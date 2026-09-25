package ir.gamyar.app

import android.content.Context
import android.os.Build
import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyInfo
import android.security.keystore.KeyProperties
import android.security.keystore.StrongBoxUnavailableException
import android.util.Base64
import com.google.android.play.core.integrity.IntegrityManagerFactory
import com.google.android.play.core.integrity.StandardIntegrityManager.PrepareIntegrityTokenRequest
import com.google.android.play.core.integrity.StandardIntegrityManager.StandardIntegrityTokenRequest
import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel
import java.io.File
import java.security.KeyFactory
import java.security.KeyPairGenerator
import java.security.KeyStore
import java.security.PrivateKey
import java.security.Signature
import java.security.spec.ECGenParameterSpec

/**
 * Device identity key in the Android Keystore (EC P-256, non-exportable,
 * StrongBox when available). Dart only ever receives the public key and
 * signatures; the private key never leaves secure hardware.
 */
class DeviceKeyChannel(private val context: Context) : MethodChannel.MethodCallHandler {

    companion object {
        const val NAME = "ir.gamyar/device_key"
        private const val FIRST_ALIAS = "gamyar_device_key_v1"
        private const val KEYSTORE = "AndroidKeyStore"
        private const val PREFS = "gamyar_device_key"
        private const val ACTIVE = "active_alias"
        private const val PENDING = "pending_alias"
        private const val CREATED = "active_created_at"
    }

    private val keyStore: KeyStore by lazy { KeyStore.getInstance(KEYSTORE).apply { load(null) } }
    private val prefs by lazy { context.getSharedPreferences(PREFS, Context.MODE_PRIVATE) }

    /** The alias currently registered with the server. Rotation switches it atomically. */
    private val activeAlias: String get() = prefs.getString(ACTIVE, FIRST_ALIAS)!!

    override fun onMethodCall(call: MethodCall, result: MethodChannel.Result) {
        try {
            when (call.method) {
                "publicKey" -> result.success(publicKey())
                "sign" -> {
                    val data = call.argument<ByteArray>("data")
                        ?: return result.error("bad_args", "data is required", null)
                    result.success(sign(data))
                }
                "signals" -> result.success(signals())
                // Rotation: new key under a new alias; the server only learns about it
                // through a proof signed with it, and the old key is deleted only after
                // the server has accepted the new one (commit).
                "pendingPublicKey" -> result.success(pendingPublicKey())
                "signPending" -> {
                    val data = call.argument<ByteArray>("data")
                        ?: return result.error("bad_args", "data is required", null)
                    val alias = prefs.getString(PENDING, null) ?: return result.error("no_pending", "no pending key", null)
                    result.success(sign(data, alias))
                }
                "commitPending" -> result.success(commitPending())
                "discardPending" -> result.success(discardPending())
                "keyCreatedAt" -> {
                    ensureKey(activeAlias)
                    // Keys from before rotation support start their clock now.
                    if (!prefs.contains(CREATED)) prefs.edit().putLong(CREATED, System.currentTimeMillis()).apply()
                    result.success(prefs.getLong(CREATED, 0L))
                }
                "integrityToken" -> integrityToken(call, result)
                else -> result.notImplemented()
            }
        } catch (e: Exception) {
            result.error("keystore_error", e.javaClass.simpleName, null)
        }
    }

    private fun ensureKey(alias: String = activeAlias) {
        if (keyStore.containsAlias(alias)) return
        fun spec(strongBox: Boolean) = KeyGenParameterSpec.Builder(alias, KeyProperties.PURPOSE_SIGN)
            .setAlgorithmParameterSpec(ECGenParameterSpec("secp256r1"))
            .setDigests(KeyProperties.DIGEST_SHA256)
            .apply { if (strongBox && Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) setIsStrongBoxBacked(true) }
            .build()

        val generator = KeyPairGenerator.getInstance(KeyProperties.KEY_ALGORITHM_EC, KEYSTORE)
        try {
            generator.initialize(spec(strongBox = true))
            generator.generateKeyPair()
        } catch (e: StrongBoxUnavailableException) {
            generator.initialize(spec(strongBox = false))
            generator.generateKeyPair()
        }
        if (alias == activeAlias) prefs.edit().putLong(CREATED, System.currentTimeMillis()).apply()
    }

    private fun pendingPublicKey(): String {
        val existing = prefs.getString(PENDING, null)
        val alias = existing ?: run {
            val version = activeAlias.substringAfterLast("_v").toIntOrNull() ?: 1
            "gamyar_device_key_v${version + 1}"
        }
        if (existing == null) {
            if (keyStore.containsAlias(alias)) keyStore.deleteEntry(alias)
            prefs.edit().putString(PENDING, alias).commit()
        }
        ensureKey(alias)
        return Base64.encodeToString(keyStore.getCertificate(alias).publicKey.encoded, Base64.NO_WRAP)
    }

    private fun commitPending(): Boolean {
        val pending = prefs.getString(PENDING, null) ?: return false
        val old = activeAlias
        prefs.edit().putString(ACTIVE, pending).remove(PENDING).putLong(CREATED, System.currentTimeMillis()).commit()
        if (old != pending && keyStore.containsAlias(old)) keyStore.deleteEntry(old)
        return true
    }

    private fun discardPending(): Boolean {
        val pending = prefs.getString(PENDING, null) ?: return false
        if (keyStore.containsAlias(pending)) keyStore.deleteEntry(pending)
        prefs.edit().remove(PENDING).commit()
        return true
    }

    /** Base64 of the DER SubjectPublicKeyInfo. */
    private fun publicKey(): String {
        ensureKey()
        val cert = keyStore.getCertificate(activeAlias)
        return Base64.encodeToString(cert.publicKey.encoded, Base64.NO_WRAP)
    }

    /** Base64 of a DER-encoded ECDSA(SHA-256) signature. */
    private fun sign(data: ByteArray, alias: String = activeAlias): String {
        ensureKey(alias)
        val key = keyStore.getKey(alias, null) as PrivateKey
        val signature = Signature.getInstance("SHA256withECDSA").apply {
            initSign(key)
            update(data)
        }.sign()
        return Base64.encodeToString(signature, Base64.NO_WRAP)
    }

    /**
     * Cheap local hints. They are trivially spoofable on a rooted device, so the
     * server gives them low weight and relies on Play Integrity for real verdicts.
     */
    private fun signals(): Map<String, Any?> {
        ensureKey()
        return mapOf(
            "emulator" to isProbablyEmulator(),
            "rooted" to isProbablyRooted(),
            "hardwareBacked" to isHardwareBacked(),
            "installer" to installer(),
        )
    }

    /** Package that installed us (Play, Bazaar, Myket…); null for sideloads and debug installs. */
    private fun installer(): String? = try {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
            context.packageManager.getInstallSourceInfo(context.packageName).installingPackageName
        } else {
            @Suppress("DEPRECATION")
            context.packageManager.getInstallerPackageName(context.packageName)
        }
    } catch (e: Exception) {
        null
    }

    private fun isHardwareBacked(): Boolean = try {
        val key = keyStore.getKey(activeAlias, null) as PrivateKey
        val info = KeyFactory.getInstance(key.algorithm, KEYSTORE).getKeySpec(key, KeyInfo::class.java)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            info.securityLevel != KeyProperties.SECURITY_LEVEL_SOFTWARE
        } else {
            @Suppress("DEPRECATION")
            info.isInsideSecureHardware
        }
    } catch (e: Exception) {
        false
    }

    private fun isProbablyEmulator(): Boolean {
        val fp = Build.FINGERPRINT.lowercase()
        val model = Build.MODEL.lowercase()
        val hw = Build.HARDWARE.lowercase()
        return fp.startsWith("generic") || fp.contains("emulator") || fp.contains("sdk_gphone") ||
            model.contains("emulator") || model.contains("android sdk built for") ||
            hw.contains("goldfish") || hw.contains("ranchu") ||
            Build.PRODUCT.lowercase().contains("sdk") ||
            Build.MANUFACTURER.lowercase().contains("genymotion")
    }

    private fun isProbablyRooted(): Boolean {
        if (Build.TAGS?.contains("test-keys") == true) return true
        val paths = listOf(
            "/system/bin/su", "/system/xbin/su", "/sbin/su", "/system/su", "/vendor/bin/su",
            "/data/local/su", "/data/local/bin/su", "/data/local/xbin/su", "/system/app/Superuser.apk",
        )
        return paths.any { File(it).exists() }
    }

    /** Play Integrity (standard request) bound to the server-provided request hash. */
    private fun integrityToken(call: MethodCall, result: MethodChannel.Result) {
        val requestHash = call.argument<String>("requestHash")
        val projectNumber = call.argument<Number>("cloudProjectNumber")?.toLong() ?: 0L
        if (requestHash == null || projectNumber == 0L) return result.success(null)

        val manager = IntegrityManagerFactory.createStandard(context)
        manager.prepareIntegrityToken(PrepareIntegrityTokenRequest.builder().setCloudProjectNumber(projectNumber).build())
            .addOnSuccessListener { provider ->
                provider.request(StandardIntegrityTokenRequest.builder().setRequestHash(requestHash).build())
                    .addOnSuccessListener { response -> result.success(response.token()) }
                    .addOnFailureListener { result.success(null) }
            }
            .addOnFailureListener { result.success(null) }
    }
}
