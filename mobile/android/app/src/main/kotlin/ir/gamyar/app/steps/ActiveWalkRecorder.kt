package ir.gamyar.app.steps

import android.content.Context
import android.location.Location
import android.os.Build
import kotlin.math.sqrt

/**
 * In-memory state of the user-started walk. Raw sensor samples are reduced to
 * one-minute buckets on the device; only the summaries ever leave the phone.
 */
object ActiveWalkRecorder {

    private const val BUCKET_MS = 60_000L
    private const val MAX_JUMP_SPEED = 50.0 // m/s: implied speed above this is a GPS jump
    private const val MAX_FIX_ACCURACY = 50f // metres: worse fixes are ignored for distance

    data class Bucket(
        val startMs: Long,
        val durationS: Int,
        val steps: Int,
        val detectorSteps: Int,
        val accelStd: Double?,
        val accelPeakHz: Double?,
        val speedMps: Double?,
        val gpsAccuracyM: Int?,
        val activityType: String?,
    )

    @Volatile var running = false
        private set
    var startedAtMs = 0L
        private set
    var withGps = false
        private set

    private var counterBaseline: Long? = null
    private var lastCounter: Long? = null
    private var bucketStartMs = 0L
    private var bucketCounterStart: Long? = null
    private var bucketDetector = 0
    private val buckets = mutableListOf<Bucket>()

    // Accelerometer features for the current bucket.
    private var accN = 0
    private var accMean = 0.0
    private var accM2 = 0.0
    private var emaMean = 9.81
    private var lastSign = 0
    private var crossings = 0
    private var accFirstMs = 0L
    private var accLastMs = 0L

    // GPS summary.
    private var lastFix: Location? = null
    private var gpsPoints = 0
    private var gpsDistance = 0.0
    private var gpsMaxSpeed = 0.0
    private var gpsJumps = 0
    private var gpsAccuracySum = 0.0
    private var mockDetected = false
    private var bucketSpeedSum = 0.0
    private var bucketSpeedN = 0
    private var bucketAccuracySum = 0.0

    // Live mode (walking / running / cycling / vehicle / still) from the last ~30 s.
    private const val WINDOW_MS = 30_000L
    private val recentCounter = ArrayDeque<Pair<Long, Long>>()
    private val recentSpeed = ArrayDeque<Pair<Long, Float>>()
    private var mode = "still"
    private var cyclingDistance = 0.0
    private var bucketLabel: String? = null

    // Route: points ≥ 10 m apart from good fixes. Stays on the phone unless the user shares routes.
    private const val ROUTE_MIN_STEP_M = 10f
    private const val ROUTE_MAX_ACCURACY = 25f
    private const val ROUTE_MAX_POINTS = 5000
    private val route = mutableListOf<List<Number>>()
    private var lastRoutePoint: Location? = null

    var onLiveUpdate: ((Map<String, Any>) -> Unit)? = null

    @Synchronized
    fun start(nowMs: Long, gps: Boolean) {
        reset()
        running = true
        startedAtMs = nowMs
        bucketStartMs = nowMs
        withGps = gps
    }

    @Synchronized
    fun onCounter(context: Context, value: Long, nowMs: Long) {
        if (!running) return
        if (counterBaseline == null) {
            counterBaseline = value
            bucketCounterStart = value
            StepStore.get(context).addReading(nowMs, value, StepCounterReader.bootCount(context), StepStore.MARK_ACTIVE_START)
        }
        lastCounter = value
        recentCounter.addLast(nowMs to value)
        while (recentCounter.size > 2 && nowMs - recentCounter.first().first > WINDOW_MS) recentCounter.removeFirst()
        emitLive(nowMs)
    }

    @Synchronized
    fun onDetectorStep() {
        if (running) bucketDetector++
    }

    @Synchronized
    fun onAccel(x: Float, y: Float, z: Float, nowMs: Long) {
        if (!running) return
        val mag = sqrt((x * x + y * y + z * z).toDouble())
        // Welford for the bucket's standard deviation.
        accN++
        val delta = mag - accMean
        accMean += delta / accN
        accM2 += delta * (mag - accMean)
        // Zero crossings of the detrended signal ≈ 2 per gait cycle → dominant frequency.
        emaMean += 0.02 * (mag - emaMean)
        val sign = if (mag - emaMean >= 0) 1 else -1
        if (lastSign != 0 && sign != lastSign) crossings++
        lastSign = sign
        if (accFirstMs == 0L) accFirstMs = nowMs
        accLastMs = nowMs
    }

    @Synchronized
    fun onLocation(location: Location) {
        if (!running) return
        val mock = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) location.isMock else @Suppress("DEPRECATION") location.isFromMockProvider
        if (mock) mockDetected = true
        gpsPoints++
        gpsAccuracySum += location.accuracy
        bucketAccuracySum += location.accuracy
        if (location.hasSpeed()) {
            gpsMaxSpeed = maxOf(gpsMaxSpeed, location.speed.toDouble())
            bucketSpeedSum += location.speed
            bucketSpeedN++
            recentSpeed.addLast(location.time to location.speed)
            while (recentSpeed.size > 1 && location.time - recentSpeed.first().first > WINDOW_MS) recentSpeed.removeFirst()
        }
        if (location.accuracy <= ROUTE_MAX_ACCURACY && route.size < ROUTE_MAX_POINTS && (lastRoutePoint?.distanceTo(location) ?: Float.MAX_VALUE) >= ROUTE_MIN_STEP_M) {
            route += listOf(round6(location.latitude), round6(location.longitude), location.time)
            lastRoutePoint = location
        }
        val previous = lastFix
        if (location.accuracy <= MAX_FIX_ACCURACY) {
            if (previous != null) {
                val meters = previous.distanceTo(location).toDouble()
                val seconds = (location.time - previous.time) / 1000.0
                if (seconds > 0 && meters / seconds > MAX_JUMP_SPEED) {
                    gpsJumps++
                } else {
                    gpsDistance += meters
                    if (mode == "cycling") cyclingDistance += meters
                }
            }
            lastFix = location
        }
    }

    /** Called every second by the service; closes finished minute buckets. */
    @Synchronized
    fun tick(context: Context, nowMs: Long) {
        if (!running) return
        while (nowMs - bucketStartMs >= BUCKET_MS) closeBucket(bucketStartMs + BUCKET_MS)
        mode = detectMode(nowMs)
        // Any Activity Recognition label seen during the minute is kept for the server.
        ActivityTransitions.current?.let { bucketLabel = it }
        emitLive(nowMs)
    }

    /**
     * Best guess for the screen; the server re-decides from the minute summaries.
     * Pedalling = moving at bike speed with few steps but a clearly shaking phone.
     */
    private fun detectMode(nowMs: Long): String {
        val label = ActivityTransitions.current
        val speed = recentSpeed.takeIf { withGps && it.isNotEmpty() && nowMs - it.last().first < 15_000 }?.map { it.second }?.average()
        val first = recentCounter.firstOrNull()
        val last = recentCounter.lastOrNull()
        val cadence = if (first != null && last != null && last.first - first.first >= 10_000) (last.second - first.second) * 60_000.0 / (last.first - first.first) else 0.0
        val accelStd = if (accN > 1) sqrt(accM2 / (accN - 1)) else 0.0
        return when {
            label == "vehicle" -> "vehicle"
            label == "bicycle" -> "cycling"
            speed != null && speed > 11.1 -> "vehicle"
            speed != null && speed >= 2.8 && cadence < 60 -> if (accelStd >= 0.8) "cycling" else "vehicle"
            cadence >= 145 -> "running"
            cadence >= 20 -> "walking"
            else -> "still"
        }
    }

    /** Finishes the walk and returns the session payload for Dart. */
    @Synchronized
    fun finish(context: Context, nowMs: Long): Map<String, Any?>? {
        if (!running) return null
        while (nowMs - bucketStartMs >= BUCKET_MS) closeBucket(bucketStartMs + BUCKET_MS)
        if (nowMs - bucketStartMs >= 1000) closeBucket(nowMs)
        running = false

        lastCounter?.let { StepStore.get(context).addReading(nowMs, it, StepCounterReader.bootCount(context), StepStore.MARK_ACTIVE_END) }

        val endMs = buckets.lastOrNull()?.let { it.startMs + it.durationS * 1000L } ?: nowMs
        val payload = mapOf(
            "started_at_ms" to startedAtMs,
            "ended_at_ms" to endMs,
            "raw_steps" to buckets.sumOf { it.steps },
            "buckets" to buckets.map {
                mapOf(
                    "started_at_ms" to it.startMs,
                    "duration_s" to it.durationS,
                    "steps" to it.steps,
                    "detector_steps" to it.detectorSteps,
                    "accel_std" to it.accelStd,
                    "accel_peak_hz" to it.accelPeakHz,
                    "speed_mps" to it.speedMps,
                    "gps_accuracy_m" to it.gpsAccuracyM,
                    "activity_type" to it.activityType,
                )
            },
            "gps" to if (withGps) mapOf(
                "points" to gpsPoints,
                "distance_m" to gpsDistance,
                "avg_accuracy_m" to if (gpsPoints > 0) gpsAccuracySum / gpsPoints else null,
                "max_speed_mps" to gpsMaxSpeed,
                "jumps" to gpsJumps,
                "mock_detected" to mockDetected,
            ) else null,
            "mock_location" to mockDetected,
            // [lat, lng, epoch ms]; uploaded only if the user chose to share routes on the public map.
            "route" to if (withGps && !mockDetected) route.toList() else emptyList<List<Number>>(),
        )
        reset()
        return payload
    }

    @Synchronized
    fun snapshot(nowMs: Long): Map<String, Any>? = if (!running) null else live(nowMs)

    private fun closeBucket(endMs: Long) {
        val steps = ((lastCounter ?: 0L) - (bucketCounterStart ?: lastCounter ?: 0L)).coerceAtLeast(0L).toInt()
        val seconds = ((accLastMs - accFirstMs) / 1000.0).takeIf { it > 5 }
        buckets += Bucket(
            startMs = bucketStartMs,
            durationS = ((endMs - bucketStartMs) / 1000).toInt().coerceAtLeast(1),
            steps = steps,
            detectorSteps = bucketDetector,
            accelStd = if (accN > 1) round3(sqrt(accM2 / (accN - 1))) else null,
            accelPeakHz = seconds?.let { round3(crossings / 2.0 / it) },
            speedMps = if (bucketSpeedN > 0) round3(bucketSpeedSum / bucketSpeedN) else null,
            gpsAccuracyM = if (bucketSpeedN > 0) (bucketAccuracySum / bucketSpeedN).toInt() else null,
            activityType = bucketLabel,
        )
        bucketLabel = ActivityTransitions.current
        bucketStartMs = endMs
        bucketCounterStart = lastCounter
        bucketDetector = 0
        accN = 0; accMean = 0.0; accM2 = 0.0; crossings = 0; accFirstMs = 0L; accLastMs = 0L
        bucketSpeedSum = 0.0; bucketSpeedN = 0; bucketAccuracySum = 0.0
    }

    private fun live(nowMs: Long): Map<String, Any> = mapOf(
        "steps" to ((lastCounter ?: 0L) - (counterBaseline ?: lastCounter ?: 0L)).toInt(),
        "elapsed_s" to ((nowMs - startedAtMs) / 1000).toInt(),
        "distance_m" to gpsDistance,
        "gps" to withGps,
        "mode" to mode,
        "cycling_distance_m" to cyclingDistance,
        "speed_kmh" to (recentSpeed.lastOrNull()?.second?.times(3.6) ?: 0.0),
    )

    private fun emitLive(nowMs: Long) {
        onLiveUpdate?.invoke(live(nowMs))
    }

    private fun reset() {
        running = false
        counterBaseline = null; lastCounter = null; bucketCounterStart = null
        bucketDetector = 0; buckets.clear()
        accN = 0; accMean = 0.0; accM2 = 0.0; emaMean = 9.81; lastSign = 0; crossings = 0; accFirstMs = 0L; accLastMs = 0L
        lastFix = null; gpsPoints = 0; gpsDistance = 0.0; gpsMaxSpeed = 0.0; gpsJumps = 0; gpsAccuracySum = 0.0; mockDetected = false
        bucketSpeedSum = 0.0; bucketSpeedN = 0; bucketAccuracySum = 0.0
        recentCounter.clear(); recentSpeed.clear(); mode = "still"; cyclingDistance = 0.0; bucketLabel = null
        route.clear(); lastRoutePoint = null
    }

    val currentMode: String get() = mode

    private fun round6(v: Double) = Math.round(v * 1_000_000.0) / 1_000_000.0

    private fun round3(v: Double) = Math.round(v * 1000.0) / 1000.0
}
