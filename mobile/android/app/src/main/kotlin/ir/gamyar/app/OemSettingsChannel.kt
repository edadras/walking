package ir.gamyar.app

import android.content.ActivityNotFoundException
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.provider.Settings
import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel

/**
 * Vendor "autostart / protected apps" screens. Several Android skins kill
 * background work regardless of the battery-optimisation exemption, so the
 * user has to allow the app there too. Component names differ per ROM
 * version; each candidate is tried in order, falling back to app details.
 */
class OemSettingsChannel(private val context: Context) : MethodChannel.MethodCallHandler {
    companion object {
        const val NAME = "ir.gamyar.app/oem"

        private val CANDIDATES: Map<String, List<ComponentName>> = mapOf(
            "xiaomi" to listOf(
                ComponentName("com.miui.securitycenter", "com.miui.permcenter.autostart.AutoStartManagementActivity"),
                ComponentName("com.miui.securitycenter", "com.miui.powercenter.PowerSettings"),
            ),
            "huawei" to listOf(
                ComponentName("com.huawei.systemmanager", "com.huawei.systemmanager.startupmgr.ui.StartupNormalAppListActivity"),
                ComponentName("com.huawei.systemmanager", "com.huawei.systemmanager.optimize.process.ProtectActivity"),
            ),
            "oppo" to listOf(
                ComponentName("com.coloros.safecenter", "com.coloros.safecenter.permission.startup.StartupAppListActivity"),
                ComponentName("com.coloros.safecenter", "com.coloros.safecenter.startupapp.StartupAppListActivity"),
                ComponentName("com.oppo.safe", "com.oppo.safe.permission.startup.StartupAppListActivity"),
            ),
            "vivo" to listOf(
                ComponentName("com.vivo.permissionmanager", "com.vivo.permissionmanager.activity.BgStartUpManagerActivity"),
                ComponentName("com.iqoo.secure", "com.iqoo.secure.ui.phoneoptimize.AddWhiteListActivity"),
            ),
            "oneplus" to listOf(
                ComponentName("com.oneplus.security", "com.oneplus.security.chainlaunch.view.ChainLaunchAppListActivity"),
            ),
            "samsung" to listOf(
                ComponentName("com.samsung.android.lool", "com.samsung.android.sm.battery.ui.BatteryActivity"),
                ComponentName("com.samsung.android.lool", "com.samsung.android.sm.ui.battery.BatteryActivity"),
            ),
        )

        /** Maps sub-brands onto the ROM that ships the settings screen. */
        fun family(manufacturer: String): String? = when (manufacturer.lowercase()) {
            "xiaomi", "redmi", "poco" -> "xiaomi"
            "huawei", "honor" -> "huawei"
            "oppo", "realme" -> "oppo"
            "vivo", "iqoo" -> "vivo"
            "oneplus" -> "oneplus"
            "samsung" -> "samsung"
            else -> null
        }
    }

    override fun onMethodCall(call: MethodCall, result: MethodChannel.Result) {
        when (call.method) {
            "info" -> result.success(mapOf("manufacturer" to Build.MANUFACTURER, "family" to family(Build.MANUFACTURER), "sdk" to Build.VERSION.SDK_INT))
            "openAutostart" -> result.success(openAutostart())
            else -> result.notImplemented()
        }
    }

    private fun openAutostart(): Boolean {
        val candidates = family(Build.MANUFACTURER)?.let { CANDIDATES[it] }.orEmpty()
        for (component in candidates) {
            try {
                context.startActivity(Intent().setComponent(component).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
                return true
            } catch (_: ActivityNotFoundException) {
            } catch (_: SecurityException) {
            }
        }
        return try {
            context.startActivity(
                Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS, Uri.parse("package:${context.packageName}")).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK),
            )
            false
        } catch (_: ActivityNotFoundException) {
            false
        }
    }
}
