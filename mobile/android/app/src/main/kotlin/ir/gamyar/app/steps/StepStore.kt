package ir.gamyar.app.steps

import android.content.Context
import android.content.SharedPreferences
import org.json.JSONArray
import org.json.JSONObject

/**
 * Small append-only store for raw step-counter readings and activity
 * transitions, written by background components and drained by Dart.
 *
 * Readings are cumulative counter values (since boot) with the boot count, so
 * Dart can compute deltas and detect reboots. Dart acknowledges what it has
 * turned into sessions; the last reading is always kept as the next baseline.
 */
class StepStore private constructor(context: Context) {

    companion object {
        private const val PREFS = "gamyar_steps"
        private const val KEY_READINGS = "readings"
        private const val KEY_TRANSITIONS = "transitions"
        private const val MAX_READINGS = 2000 // ~20 days at one read / 15 min
        private const val MAX_TRANSITIONS = 1000

        const val MARK_NONE = ""
        const val MARK_ACTIVE_START = "active_start"
        const val MARK_ACTIVE_END = "active_end"

        @Volatile private var instance: StepStore? = null

        fun get(context: Context): StepStore =
            instance ?: synchronized(this) { instance ?: StepStore(context.applicationContext).also { instance = it } }
    }

    private val prefs: SharedPreferences = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)

    @Synchronized
    fun addReading(timeMs: Long, counter: Long, bootCount: Int, mark: String = MARK_NONE) {
        val list = load(KEY_READINGS)
        list.put(JSONObject().put("t", timeMs).put("c", counter).put("b", bootCount).put("m", mark))
        save(KEY_READINGS, trim(list, MAX_READINGS))
    }

    @Synchronized
    fun addTransition(timeMs: Long, type: String, enter: Boolean) {
        val list = load(KEY_TRANSITIONS)
        list.put(JSONObject().put("t", timeMs).put("type", type).put("enter", enter))
        save(KEY_TRANSITIONS, trim(list, MAX_TRANSITIONS))
    }

    @Synchronized
    fun readings(): List<Map<String, Any>> = toMaps(load(KEY_READINGS))

    @Synchronized
    fun transitions(): List<Map<String, Any>> = toMaps(load(KEY_TRANSITIONS))

    @Synchronized
    fun lastReading(): Map<String, Any>? = readings().lastOrNull()

    /** Removes readings strictly older than [beforeMs] (the new baseline stays). */
    @Synchronized
    fun ackReadings(beforeMs: Long) = save(KEY_READINGS, filter(load(KEY_READINGS)) { it.getLong("t") >= beforeMs })

    @Synchronized
    fun ackTransitions(beforeMs: Long) = save(KEY_TRANSITIONS, filter(load(KEY_TRANSITIONS)) { it.getLong("t") >= beforeMs })

    private fun load(key: String): JSONArray = try {
        JSONArray(prefs.getString(key, "[]"))
    } catch (e: Exception) {
        JSONArray()
    }

    private fun save(key: String, array: JSONArray) = prefs.edit().putString(key, array.toString()).apply()

    private fun trim(array: JSONArray, max: Int): JSONArray {
        if (array.length() <= max) return array
        val out = JSONArray()
        for (i in array.length() - max until array.length()) out.put(array.get(i))
        return out
    }

    private fun filter(array: JSONArray, keep: (JSONObject) -> Boolean): JSONArray {
        val out = JSONArray()
        for (i in 0 until array.length()) {
            val o = array.getJSONObject(i)
            if (keep(o)) out.put(o)
        }
        return out
    }

    private fun toMaps(array: JSONArray): List<Map<String, Any>> = (0 until array.length()).map { i ->
        val o = array.getJSONObject(i)
        o.keys().asSequence().associateWith { o.get(it) }
    }
}
