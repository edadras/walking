package ir.gamyar.app.steps

import android.content.Context
import androidx.work.Constraints
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.Worker
import androidx.work.WorkerParameters
import java.util.concurrent.TimeUnit

/**
 * Periodic background read of the step counter (WorkManager minimum: 15 min).
 * Doze may delay it; that only delays syncing, not counting. No network, no GPS.
 */
class PassiveStepWorker(context: Context, params: WorkerParameters) : Worker(context, params) {

    companion object {
        private const val NAME = "gamyar_passive_steps"

        fun schedule(context: Context) {
            val request = PeriodicWorkRequestBuilder<PassiveStepWorker>(15, TimeUnit.MINUTES)
                .setConstraints(Constraints.Builder().setRequiresBatteryNotLow(false).build())
                .build()
            WorkManager.getInstance(context).enqueueUniquePeriodicWork(NAME, ExistingPeriodicWorkPolicy.KEEP, request)
        }

        fun cancel(context: Context) = WorkManager.getInstance(context).cancelUniqueWork(NAME)
    }

    override fun doWork(): Result {
        // An active walk records its own data; a background read in the middle is harmless
        // (Dart skips intervals between active_start/active_end marks).
        StepCounterReader.readAndStore(applicationContext)
        return Result.success()
    }
}
