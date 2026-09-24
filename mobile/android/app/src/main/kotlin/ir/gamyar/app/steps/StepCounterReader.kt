package ir.gamyar.app.steps

import android.content.Context
import android.hardware.Sensor
import android.hardware.SensorEvent
import android.hardware.SensorEventListener
import android.hardware.SensorManager
import android.os.Handler
import android.os.HandlerThread
import android.provider.Settings
import java.util.concurrent.CountDownLatch
import java.util.concurrent.TimeUnit

/**
 * One-shot read of the hardware step counter (cumulative since boot). The
 * sensor keeps counting in deep sleep, so a read every 15+ minutes loses nothing.
 */
object StepCounterReader {

    fun isAvailable(context: Context): Boolean = sensorManager(context).getDefaultSensor(Sensor.TYPE_STEP_COUNTER) != null

    fun hasDetector(context: Context): Boolean = sensorManager(context).getDefaultSensor(Sensor.TYPE_STEP_DETECTOR) != null

    fun bootCount(context: Context): Int =
        Settings.Global.getInt(context.contentResolver, Settings.Global.BOOT_COUNT, -1)

    /** Blocking; call from a background thread. Returns null when unavailable or not permitted. */
    fun read(context: Context, timeoutMs: Long = 5000): Long? {
        val manager = sensorManager(context)
        val sensor = manager.getDefaultSensor(Sensor.TYPE_STEP_COUNTER) ?: return null
        val latch = CountDownLatch(1)
        var value: Long? = null
        val thread = HandlerThread("step-read").apply { start() }
        val listener = object : SensorEventListener {
            override fun onSensorChanged(event: SensorEvent) {
                if (value == null) {
                    value = event.values[0].toLong()
                    latch.countDown()
                }
            }

            override fun onAccuracyChanged(sensor: Sensor?, accuracy: Int) {}
        }
        return try {
            if (!manager.registerListener(listener, sensor, SensorManager.SENSOR_DELAY_NORMAL, Handler(thread.looper))) return null
            latch.await(timeoutMs, TimeUnit.MILLISECONDS)
            value
        } catch (e: SecurityException) {
            null
        } finally {
            manager.unregisterListener(listener)
            thread.quitSafely()
        }
    }

    /** Reads and stores a reading; returns it (or null). */
    fun readAndStore(context: Context, mark: String = StepStore.MARK_NONE): Map<String, Any>? {
        val counter = read(context) ?: return null
        val now = System.currentTimeMillis()
        val boot = bootCount(context)
        StepStore.get(context).addReading(now, counter, boot, mark)
        return mapOf("t" to now, "c" to counter, "b" to boot, "m" to mark)
    }

    private fun sensorManager(context: Context) = context.getSystemService(Context.SENSOR_SERVICE) as SensorManager
}
