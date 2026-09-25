package ir.gamyar.app

import android.content.Context
import com.android.installreferrer.api.InstallReferrerClient
import com.android.installreferrer.api.InstallReferrerStateListener
import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel

/**
 * Reads the Google Play install referrer once (e.g. "code=ABCD1234" from an invite link's
 * store button). Other stores don't provide it: the call answers null.
 */
class InstallReferrerChannel(private val context: Context) : MethodChannel.MethodCallHandler {
    companion object {
        const val NAME = "ir.gamyar.app/install_referrer"
    }

    override fun onMethodCall(call: MethodCall, result: MethodChannel.Result) {
        if (call.method != "get") return result.notImplemented()
        val client = InstallReferrerClient.newBuilder(context).build()
        var answered = false
        fun answer(value: String?) {
            if (answered) return
            answered = true
            result.success(value)
            runCatching { client.endConnection() }
        }
        try {
            client.startConnection(object : InstallReferrerStateListener {
                override fun onInstallReferrerSetupFinished(code: Int) {
                    answer(if (code == InstallReferrerClient.InstallReferrerResponse.OK) runCatching { client.installReferrer.installReferrer }.getOrNull() else null)
                }

                override fun onInstallReferrerServiceDisconnected() = answer(null)
            })
        } catch (e: Exception) {
            answer(null)
        }
    }
}
