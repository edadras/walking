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
            )),
            NoopResult,
        )
        // Background worker reads the counter again later.
        StepStore.get(context).addReading(System.currentTimeMillis(), counterNow, 1)
        StepWidget.render(context)
    }

    private fun text(view: View, id: Int) = view.findViewById<TextView>(id).text.toString()

    @Test
    fun big_widget_shows_verified_steps_and_pending_separately() {
        val id = manager.createWidget(StepWidget::class.java, R.layout.step_widget)
        snapshot(steps = 6420, goal = 7500, streak = "🔥 ۵ روز", counterNow = 101_230, counterAtSync = 100_000)
        val view = manager.getViewFor(id)
        assertEquals("۶٬۴۲۰", text(view, R.id.widget_steps))
        assertEquals("هدف: ۷٬۵۰۰ قدم", text(view, R.id.widget_goal))
        assertTrue(text(view, R.id.widget_pending).startsWith("+۱٬۲۳۰"))
        shot(view, "widget-3x2", 250, 160)
    }

    @Test
    fun small_widget() {
        val id = manager.createWidget(StepWidgetSmall::class.java, R.layout.step_widget_small)
        snapshot(steps = 6420, goal = 7500, streak = "", counterNow = 100_000, counterAtSync = 100_000)
        val view = manager.getViewFor(id)
        assertEquals("۶٬۴۲۰", text(view, R.id.widget_steps))
        shot(view, "widget-2x1", 170, 64)
    }

    @Test
    fun signed_out_and_new_day() {
        val id = manager.createWidget(StepWidget::class.java, R.layout.step_widget)
        StepWidget.render(context)
        assertEquals("برای دیدن قدم‌ها وارد گام‌یار شو", text(manager.getViewFor(id), R.id.widget_goal))
        shot(manager.getViewFor(id), "widget-3x2-signed-out", 250, 160)

        snapshot(steps = 9000, goal = 7500, streak = "", counterNow = 5, counterAtSync = 1)
        context.getSharedPreferences("gamyar_widget", Context.MODE_PRIVATE).edit().putString("date", LocalDate.now().minusDays(1).toString()).commit()
        StepWidget.render(context)
        assertEquals("روز جدید؛ اپ را باز کن تا همگام شود", text(manager.getViewFor(id), R.id.widget_goal))
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
