package ir.gamyar.app

import io.flutter.plugin.common.EventChannel
import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel

/** Play build: no Pushe SDK; the app uses FCM instead. */
object PusheChannel : MethodChannel.MethodCallHandler, EventChannel.StreamHandler {
    const val METHODS = "ir.gamyar.app/pushe"
    const val EVENTS = "ir.gamyar.app/pushe/clicks"

    fun attach() {}

    override fun onMethodCall(call: MethodCall, result: MethodChannel.Result) {
        if (call.method == "available") result.success(false) else result.notImplemented()
    }

    override fun onListen(arguments: Any?, events: EventChannel.EventSink) {}

    override fun onCancel(arguments: Any?) {}
}
