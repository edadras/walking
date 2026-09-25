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
import android.view.View
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
import org.json.JSONObject

/**
 * Home-screen widgets (steps 3×2, compact 2×1, walking weather 4×2) and the lock-screen "today" notification.
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

        private data class Snapshot(
            val steps: String,
            val goal: String,
            val streak: String,
            val percent: Int,
            val pending: String,
            val today: Boolean,
            val stats: String = "",
        )

        private val fa1: NumberFormat = NumberFormat.getNumberInstance(Locale.forLanguageTag("fa-IR")).apply { maximumFractionDigits = 1; minimumFractionDigits = 0 }

        /** Last weather the app fetched (null = never, or signed out). */
        private fun weather(context: Context): JSONObject? = prefs(context).getString("weather", null)?.let {
            try { JSONObject(it) } catch (e: Exception) { null }
        }

        private fun temp(v: Double) = fa.format(Math.round(v)) + "°"

        private fun skyFor(icon: String, day: Boolean): Int = when {
            !day -> R.drawable.widget_sky_night
            icon == "cloudy" -> R.drawable.widget_sky_cloudy
            icon == "rain" || icon == "drizzle" -> R.drawable.widget_sky_rain
            icon == "thunder" -> R.drawable.widget_sky_storm
            icon == "snow" -> R.drawable.widget_sky_snow
            icon == "fog" -> R.drawable.widget_sky_fog
            else -> R.drawable.widget_sky_clear
        }

        private fun isStale(w: JSONObject) = System.currentTimeMillis() - w.optLong("at", 0) > 3 * 3600_000L

        private fun snapshot(context: Context): Snapshot {
            val p = prefs(context)
            val today = p.getString("date", null) == LocalDate.now().toString()
            if (!signedIn(context)) return Snapshot("—", context.getString(R.string.widget_sign_in), "", 0, "", true)
            if (!today) return Snapshot(fa.format(0), context.getString(R.string.widget_open_to_sync), "", 0, "", false)
            val extra = pending(context)?.takeIf { it > 0 }
            val km = p.getInt("distance_m", 0) / 1000.0
            val kcal = p.getInt("kcal", 0)
            return Snapshot(
                steps = fa.format(p.getInt("steps_raw", 0)),
                goal = context.getString(R.string.widget_goal, fa.format(p.getInt("goal_raw", 0))),
                streak = p.getString("streak", "") ?: "",
                percent = p.getInt("percent", 0),
                pending = if (extra == null) "" else context.getString(R.string.widget_pending, fa.format(extra)),
                today = true,
                stats = if (km <= 0 && kcal <= 0) "" else context.getString(R.string.widget_stats, fa1.format(km), fa.format(kcal)),
            )
        }

        fun render(context: Context) {
            val s = snapshot(context)
            val w = if (signedIn(context)) weather(context) else null
            val manager = AppWidgetManager.getInstance(context)
            val open = openApp(context)
            val percentText = fa.format(s.percent) + "٪"

            val big = manager.getAppWidgetIds(ComponentName(context, StepWidget::class.java))
            if (big.isNotEmpty()) {
                manager.updateAppWidget(big, RemoteViews(context.packageName, R.layout.step_widget).apply {
                    setImageViewBitmap(R.id.widget_ring, WidgetArt.ring(context, s.percent, 92f, 8f, 0x40FFFFFF, 0xFFFFFFFF.toInt()))
                    setTextViewText(R.id.widget_steps, s.steps)
                    setTextViewText(R.id.widget_percent, if (s.today && signedIn(context)) percentText else "")
                    setTextViewText(R.id.widget_goal, s.goal)
                    setTextViewText(R.id.widget_stats, s.stats)
                    setTextViewText(R.id.widget_streak, s.streak)
                    setTextViewText(R.id.widget_pending, s.pending)
                    if (w != null && !isStale(w)) {
                        setImageViewBitmap(R.id.w_icon, WidgetArt.weather(context, w.optString("icon"), 20f))
                        setTextViewText(R.id.w_temp, temp(w.optDouble("t")))
                        setViewVisibility(R.id.w_icon, View.VISIBLE)
                    } else {
                        setViewVisibility(R.id.w_icon, View.GONE)
                        setTextViewText(R.id.w_temp, "")
                    }
                    open?.let { setOnClickPendingIntent(R.id.widget_root, it) }
                })
            }
            val small = manager.getAppWidgetIds(ComponentName(context, StepWidgetSmall::class.java))
            if (small.isNotEmpty()) {
                manager.updateAppWidget(small, RemoteViews(context.packageName, R.layout.step_widget_small).apply {
                    setImageViewBitmap(R.id.widget_ring, WidgetArt.ring(context, s.percent, 46f, 5f, 0x40FFFFFF, 0xFFFFFFFF.toInt()))
                    setTextViewText(R.id.widget_percent, if (s.today && signedIn(context)) percentText else "")
                    setTextViewText(R.id.widget_steps, s.steps)
                    val weatherPart = if (w != null && !isStale(w)) "، " + temp(w.optDouble("t")) else ""
                    setTextViewText(R.id.widget_sub, if (signedIn(context)) context.getString(R.string.widget_steps_unit) + weatherPart else s.goal)
                    open?.let { setOnClickPendingIntent(R.id.widget_root, it) }
                })
            }
            val sky = manager.getAppWidgetIds(ComponentName(context, WeatherWidget::class.java))
            if (sky.isNotEmpty()) {
                manager.updateAppWidget(sky, weatherViews(context, w, s, percentText).apply {
                    open?.let { setOnClickPendingIntent(R.id.widget_root, it) }
                })
            }
            notifyLockScreen(context, s, open)
        }

        private val hourIds = listOf(
            Triple(R.id.h0_time, R.id.h0_icon, R.id.h0_temp),
            Triple(R.id.h1_time, R.id.h1_icon, R.id.h1_temp),
            Triple(R.id.h2_time, R.id.h2_icon, R.id.h2_temp),
            Triple(R.id.h3_time, R.id.h3_icon, R.id.h3_temp),
            Triple(R.id.h4_time, R.id.h4_icon, R.id.h4_temp),
        )

        /** The 4×2 walking-weather widget. Without a fetched report it says so instead of guessing. */
        private fun weatherViews(context: Context, w: JSONObject?, s: Snapshot, percentText: String) =
            RemoteViews(context.packageName, R.layout.weather_widget).apply {
                val signedIn = signedIn(context)
                setTextViewText(R.id.w_steps, if (signedIn && s.today) context.getString(R.string.weather_steps_line, s.steps, percentText) else "")
                if (w == null) {
                    setInt(R.id.widget_root, "setBackgroundResource", R.drawable.widget_bg_brand)
                    setViewVisibility(R.id.w_icon, View.GONE)
                    setTextViewText(R.id.w_temp, "—")
                    setTextViewText(R.id.w_cond, if (signedIn) context.getString(R.string.weather_open_app) else context.getString(R.string.widget_sign_in))
                    setTextViewText(R.id.w_feels, "")
                    setViewVisibility(R.id.w_index_box, View.GONE)
                    setViewVisibility(R.id.w_chips, View.GONE)
                    setViewVisibility(R.id.w_hours, View.INVISIBLE)
                    setTextViewText(R.id.w_advice, "")
                    setTextViewText(R.id.w_updated, "")
                    return@apply
                }
                val icon = w.optString("icon", "cloudy")
                setInt(R.id.widget_root, "setBackgroundResource", skyFor(icon, w.optBoolean("day", true)))
                setViewVisibility(R.id.w_icon, View.VISIBLE)
                setImageViewBitmap(R.id.w_icon, WidgetArt.weather(context, icon, 40f))
                setTextViewText(R.id.w_temp, temp(w.optDouble("t")))
                setTextViewText(R.id.w_cond, w.optString("cond"))
                setTextViewText(R.id.w_feels, context.getString(R.string.weather_feels, temp(w.optDouble("feels")), temp(w.optDouble("hi")), temp(w.optDouble("lo"))))
                setViewVisibility(R.id.w_index_box, View.VISIBLE)
                setTextViewText(R.id.w_index, fa.format(w.optInt("index")))
                setViewVisibility(R.id.w_chips, View.VISIBLE)
                setTextViewText(R.id.w_hum, context.getString(R.string.weather_chip_humidity, fa.format(w.optInt("hum"))))
                setTextViewText(R.id.w_wind, context.getString(R.string.weather_chip_wind, fa.format(Math.round(w.optDouble("wind")))))
                setTextViewText(R.id.w_uv, context.getString(R.string.weather_chip_uv, fa1.format(w.optDouble("uv"))))
                if (w.has("aqi")) {
                    setViewVisibility(R.id.w_aqi, View.VISIBLE)
                    setTextViewText(R.id.w_aqi, context.getString(R.string.weather_chip_air, fa.format(w.optInt("aqi"))))
                } else {
                    setViewVisibility(R.id.w_aqi, View.GONE)
                }
                val hours = w.optJSONArray("hours")
                setViewVisibility(R.id.w_hours, if (hours != null && hours.length() > 0) View.VISIBLE else View.INVISIBLE)
                hourIds.forEachIndexed { i, (time, img, t) ->
                    val h = hours?.optJSONObject(i)
                    if (h == null) {
                        setTextViewText(time, ""); setTextViewText(t, ""); setViewVisibility(img, View.INVISIBLE)
                    } else {
                        setTextViewText(time, fa.format(h.optInt("h")))
                        setViewVisibility(img, View.VISIBLE)
                        setImageViewBitmap(img, WidgetArt.weather(context, h.optString("icon"), 20f))
                        setTextViewText(t, temp(h.optDouble("t")))
                    }
                }
                setTextViewText(R.id.w_advice, w.optString("advice"))
                val at = java.time.Instant.ofEpochMilli(w.optLong("at")).atZone(java.time.ZoneId.systemDefault())
                val clock = fa.format(at.hour) + ":" + fa.format(at.minute).padStart(2, '۰')
                setTextViewText(R.id.w_updated, if (isStale(w)) context.getString(R.string.weather_stale, clock) else context.getString(R.string.weather_updated, clock))
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
            val text = listOf(s.goal, s.streak, s.pending).filter { it.isNotBlank() }.joinToString("، ")
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
                        .putInt("distance_m", call.argument<Int>("distance_m") ?: 0)
                        .putInt("kcal", call.argument<Int>("kcal") ?: 0)
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
                "weather" -> {
                    prefs(context).edit().putString("weather", call.argument<String>("json")).apply()
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

/** Compact 2×1 variant: goal ring, steps and temperature. Rendered by [StepWidget.render]. */
class StepWidgetSmall : AppWidgetProvider() {
    override fun onUpdate(context: Context, manager: AppWidgetManager, ids: IntArray) = StepWidget.render(context)
}

/** 4×2 walking weather: now, next hours, advice and today's steps. Rendered by [StepWidget.render]. */
class WeatherWidget : AppWidgetProvider() {
    override fun onUpdate(context: Context, manager: AppWidgetManager, ids: IntArray) = StepWidget.render(context)
}
