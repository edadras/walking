package ir.gamyar.app.steps

import android.annotation.SuppressLint
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import com.google.android.gms.location.ActivityRecognition
import com.google.android.gms.location.ActivityTransition
import com.google.android.gms.location.ActivityTransitionRequest
import com.google.android.gms.location.ActivityTransitionResult
import com.google.android.gms.location.DetectedActivity

/**
 * Low-power Activity Recognition transitions (walking / running / vehicle /
 * bicycle / still). Used by the server to discount steps logged in a vehicle.
 * Requires Google Play services; without them this silently does nothing.
 */
object ActivityTransitions {

    /** Latest activity Google reported entering (null after its exit, or without Play services). */
    @Volatile var current: String? = null
        private set

    private val types = mapOf(
        DetectedActivity.WALKING to "walking",
        DetectedActivity.RUNNING to "running",
        DetectedActivity.IN_VEHICLE to "vehicle",
        DetectedActivity.ON_BICYCLE to "bicycle",
        DetectedActivity.STILL to "still",
    )

    @SuppressLint("MissingPermission") // checked by the caller (ACTIVITY_RECOGNITION)
    fun register(context: Context) {
        try {
            val transitions = types.keys.flatMap { type ->
                listOf(
                    ActivityTransition.Builder().setActivityType(type).setActivityTransition(ActivityTransition.ACTIVITY_TRANSITION_ENTER).build(),
                    ActivityTransition.Builder().setActivityType(type).setActivityTransition(ActivityTransition.ACTIVITY_TRANSITION_EXIT).build(),
                )
            }
            ActivityRecognition.getClient(context)
                .requestActivityTransitionUpdates(ActivityTransitionRequest(transitions), pendingIntent(context))
        } catch (e: Exception) {
            // No Play services or permission revoked: transitions stay empty.
        }
    }

    private fun pendingIntent(context: Context): PendingIntent {
        val intent = Intent(context, TransitionReceiver::class.java)
        // Activity Recognition fills in the result, so the intent must be mutable.
        return PendingIntent.getBroadcast(context, 42, intent, PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_MUTABLE)
    }

    class TransitionReceiver : BroadcastReceiver() {
        override fun onReceive(context: Context, intent: Intent) {
            if (!ActivityTransitionResult.hasResult(intent)) return
            val result = ActivityTransitionResult.extractResult(intent) ?: return
            val store = StepStore.get(context)
            // elapsedRealTimeNanos → wall clock.
            val offsetMs = System.currentTimeMillis() - android.os.SystemClock.elapsedRealtime()
            for (event in result.transitionEvents) {
                val type = types[event.activityType] ?: continue
                val enter = event.transitionType == ActivityTransition.ACTIVITY_TRANSITION_ENTER
                store.addTransition(offsetMs + event.elapsedRealTimeNanos / 1_000_000, type, enter)
                if (enter) current = type else if (current == type) current = null
            }
        }
    }
}
