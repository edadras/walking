package ir.gamyar.app

import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.EventChannel
import io.flutter.plugin.common.MethodChannel
import ir.gamyar.app.steps.StepsChannel

class MainActivity : FlutterActivity() {
    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, DeviceKeyChannel.NAME)
            .setMethodCallHandler(DeviceKeyChannel(applicationContext))

        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, OemSettingsChannel.NAME)
            .setMethodCallHandler(OemSettingsChannel(applicationContext))

        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, StepWidget.CHANNEL)
            .setMethodCallHandler(StepWidget.Channel(applicationContext))

        PusheChannel.attach()
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, PusheChannel.METHODS).setMethodCallHandler(PusheChannel)
        EventChannel(flutterEngine.dartExecutor.binaryMessenger, PusheChannel.EVENTS).setStreamHandler(PusheChannel)

        val steps = StepsChannel(applicationContext)
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, StepsChannel.METHODS).setMethodCallHandler(steps)
        EventChannel(flutterEngine.dartExecutor.binaryMessenger, StepsChannel.EVENTS).setStreamHandler(steps)
    }
}
