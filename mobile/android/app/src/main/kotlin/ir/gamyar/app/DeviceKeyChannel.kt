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
        private const val ALIAS = "gamyar_device_key_v1"
        private const val KEYSTORE = "AndroidKeyStore"
    }

    private val keyStore: KeyStore by lazy { KeyStore.getInstance(KEYSTORE).apply { load(null) } }

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
                "integrityToken" -> integrityToken(call, result)
                else -> result.notImplemented()
            }
        } catch (e: Exception) {
            result.error("keystore_error", e.javaClass.simpleName, null)
        }
    }

    private fun ensureKey() {
        if (keyStore.containsAlias(ALIAS)) return
        fun spec(strongBox: Boolean) = KeyGenParameterSpec.Builder(ALIAS, KeyProperties.PURPOSE_SIGN)
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
    }

    /** Base64 of the DER SubjectPublicKeyInfo. */
    private fun publicKey(): String {
        ensureKey()
        val cert = keyStore.getCertificate(ALIAS)
        return Base64.encodeToString(cert.publicKey.encoded, Base64.NO_WRAP)
    }

    /** Base64 of a DER-encoded ECDSA(SHA-256) signature. */
    private fun sign(data: ByteArray): String {
        ensureKey()
        val key = keyStore.getKey(ALIAS, null) as PrivateKey
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
    private fun signals(): Map<String, Boolean> {
        ensureKey()
        return mapOf(
            "emulator" to isProbablyEmulator(),
            "rooted" to isProbablyRooted(),
            "hardwareBacked" to isHardwareBacked(),
        )
    }

    private fun isHardwareBacked(): Boolean = try {
        val key = keyStore.getKey(ALIAS, null) as PrivateKey
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
