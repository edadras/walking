package ir.gamyar.app

import android.os.Handler
import android.os.Looper
import java.util.concurrent.atomic.AtomicBoolean
import co.pushe.plus.Pushe
import co.pushe.plus.notification.NotificationButtonData
import co.pushe.plus.notification.NotificationData
import co.pushe.plus.notification.PusheNotification
import co.pushe.plus.notification.PusheNotificationListener
import io.flutter.plugin.common.EventChannel
import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel

/**
 * Pushe bridge for Bazaar/Myket builds. The Pushe device id is registered with
 * our server as the push token; notification taps are forwarded to Dart with
 * their custom content (type/id), which the app maps to a screen.
 */
object PusheChannel : MethodChannel.MethodCallHandler, EventChannel.StreamHandler {
    const val METHODS = "ir.gamyar.app/pushe"
    const val EVENTS = "ir.gamyar.app/pushe/clicks"

    private val main = Handler(Looper.getMainLooper())
    private var sink: EventChannel.EventSink? = null
    private var pendingClick: Map<String, String>? = null
    private var listening = false

    fun attach() {
        if (listening) return
        listening = true
        // Modules are only usable after Pushe finished initialising.
        Pushe.setInitializationCompleteListener { listenForClicks() }
    }

    private fun listenForClicks() {
        Pushe.getPusheService(PusheNotification::class.java)?.setNotificationListener(object : PusheNotificationListener {
            override fun onNotification(notification: NotificationData) {}
            override fun onCustomContentNotification(customContent: MutableMap<String, Any>) {}
            override fun onNotificationDismiss(notification: NotificationData) {}
            override fun onNotificationButtonClick(button: NotificationButtonData, notification: NotificationData) = onNotificationClick(notification)

            override fun onNotificationClick(notification: NotificationData) {
                val data = notification.customContent?.mapValues { "${it.value}" } ?: emptyMap()
                main.post {
                    val s = sink
                    if (s != null) s.success(data) else pendingClick = data
                }
            }
        })
    }

    override fun onMethodCall(call: MethodCall, result: MethodChannel.Result) {
        when (call.method) {
            "available" -> result.success(true)
            "deviceId" -> {
                if (Pushe.isRegistered()) {
                    result.success(Pushe.getDeviceId())
                } else {
                    // Registration needs the network; answer once it completes (exactly once).
                    val answered = AtomicBoolean(false)
                    Pushe.setRegistrationCompleteListener {
                        if (answered.compareAndSet(false, true)) main.post { result.success(Pushe.getDeviceId()) }
                    }
                }
            }
            "launchClick" -> {
                result.success(pendingClick)
                pendingClick = null
            }
            else -> result.notImplemented()
        }
    }

    override fun onListen(arguments: Any?, events: EventChannel.EventSink) {
        sink = events
    }

    override fun onCancel(arguments: Any?) {
        sink = null
    }
}
