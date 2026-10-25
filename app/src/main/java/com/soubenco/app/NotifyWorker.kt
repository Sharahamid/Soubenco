package com.soubenco.app

import android.Manifest
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import androidx.work.Constraints
import androidx.work.CoroutineWorker
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.NetworkType
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.WorkerParameters
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL
import java.util.concurrent.TimeUnit

/**
 * Checks Soubenco about every 15 minutes for new alerts (price drops, restocks, order updates)
 * and shows them as phone notifications — even when the app is closed.
 */
class NotifyWorker(ctx: Context, params: WorkerParameters) : CoroutineWorker(ctx, params) {

    companion object {
        const val PREFS = "soubenco"
        const val KEY_TOKEN = "token"
        const val KEY_LAST = "last_id"
        private const val CHANNEL = "soubenco_alerts"

        fun schedule(ctx: Context) {
            val req = PeriodicWorkRequestBuilder<NotifyWorker>(15, TimeUnit.MINUTES)
                .setConstraints(Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build())
                .build()
            WorkManager.getInstance(ctx).enqueueUniquePeriodicWork("soubenco-alerts", ExistingPeriodicWorkPolicy.KEEP, req)
        }
    }

    override suspend fun doWork(): Result = withContext(Dispatchers.IO) {
        val prefs = applicationContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE)
        val token = prefs.getString(KEY_TOKEN, "") ?: ""
        if (token.isEmpty()) return@withContext Result.success()
        val last = prefs.getLong(KEY_LAST, -1L)

        try {
            val conn = (URL("https://soubenco.com/wp-json/sb/v1/app-feed?after=${maxOf(last, 0L)}").openConnection() as HttpURLConnection).apply {
                connectTimeout = 15000
                readTimeout = 15000
                setRequestProperty("X-SB-Token", token)
                setRequestProperty("User-Agent", "Soubenco-Android" + MainActivity.APP_MARKER)
            }
            if (conn.responseCode == 401) {
                prefs.edit().putString(KEY_TOKEN, "").apply()
                return@withContext Result.success()
            }
            if (conn.responseCode != 200) return@withContext Result.retry()
            val json = JSONObject(conn.inputStream.bufferedReader().use { it.readText() })
            val newest = json.optLong("last", 0L)

            // First check after signing in: just remember where we are, don't replay old alerts.
            if (last >= 0) {
                val items = json.optJSONArray("items")
                if (items != null) for (i in 0 until items.length()) {
                    val n = items.getJSONObject(i)
                    show(n.optInt("id"), n.optString("title"), n.optString("body"), n.optString("link"))
                }
            }
            prefs.edit().putLong(KEY_LAST, maxOf(newest, last, 0L)).apply()
            Result.success()
        } catch (e: Exception) {
            Result.retry()
        }
    }

    private fun show(id: Int, title: String, body: String, link: String) {
        val ctx = applicationContext
        if (Build.VERSION.SDK_INT >= 33 &&
            ContextCompat.checkSelfPermission(ctx, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED
        ) return
        if (Build.VERSION.SDK_INT >= 26) {
            val nm = ctx.getSystemService(NotificationManager::class.java)
            if (nm.getNotificationChannel(CHANNEL) == null) {
                nm.createNotificationChannel(
                    NotificationChannel(CHANNEL, "Price drops & order updates", NotificationManager.IMPORTANCE_DEFAULT).apply {
                        description = "Price drops, items back in stock and order updates from Soubenco"
                    }
                )
            }
        }
        val open = Intent(ctx, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
            putExtra("open_url", link.ifEmpty { MainActivity.HOME })
        }
        val pi = PendingIntent.getActivity(ctx, id, open, PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)
        val notification = NotificationCompat.Builder(ctx, CHANNEL)
            .setSmallIcon(R.drawable.ic_notify)
            .setColor(ContextCompat.getColor(ctx, R.color.brand_violet))
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(NotificationCompat.BigTextStyle().bigText(body))
            .setAutoCancel(true)
            .setContentIntent(pi)
            .build()
        try { NotificationManagerCompat.from(ctx).notify(id, notification) } catch (_: SecurityException) { }
    }
}
