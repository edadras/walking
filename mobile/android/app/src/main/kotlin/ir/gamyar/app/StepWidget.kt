package ir.gamyar.app

import android.app.PendingIntent
import android.appwidget.AppWidgetManager
import android.appwidget.AppWidgetProvider
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.widget.RemoteViews
import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel

/**
 * Home-screen widget. It shows exactly what the app's home screen last showed
 * (server-verified numbers pushed from Dart), so the widget never disagrees
 * with the app; the texts arrive already formatted in Persian.
 */
class StepWidget : AppWidgetProvider() {
    companion object {
        const val CHANNEL = "ir.gamyar.app/widget"
        private const val PREFS = "gamyar_widget"

        fun render(context: Context) {
            val manager = AppWidgetManager.getInstance(context)
            val ids = manager.getAppWidgetIds(ComponentName(context, StepWidget::class.java))
            if (ids.isEmpty()) return
            val prefs = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            val views = RemoteViews(context.packageName, R.layout.step_widget).apply {
                setTextViewText(R.id.widget_steps, prefs.getString("steps", "—"))
                setTextViewText(R.id.widget_goal, prefs.getString("goal", ""))
                setTextViewText(R.id.widget_streak, prefs.getString("streak", ""))
                setProgressBar(R.id.widget_progress, 100, prefs.getInt("percent", 0), false)
                val open = context.packageManager.getLaunchIntentForPackage(context.packageName)
                    ?.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP)
                if (open != null) {
                    setOnClickPendingIntent(R.id.widget_root,
                        PendingIntent.getActivity(context, 0, open, PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT))
                }
            }
            manager.updateAppWidget(ids, views)
        }
    }

    override fun onUpdate(context: Context, manager: AppWidgetManager, ids: IntArray) = render(context)

    /** Dart → widget: store the snapshot and redraw. */
    class Channel(private val context: Context) : MethodChannel.MethodCallHandler {
        override fun onMethodCall(call: MethodCall, result: MethodChannel.Result) {
            when (call.method) {
                "update" -> {
                    context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit()
                        .putString("steps", call.argument<String>("steps"))
                        .putString("goal", call.argument<String>("goal"))
                        .putString("streak", call.argument<String>("streak"))
                        .putInt("percent", call.argument<Int>("percent") ?: 0)
                        .apply()
                    render(context)
                    result.success(true)
                }
                "clear" -> {
                    context.getSharedPreferences(PREFS, Context.MODE_PRIVATE).edit().clear().apply()
                    render(context)
                    result.success(true)
                }
                else -> result.notImplemented()
            }
        }
    }
}
