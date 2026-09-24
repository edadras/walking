package ir.gamyar.app.steps

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.os.Build
import android.os.Handler
import android.os.Looper
import androidx.core.content.ContextCompat
import io.flutter.plugin.common.EventChannel
import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel
import java.util.concurrent.Executors

/** Bridge between Dart and the native step tracking components. */
class StepsChannel(private val context: Context) : MethodChannel.MethodCallHandler, EventChannel.StreamHandler {

    companion object {
        const val METHODS = "ir.gamyar/steps"
        const val EVENTS = "ir.gamyar/steps/live"
    }

    private val io = Executors.newSingleThreadExecutor()
    private val main = Handler(Looper.getMainLooper())

    override fun onMethodCall(call: MethodCall, result: MethodChannel.Result) {
        when (call.method) {
            "capabilities" -> result.success(mapOf(
                "step_counter" to StepCounterReader.isAvailable(context),
                "step_detector" to StepCounterReader.hasDetector(context),
                "activity_permission" to hasActivityPermission(),
            ))
            "startPassive" -> {
                if (!hasActivityPermission()) return result.success(false)
                PassiveStepWorker.schedule(context)
                ActivityTransitions.register(context)
                background(result) { StepCounterReader.readAndStore(context) != null }
            }
            "readNow" -> background(result) { StepCounterReader.readAndStore(context) }
            "readings" -> background(result) {
                mapOf("readings" to StepStore.get(context).readings(), "transitions" to StepStore.get(context).transitions())
            }
            "ack" -> {
                val readingsBefore = call.argument<Number>("readings_before")?.toLong()
                val transitionsBefore = call.argument<Number>("transitions_before")?.toLong()
                background(result) {
                    readingsBefore?.let { StepStore.get(context).ackReadings(it) }
                    transitionsBefore?.let { StepStore.get(context).ackTransitions(it) }
                    true
                }
            }
            "startActive" -> {
                if (!hasActivityPermission()) return result.error("permission", "activity recognition", null)
                if (!StepCounterReader.isAvailable(context)) return result.error("no_sensor", "step counter missing", null)
                ActiveWalkService.start(context, call.argument<Boolean>("gps") == true)
                result.success(true)
            }
            "stopActive" -> {
                val payload = ActiveWalkRecorder.finish(context, System.currentTimeMillis())
                ActiveWalkService.stop(context)
                result.success(payload)
            }
            "activeState" -> result.success(ActiveWalkRecorder.snapshot(System.currentTimeMillis()))
            else -> result.notImplemented()
        }
    }

    override fun onListen(arguments: Any?, events: EventChannel.EventSink) {
        ActiveWalkRecorder.onLiveUpdate = { update -> main.post { events.success(update) } }
    }

    override fun onCancel(arguments: Any?) {
        ActiveWalkRecorder.onLiveUpdate = null
    }

    private fun hasActivityPermission(): Boolean =
        Build.VERSION.SDK_INT < Build.VERSION_CODES.Q ||
            ContextCompat.checkSelfPermission(context, Manifest.permission.ACTIVITY_RECOGNITION) == PackageManager.PERMISSION_GRANTED

    private fun background(result: MethodChannel.Result, work: () -> Any?) {
        io.execute {
            val value = try { work() } catch (e: Exception) { null }
            main.post { result.success(value) }
        }
    }
}
