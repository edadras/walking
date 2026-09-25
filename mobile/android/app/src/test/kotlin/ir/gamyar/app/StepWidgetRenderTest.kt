package ir.gamyar.app

import android.appwidget.AppWidgetManager
import android.content.Context
import android.graphics.Bitmap
import android.graphics.Canvas
import android.graphics.Color
import android.view.View
import android.view.ViewGroup
import android.widget.FrameLayout
import android.widget.TextView
import androidx.test.core.app.ApplicationProvider
import ir.gamyar.app.steps.StepStore
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import org.robolectric.Shadows.shadowOf
import org.robolectric.annotation.Config
import org.robolectric.annotation.GraphicsMode
import java.io.File
import java.io.FileOutputStream
import java.time.LocalDate

/**
 * Drives the real widget code (Dart snapshot → StepWidget.render → AppWidgetManager) and
 * checks what the launcher would show. With -DwidgetShots=<dir> it also writes PNGs.
 */
@RunWith(RobolectricTestRunner::class)
@GraphicsMode(GraphicsMode.Mode.NATIVE)
@Config(sdk = [34], qualifiers = "fa-rIR-w411dp-h891dp-xxhdpi")
class StepWidgetRenderTest {
    private val context: Context = ApplicationProvider.getApplicationContext()
    private val manager = shadowOf(AppWidgetManager.getInstance(context))

    @Before
    fun reset() {
        context.getSharedPreferences("gamyar_widget", Context.MODE_PRIVATE).edit().clear().commit()
    }

    /** What Dart sends after the home screen loads (HomeWidgetBridge.update). */
    private fun snapshot(steps: Int, goal: Int, streak: String, counterNow: Long, counterAtSync: Long) {
        StepStore.get(context).addReading(System.currentTimeMillis() - 60_000, counterAtSync, 1)
        StepWidget.Channel(context).onMethodCall(
            io.flutter.plugin.common.MethodCall("update", mapOf(
                "steps" to steps, "goal" to goal, "streak" to streak,
                "percent" to (steps * 100 / goal).coerceAtMost(100), "date" to LocalDate.now().toString(),
                "distance_m" to 4800, "kcal" to 210,
            )),
            NoopResult,
        )
        // Background worker reads the counter again later.
        StepStore.get(context).addReading(System.currentTimeMillis(), counterNow, 1)
        StepWidget.render(context)
    }

    private fun text(view: View, id: Int) = view.findViewById<TextView>(id).text.toString()

    /** What Dart sends after fetching weather (HomeWidgetBridge.updateWeather). */
    private fun weather(icon: String = "clear", day: Boolean = true, ageMinutes: Long = 5, advice: String = "هوا خشک است؛ کمی بیشتر از همیشه آب بنوشید.") {
        val hours = org.json.JSONArray()
        listOf(Triple(17, "clear", 31.8), Triple(18, "partly_cloudy", 29.5), Triple(19, "clear_night", 26.4), Triple(20, "rain", 25.1), Triple(21, "thunder", 24.3))
            .forEach { (h, i, t) -> hours.put(org.json.JSONObject().put("h", h).put("icon", i).put("t", t)) }
        val json = org.json.JSONObject()
            .put("t", 33.2).put("feels", 29.5).put("hi", 34.1).put("lo", 21.7).put("cond", if (icon == "rain") "باران" else "صاف")
            .put("icon", icon).put("day", day).put("hum", 10).put("wind", 8.3).put("uv", 1.5).put("aqi", 93)
            .put("index", 100).put("advice", advice).put("at", System.currentTimeMillis() - ageMinutes * 60_000).put("hours", hours)
        StepWidget.Channel(context).onMethodCall(io.flutter.plugin.common.MethodCall("weather", mapOf("json" to json.toString())), NoopResult)
    }

    @Test
    fun big_widget_shows_verified_steps_and_pending_separately() {
        val id = manager.createWidget(StepWidget::class.java, R.layout.step_widget)
        snapshot(steps = 6420, goal = 7500, streak = "🔥 ۵ روز", counterNow = 101_230, counterAtSync = 100_000)
        val view = manager.getViewFor(id)
        assertEquals("۶٬۴۲۰", text(view, R.id.widget_steps))
        assertEquals("هدف: ۷٬۵۰۰ قدم", text(view, R.id.widget_goal))
        assertTrue(text(view, R.id.widget_pending).startsWith("+۱٬۲۳۰"))
        assertEquals("۸۵٪", text(view, R.id.widget_percent))
        assertEquals("۴٫۸ کیلومتر، ۲۱۰ کالری", text(view, R.id.widget_stats))
        assertEquals("", text(view, R.id.w_temp)) // no weather yet: nothing invented
        weather()
        assertEquals("۳۳°", text(manager.getViewFor(id), R.id.w_temp))
        shot(manager.getViewFor(id), "widget-3x2", 260, 170)
    }

    @Test
    fun small_widget() {
        val id = manager.createWidget(StepWidgetSmall::class.java, R.layout.step_widget_small)
        snapshot(steps = 6420, goal = 7500, streak = "", counterNow = 100_000, counterAtSync = 100_000)
        val view = manager.getViewFor(id)
        assertEquals("۶٬۴۲۰", text(view, R.id.widget_steps))
        weather()
        assertEquals("قدم امروز، ۳۳°", text(manager.getViewFor(id), R.id.widget_sub))
        shot(manager.getViewFor(id), "widget-2x1", 180, 70)
    }

    @Test
    fun signed_out_and_new_day() {
        val id = manager.createWidget(StepWidget::class.java, R.layout.step_widget)
        StepWidget.render(context)
        assertEquals("برای دیدن قدم‌ها وارد گام‌یار شو", text(manager.getViewFor(id), R.id.widget_goal))
        shot(manager.getViewFor(id), "widget-3x2-signed-out", 260, 170)

        snapshot(steps = 9000, goal = 7500, streak = "", counterNow = 5, counterAtSync = 1)
        context.getSharedPreferences("gamyar_widget", Context.MODE_PRIVATE).edit().putString("date", LocalDate.now().minusDays(1).toString()).commit()
        StepWidget.render(context)
        assertEquals("روز جدید؛ اپ را باز کن تا همگام شود", text(manager.getViewFor(id), R.id.widget_goal))
    }

    @Test
    fun weather_widget_shows_now_the_next_hours_air_and_advice() {
        val id = manager.createWidget(WeatherWidget::class.java, R.layout.weather_widget)
        snapshot(steps = 6420, goal = 7500, streak = "", counterNow = 100_000, counterAtSync = 100_000)
        // Signed in but weather never fetched: says so instead of guessing.
        assertEquals("برای آب‌وهوای محل، گام‌یار را باز کن", text(manager.getViewFor(id), R.id.w_cond))
        shot(manager.getViewFor(id), "weather-widget-empty", 340, 190)

        weather()
        val view = manager.getViewFor(id)
        assertEquals("۳۳°", text(view, R.id.w_temp))
        assertEquals("صاف", text(view, R.id.w_cond))
        assertEquals("احساس ۳۰°، بیشینه ۳۴°، کمینه ۲۲°", text(view, R.id.w_feels))
        assertEquals("رطوبت ۱۰٪", text(view, R.id.w_hum))
        assertEquals("هوا ۹۳", text(view, R.id.w_aqi))
        assertEquals("۱۸", text(view, R.id.h1_time))
        assertEquals("۲۵°", text(view, R.id.h3_temp))
        assertEquals("۶٬۴۲۰ قدم امروز، ۸۵٪ از هدف", text(view, R.id.w_steps))
        assertTrue(text(view, R.id.w_updated).startsWith("به‌روز:"))
        shot(view, "weather-widget", 340, 190)

        weather(icon = "rain", day = false, ageMinutes = 240, advice = "در حال بارش است؛ کفش مناسب و چتر همراه داشته باشید.")
        assertTrue(text(manager.getViewFor(id), R.id.w_updated).endsWith("(قدیمی)"))
        shot(manager.getViewFor(id), "weather-widget-night-rain", 340, 190)
    }

    @Test
    fun weather_icons_draw_every_condition() {
        for (key in listOf("clear", "clear_night", "mostly_clear", "partly_cloudy", "partly_cloudy_night", "cloudy", "fog", "drizzle", "rain", "snow", "thunder")) {
            val bmp = WidgetArt.weather(context, key, 24f)
            var painted = 0
            for (x in 0 until bmp.width step 2) for (y in 0 until bmp.height step 2) if (Color.alpha(bmp.getPixel(x, y)) > 0) painted++
            assertTrue("$key draws something", painted > 10)
        }
    }

    @Test
    fun lock_screen_notification_is_quiet_public_and_switchable() {
        shadowOf(context as android.app.Application).grantPermissions(android.Manifest.permission.POST_NOTIFICATIONS)
        snapshot(steps = 6420, goal = 7500, streak = "🔥 ۵ روز", counterNow = 101_230, counterAtSync = 100_000)
        val nm = context.getSystemService(android.app.NotificationManager::class.java)
        val posted = shadowOf(nm).allNotifications.single()
        assertEquals(android.app.Notification.VISIBILITY_PUBLIC, posted.visibility)
        assertTrue(posted.flags and android.app.Notification.FLAG_ONGOING_EVENT != 0)
        assertEquals(android.app.NotificationManager.IMPORTANCE_LOW, nm.getNotificationChannel("daily_progress").importance)
        val content = android.app.Notification.Builder.recoverBuilder(context, posted).createContentView()
        val view = content.apply(context, FrameLayout(context))
        shot(view, "lockscreen-notification", 360, 104, lockScreen = true)
        assertEquals("۶٬۴۲۰", text(view, R.id.n_steps))
        assertEquals(R.drawable.ic_stat_gamyar, posted.smallIcon.resId)

        StepWidget.Channel(context).onMethodCall(io.flutter.plugin.common.MethodCall("lockscreen", mapOf("enabled" to false)), NoopResult)
        assertTrue(shadowOf(nm).allNotifications.isEmpty())
    }

    /** The launcher icon (adaptive, with the brand colours), also usable as the 512px store icon. */
    @Test
    fun launcher_icon_is_the_brand_icon() {
        val dir = System.getProperty("widgetShots") ?: return
        val icon = context.getDrawable(R.mipmap.ic_launcher)!!
        assertTrue(icon is android.graphics.drawable.AdaptiveIconDrawable)
        for ((name, size, circle) in listOf(Triple("icon-512", 512, false), Triple("icon-round-192", 192, true))) {
            val bitmap = Bitmap.createBitmap(size, size, Bitmap.Config.ARGB_8888)
            val canvas = Canvas(bitmap)
            val adaptive = icon as android.graphics.drawable.AdaptiveIconDrawable
            val path = android.graphics.Path().apply {
                if (circle) addCircle(size / 2f, size / 2f, size / 2f, android.graphics.Path.Direction.CW)
                else addRoundRect(0f, 0f, size.toFloat(), size.toFloat(), size * 0.22f, size * 0.22f, android.graphics.Path.Direction.CW)
            }
            canvas.clipPath(path)
            // Layers are 108dp with the visible 72dp in the middle: scale so the safe area fills the tile.
            for (layer in listOf(adaptive.background, adaptive.foreground)) {
                val pad = (size * 0.25f).toInt()
                layer.setBounds(-pad, -pad, size + pad, size + pad)
                layer.draw(canvas)
            }
            File(dir).mkdirs()
            FileOutputStream(File(dir, "$name.png")).use { bitmap.compress(Bitmap.CompressFormat.PNG, 100, it) }
        }
    }

    /** Draws the widget on a wallpaper-like background, at the size a launcher cell would give it. */
    private fun shot(view: View, name: String, widthDp: Int, heightDp: Int, lockScreen: Boolean = false) {
        val dir = System.getProperty("widgetShots") ?: return
        val d = context.resources.displayMetrics.density
        val frame = FrameLayout(context).apply {
            setBackgroundColor(Color.parseColor(if (lockScreen) "#FF1B2430" else "#FF3F6E8C"))
            setPadding((16 * d).toInt(), (16 * d).toInt(), (16 * d).toInt(), (16 * d).toInt())
            (view.parent as? ViewGroup)?.removeView(view)
            val card = if (!lockScreen) view else FrameLayout(context).apply {
                // The system draws notifications on a rounded surface; approximate it.
                background = android.graphics.drawable.GradientDrawable().apply { setColor(Color.WHITE); cornerRadius = 20 * d }
                setPadding((4 * d).toInt(), (4 * d).toInt(), (4 * d).toInt(), (4 * d).toInt())
                addView(view)
            }
            addView(card, FrameLayout.LayoutParams((widthDp * d).toInt(), (heightDp * d).toInt()))
        }
        val w = ((widthDp + 32) * d).toInt()
        val h = ((heightDp + 32) * d).toInt()
        frame.measure(View.MeasureSpec.makeMeasureSpec(w, View.MeasureSpec.EXACTLY), View.MeasureSpec.makeMeasureSpec(h, View.MeasureSpec.EXACTLY))
        frame.layout(0, 0, w, h)
        val bitmap = Bitmap.createBitmap(w, h, Bitmap.Config.ARGB_8888)
        frame.draw(Canvas(bitmap))
        File(dir).mkdirs()
        FileOutputStream(File(dir, "$name.png")).use { bitmap.compress(Bitmap.CompressFormat.PNG, 100, it) }
    }

    private object NoopResult : io.flutter.plugin.common.MethodChannel.Result {
        override fun success(result: Any?) {}
        override fun error(errorCode: String, errorMessage: String?, errorDetails: Any?) {}
        override fun notImplemented() {}
    }
}
