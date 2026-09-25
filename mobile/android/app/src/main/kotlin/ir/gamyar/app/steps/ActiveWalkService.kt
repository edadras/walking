package ir.gamyar.app.steps

import android.Manifest
import android.annotation.SuppressLint
import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.content.pm.ServiceInfo
import android.hardware.Sensor
import android.hardware.SensorEvent
import android.hardware.SensorEventListener
import android.hardware.SensorManager
import android.location.LocationListener
import android.location.LocationManager
import android.os.Build
import android.os.Handler
import android.os.IBinder
import android.os.Looper
import androidx.core.app.NotificationCompat
import androidx.core.app.ServiceCompat
import androidx.core.content.ContextCompat
import ir.gamyar.app.MainActivity
import ir.gamyar.app.R

/**
 * Foreground service for a walk the user started explicitly. Type `health`
 * (plus `location` only when the user opted into route tracking), as required
 * from Android 14. Never started for background/passive counting.
 */
class ActiveWalkService : Service(), SensorEventListener {

    companion object {
        const val ACTION_START = "ir.gamyar.walk.START"
        const val EXTRA_GPS = "gps"
        private const val CHANNEL = "active_walk"
        private const val NOTIFICATION_ID = 7001
        private const val ACCEL_PERIOD_US = 40_000 // 25 Hz: enough for gait (1–3 Hz), cheap on battery

        fun start(context: Context, gps: Boolean) {
            val intent = Intent(context, ActiveWalkService::class.java).setAction(ACTION_START).putExtra(EXTRA_GPS, gps)
            ContextCompat.startForegroundService(context, intent)
        }

        fun stop(context: Context) {
            context.stopService(Intent(context, ActiveWalkService::class.java))
        }
    }

    private lateinit var sensors: SensorManager
    private var locationManager: LocationManager? = null
    private val handler = Handler(Looper.getMainLooper())
    private var lastNotifiedSteps = -1

    private val locationListener = LocationListener { ActiveWalkRecorder.onLocation(it) }

    private val ticker = object : Runnable {
        override fun run() {
            val now = System.currentTimeMillis()
            ActiveWalkRecorder.tick(this@ActiveWalkService, now)
            ActiveWalkRecorder.snapshot(now)?.let { updateNotification(it["steps"] as Int) }
            handler.postDelayed(this, 1000)
        }
    }

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        if (intent?.action != ACTION_START) {
            // Restarted by the system without our intent: we can't resume sensors reliably.
            stopSelf()
            return START_NOT_STICKY
        }
        val gps = intent.getBooleanExtra(EXTRA_GPS, false) && hasLocationPermission()

        createChannel()
        var type = ServiceInfo.FOREGROUND_SERVICE_TYPE_HEALTH
        if (gps) type = type or ServiceInfo.FOREGROUND_SERVICE_TYPE_LOCATION
        ServiceCompat.startForeground(this, NOTIFICATION_ID, buildNotification(0), if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) type else 0)

        ActiveWalkRecorder.start(System.currentTimeMillis(), gps)
        sensors = getSystemService(Context.SENSOR_SERVICE) as SensorManager
        sensors.getDefaultSensor(Sensor.TYPE_STEP_COUNTER)?.let { sensors.registerListener(this, it, SensorManager.SENSOR_DELAY_NORMAL) }
        sensors.getDefaultSensor(Sensor.TYPE_STEP_DETECTOR)?.let { sensors.registerListener(this, it, SensorManager.SENSOR_DELAY_NORMAL) }
        sensors.getDefaultSensor(Sensor.TYPE_ACCELEROMETER)?.let { sensors.registerListener(this, it, ACCEL_PERIOD_US, 2_000_000) }
        if (gps) startLocation()
        handler.post(ticker)
        return START_NOT_STICKY
    }

    override fun onDestroy() {
        handler.removeCallbacks(ticker)
        if (::sensors.isInitialized) sensors.unregisterListener(this)
        locationManager?.removeUpdates(locationListener)
        super.onDestroy()
    }

    override fun onSensorChanged(event: SensorEvent) {
        val now = System.currentTimeMillis()
        when (event.sensor.type) {
            Sensor.TYPE_STEP_COUNTER -> ActiveWalkRecorder.onCounter(this, event.values[0].toLong(), now)
            Sensor.TYPE_STEP_DETECTOR -> ActiveWalkRecorder.onDetectorStep()
            Sensor.TYPE_ACCELEROMETER -> ActiveWalkRecorder.onAccel(event.values[0], event.values[1], event.values[2], now)
        }
    }

    override fun onAccuracyChanged(sensor: Sensor?, accuracy: Int) {}

    @SuppressLint("MissingPermission") // guarded by hasLocationPermission()
    private fun startLocation() {
        val manager = getSystemService(Context.LOCATION_SERVICE) as LocationManager
        locationManager = manager
        // Platform LocationManager (works without Google Play services). 5 s / 5 m keeps GPS duty low.
        if (manager.isProviderEnabled(LocationManager.GPS_PROVIDER)) {
            manager.requestLocationUpdates(LocationManager.GPS_PROVIDER, 5000L, 5f, locationListener, Looper.getMainLooper())
        }
    }

    private fun hasLocationPermission() =
        ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED

    private fun createChannel() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        val manager = getSystemService(NotificationManager::class.java)
        if (manager.getNotificationChannel(CHANNEL) == null) {
            manager.createNotificationChannel(NotificationChannel(CHANNEL, "پیاده‌روی در حال ثبت", NotificationManager.IMPORTANCE_LOW).apply {
                setShowBadge(false)
            })
        }
    }

    private fun buildNotification(steps: Int): Notification {
        val open = PendingIntent.getActivity(this, 0, Intent(this, MainActivity::class.java), PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT)
        return NotificationCompat.Builder(this, CHANNEL)
            .setSmallIcon(R.drawable.ic_stat_gamyar)
            .setColor(0xFF1A7F4B.toInt())
            .setContentTitle("پیاده‌روی در حال ثبت")
            .setContentText(toPersianDigits(steps) + " قدم")
            .setOngoing(true)
            .setOnlyAlertOnce(true)
            .setContentIntent(open)
            .setCategory(NotificationCompat.CATEGORY_WORKOUT)
            .build()
    }

    private fun updateNotification(steps: Int) {
        if (steps == lastNotifiedSteps || steps % 10 != 0 && lastNotifiedSteps >= 0) return
        lastNotifiedSteps = steps
        getSystemService(NotificationManager::class.java).notify(NOTIFICATION_ID, buildNotification(steps))
    }

    private fun toPersianDigits(n: Int): String {
        val digits = "۰۱۲۳۴۵۶۷۸۹"
        return String.format("%,d", n).map { if (it.isDigit()) digits[it - '0'] else if (it == ',') '٬' else it }.joinToString("")
    }
}
