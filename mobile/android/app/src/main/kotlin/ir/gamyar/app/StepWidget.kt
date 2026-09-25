package ir.gamyar.app

import android.Manifest
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.appwidget.AppWidgetManager
import android.appwidget.AppWidgetProvider
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import android.widget.RemoteViews
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel
import ir.gamyar.app.steps.StepStore
import java.text.NumberFormat
import java.time.LocalDate
import java.util.Locale

/**
 * Home-screen widgets (3×2 and compact 2×1) and the lock-screen "today" notification.
 *
 * The main number is exactly what the app last showed (server-verified, pushed from Dart),
 * so it never disagrees with the app. Steps the phone counted since then (read in the
 * background every ~15 minutes) are shown separately as "pending" until the next sync
 * verifies them.
 */
class StepWidget : AppWidgetProvider() {
    companion object {
        const val CHANNEL = "ir.gamyar.app/widget"
        private const val PREFS = "gamyar_widget"
        private const val NOTIFICATION_CHANNEL = "daily_progress"
        private const val NOTIFICATION_ID = 4242
        private val fa: NumberFormat = NumberFormat.getIntegerInstance(Locale.forLanguageTag("fa-IR"))

        private fun prefs(context: Context) = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)

        /** Steps counted on the device since the app's last snapshot (null = unknown, e.g. after a reboot). */
        private fun pending(context: Context): Int? {
            val p = prefs(context)
            if (p.getString("date", null) != LocalDate.now().toString() || !p.contains("base_counter")) return null
            val last = StepStore.get(context).lastReading() ?: return null
            val counter = (last["c"] as Number).toLong()
            val boot = (last["b"] as Number).toInt()
            val base = p.getLong("base_counter", -1)
            if (boot != p.getInt("base_boot", -1) || counter < base) return null
            return (counter - base).toInt()
        }

        private fun signedIn(context: Context) = prefs(context).contains("steps_raw")

        private fun openApp(context: Context): PendingIntent? {
            val open = context.packageManager.getLaunchIntentForPackage(context.packageName)
                ?.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP) ?: return null
            return PendingIntent.getActivity(context, 0, open, PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT)
        }

        private data class Snapshot(val steps: String, val goal: String, val streak: String, val percent: Int, val pending: String, val today: Boolean)

        private fun snapshot(context: Context): Snapshot {
            val p = prefs(context)
            val today = p.getString("date", null) == LocalDate.now().toString()
            if (!signedIn(context)) return Snapshot("—", context.getString(R.string.widget_sign_in), "", 0, "", true)
            if (!today) return Snapshot(fa.format(0), context.getString(R.string.widget_open_to_sync), "", 0, "", false)
            val extra = pending(context)?.takeIf { it > 0 }
            return Snapshot(
                steps = fa.format(p.getInt("steps_raw", 0)),
                goal = context.getString(R.string.widget_goal, fa.format(p.getInt("goal_raw", 0))),
                streak = p.getString("streak", "") ?: "",
                percent = p.getInt("percent", 0),
                pending = if (extra == null) "" else context.getString(R.string.widget_pending, fa.format(extra)),
                today = true,
            )
        }

        fun render(context: Context) {
            val s = snapshot(context)
            val manager = AppWidgetManager.getInstance(context)
            val open = openApp(context)

            val big = manager.getAppWidgetIds(ComponentName(context, StepWidget::class.java))
            if (big.isNotEmpty()) {
                manager.updateAppWidget(big, RemoteViews(context.packageName, R.layout.step_widget).apply {
                    setTextViewText(R.id.widget_steps, s.steps)
                    setTextViewText(R.id.widget_goal, s.goal)
                    setTextViewText(R.id.widget_streak, s.streak)
                    setTextViewText(R.id.widget_pending, s.pending)
                    setProgressBar(R.id.widget_progress, 100, s.percent, false)
                    open?.let { setOnClickPendingIntent(R.id.widget_root, it) }
                })
            }
            val small = manager.getAppWidgetIds(ComponentName(context, StepWidgetSmall::class.java))
            if (small.isNotEmpty()) {
                manager.updateAppWidget(small, RemoteViews(context.packageName, R.layout.step_widget_small).apply {
                    setTextViewText(R.id.widget_steps, s.steps)
                    setProgressBar(R.id.widget_progress, 100, s.percent, false)
                    open?.let { setOnClickPendingIntent(R.id.widget_root, it) }
                })
            }
            notifyLockScreen(context, s, open)
        }

        /**
         * Phones don't allow third-party lock-screen widgets, so "today" appears on the lock
         * screen as a quiet, low-priority ongoing notification (switchable in the app and in
         * system settings).
         */
        private fun notifyLockScreen(context: Context, s: Snapshot, open: PendingIntent?) {
            val nm = NotificationManagerCompat.from(context)
            val enabled = prefs(context).getBoolean("lockscreen", true)
            val allowed = Build.VERSION.SDK_INT < 33 ||
                ContextCompat.checkSelfPermission(context, Manifest.permission.POST_NOTIFICATIONS) == PackageManager.PERMISSION_GRANTED
            if (!enabled || !signedIn(context) || !allowed) {
                nm.cancel(NOTIFICATION_ID)
                return
            }
            if (Build.VERSION.SDK_INT >= 26) {
                val manager = context.getSystemService(NotificationManager::class.java)
                if (manager.getNotificationChannel(NOTIFICATION_CHANNEL) == null) {
                    manager.createNotificationChannel(NotificationChannel(NOTIFICATION_CHANNEL, context.getString(R.string.widget_channel_name), NotificationManager.IMPORTANCE_LOW).apply {
                        description = context.getString(R.string.widget_channel_description)
                        lockscreenVisibility = android.app.Notification.VISIBILITY_PUBLIC
                        setShowBadge(false)
                    })
                }
            }
            val title = context.getString(R.string.widget_notification_title, s.steps)
            val text = listOf(s.goal, s.streak, s.pending).filter { it.isNotBlank() }.joinToString(" · ")
            // Custom body so the collapsed view (what the lock screen shows) keeps goal and progress.
            val body = RemoteViews(context.packageName, R.layout.notification_today).apply {
                setTextViewText(R.id.n_steps, s.steps)
                setTextViewText(R.id.n_goal, if (s.today) s.goal else context.getString(R.string.widget_open_to_sync))
                setTextViewText(R.id.n_streak, s.streak)
                setTextViewText(R.id.n_pending, s.pending)
                setViewVisibility(R.id.n_pending, if (s.pending.isEmpty()) android.view.View.GONE else android.view.View.VISIBLE)
                setProgressBar(R.id.n_progress, 100, s.percent, false)
            }
            val notification = NotificationCompat.Builder(context, NOTIFICATION_CHANNEL)
                .setSmallIcon(R.drawable.ic_stat_gamyar)
                .setColor(0xFF1A7F4B.toInt())
                .setContentTitle(title)
                .setContentText(text)
                .setStyle(NotificationCompat.DecoratedCustomViewStyle())
                .setCustomContentView(body)
                .setCustomBigContentView(body)
                .setOngoing(true)
                .setOnlyAlertOnce(true)
                .setSilent(true)
                .setShowWhen(false)
                .setVisibility(NotificationCompat.VISIBILITY_PUBLIC)
                .setPriority(NotificationCompat.PRIORITY_LOW)
                .setCategory(NotificationCompat.CATEGORY_STATUS)
                .apply { open?.let { setContentIntent(it) } }
                .build()
            try {
                nm.notify(NOTIFICATION_ID, notification)
            } catch (e: SecurityException) {
                // Permission revoked between the check and the call.
            }
        }
    }

    override fun onUpdate(context: Context, manager: AppWidgetManager, ids: IntArray) = render(context)

    /** Dart → native: store the snapshot (with the counter it corresponds to) and redraw. */
    class Channel(private val context: Context) : MethodChannel.MethodCallHandler {
        override fun onMethodCall(call: MethodCall, result: MethodChannel.Result) {
            when (call.method) {
                "update" -> {
                    val last = StepStore.get(context).lastReading()
                    prefs(context).edit()
                        .putInt("steps_raw", call.argument<Int>("steps") ?: 0)
                        .putInt("goal_raw", call.argument<Int>("goal") ?: 0)
                        .putString("streak", call.argument<String>("streak"))
                        .putInt("percent", call.argument<Int>("percent") ?: 0)
                        .putString("date", call.argument<String>("date") ?: LocalDate.now().toString())
                        .apply {
                            if (last != null) {
                                putLong("base_counter", (last["c"] as Number).toLong())
                                putInt("base_boot", (last["b"] as Number).toInt())
                            } else {
                                remove("base_counter")
                            }
                        }
                        .apply()
                    render(context)
                    result.success(true)
                }
                "lockscreen" -> {
                    prefs(context).edit().putBoolean("lockscreen", call.argument<Boolean>("enabled") ?: true).apply()
                    render(context)
                    result.success(true)
                }
                "lockscreenEnabled" -> result.success(prefs(context).getBoolean("lockscreen", true))
                "clear" -> {
                    val lock = prefs(context).getBoolean("lockscreen", true)
                    prefs(context).edit().clear().putBoolean("lockscreen", lock).apply()
                    render(context)
                    result.success(true)
                }
                else -> result.notImplemented()
            }
        }
    }
}

/** Compact 2×1 variant: steps and progress only. Rendered by [StepWidget.render]. */
class StepWidgetSmall : AppWidgetProvider() {
    override fun onUpdate(context: Context, manager: AppWidgetManager, ids: IntArray) = StepWidget.render(context)
}
