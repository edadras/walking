package ir.gamyar.app

import android.content.Context
import android.graphics.Bitmap
import android.graphics.Canvas
import android.graphics.Color
import android.graphics.Paint
import android.graphics.Path
import android.graphics.RectF
import kotlin.math.cos
import kotlin.math.min
import kotlin.math.sin

/**
 * Pictures for the widgets. RemoteViews can't draw shapes, so the progress ring and the
 * weather icons are drawn here into small bitmaps (a few KB each).
 */
object WidgetArt {

    private fun px(context: Context, dp: Float) = dp * context.resources.displayMetrics.density

    /** Goal ring: soft track, bright arc from the top, rounded ends; gold when the goal is met. */
    fun ring(context: Context, percent: Int, sizeDp: Float, strokeDp: Float, track: Int, progress: Int): Bitmap {
        val size = px(context, sizeDp).toInt().coerceAtLeast(1)
        val stroke = px(context, strokeDp)
        val bmp = Bitmap.createBitmap(size, size, Bitmap.Config.ARGB_8888)
        val c = Canvas(bmp)
        val box = RectF(stroke / 2, stroke / 2, size - stroke / 2, size - stroke / 2)
        val paint = Paint(Paint.ANTI_ALIAS_FLAG).apply { style = Paint.Style.STROKE; strokeWidth = stroke; strokeCap = Paint.Cap.ROUND }
        c.drawArc(box, 0f, 360f, false, paint.apply { color = track })
        val p = percent.coerceIn(0, 100)
        if (p > 0) {
            // RTL app: the ring fills counter-clockwise from the top, like the in-app ring.
            c.drawArc(box, -90f, -360f * p / 100f, false, paint.apply { color = if (p >= 100) 0xFFFFC53D.toInt() else progress })
        }
        return bmp
    }

    /** Weather glyphs matching the server's condition keys. */
    fun weather(context: Context, key: String, sizeDp: Float): Bitmap {
        val s = px(context, sizeDp).toInt().coerceAtLeast(1)
        val bmp = Bitmap.createBitmap(s, s, Bitmap.Config.ARGB_8888)
        val c = Canvas(bmp)
        val f = s.toFloat()
        val night = key.endsWith("_night")
        when (key.removeSuffix("_night")) {
            "clear" -> if (night) moon(c, f / 2, f / 2, f * 0.36f) else sun(c, f / 2, f / 2, f * 0.22f, f * 0.44f)
            "mostly_clear", "partly_cloudy" -> {
                if (night) moon(c, f * 0.62f, f * 0.36f, f * 0.24f) else sun(c, f * 0.62f, f * 0.36f, f * 0.15f, f * 0.30f)
                cloud(c, f * 0.08f, f * 0.42f, f * 0.78f, 0xFFF4F7FA.toInt())
            }
            "fog" -> {
                cloud(c, f * 0.1f, f * 0.12f, f * 0.8f, 0xFFE3E8EE.toInt())
                val p = Paint(Paint.ANTI_ALIAS_FLAG).apply { color = 0xCCFFFFFF.toInt(); strokeWidth = f * 0.07f; strokeCap = Paint.Cap.ROUND }
                for (i in 0..2) c.drawLine(f * (0.18f + i * 0.05f), f * (0.72f + i * 0.1f), f * (0.82f - i * 0.05f), f * (0.72f + i * 0.1f), p)
            }
            "drizzle", "rain" -> {
                cloud(c, f * 0.08f, f * 0.06f, f * 0.84f, 0xFFE3E8EE.toInt())
                val p = Paint(Paint.ANTI_ALIAS_FLAG).apply { color = 0xFF7CC4FF.toInt(); strokeWidth = f * 0.07f; strokeCap = Paint.Cap.ROUND }
                val n = if (key == "drizzle") 2 else 3
                for (i in 0 until n) {
                    val x = f * (0.3f + i * 0.2f)
                    c.drawLine(x, f * 0.7f, x - f * 0.07f, f * 0.9f, p)
                }
            }
            "snow" -> {
                cloud(c, f * 0.08f, f * 0.06f, f * 0.84f, 0xFFE3E8EE.toInt())
                val p = Paint(Paint.ANTI_ALIAS_FLAG).apply { color = Color.WHITE }
                for (i in 0..2) c.drawCircle(f * (0.3f + i * 0.2f), f * (0.8f - (i % 2) * 0.06f), f * 0.055f, p)
            }
            "thunder" -> {
                cloud(c, f * 0.08f, f * 0.04f, f * 0.84f, 0xFFB9C2CC.toInt())
                val bolt = Path().apply {
                    moveTo(f * 0.52f, f * 0.55f); lineTo(f * 0.36f, f * 0.78f); lineTo(f * 0.5f, f * 0.78f)
                    lineTo(f * 0.42f, f * 0.98f); lineTo(f * 0.66f, f * 0.7f); lineTo(f * 0.52f, f * 0.7f); close()
                }
                c.drawPath(bolt, Paint(Paint.ANTI_ALIAS_FLAG).apply { color = 0xFFFFC53D.toInt() })
            }
            else -> cloud(c, f * 0.06f, f * 0.2f, f * 0.88f, 0xFFF4F7FA.toInt())
        }
        return bmp
    }

    private fun sun(c: Canvas, cx: Float, cy: Float, r: Float, rays: Float) {
        val p = Paint(Paint.ANTI_ALIAS_FLAG).apply { color = 0xFFFFC53D.toInt(); strokeWidth = r * 0.32f; strokeCap = Paint.Cap.ROUND }
        for (i in 0 until 8) {
            val a = Math.PI / 4 * i
            c.drawLine(cx + (r * 1.35f) * cos(a).toFloat(), cy + (r * 1.35f) * sin(a).toFloat(), cx + rays * cos(a).toFloat(), cy + rays * sin(a).toFloat(), p)
        }
        c.drawCircle(cx, cy, r, p)
    }

    private fun moon(c: Canvas, cx: Float, cy: Float, r: Float) {
        val path = Path().apply {
            addCircle(cx, cy, r, Path.Direction.CW)
            op(Path().apply { addCircle(cx + r * 0.55f, cy - r * 0.35f, r * 0.85f, Path.Direction.CW) }, Path.Op.DIFFERENCE)
        }
        c.drawPath(path, Paint(Paint.ANTI_ALIAS_FLAG).apply { color = 0xFFFFE8A3.toInt() })
    }

    /** A cloud inside the box starting at (x, y) with the given width. */
    private fun cloud(c: Canvas, x: Float, y: Float, w: Float, color: Int) {
        val p = Paint(Paint.ANTI_ALIAS_FLAG).apply { this.color = color }
        val h = w * 0.62f
        val base = y + h
        c.drawRoundRect(RectF(x, base - h * 0.42f, x + w, base), h * 0.21f, h * 0.21f, p)
        c.drawCircle(x + w * 0.32f, base - h * 0.42f, min(w * 0.2f, h * 0.36f), p)
        c.drawCircle(x + w * 0.6f, base - h * 0.52f, min(w * 0.27f, h * 0.48f), p)
    }
}
