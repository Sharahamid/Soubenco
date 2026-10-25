package com.soubenco.app

import android.Manifest
import android.annotation.SuppressLint
import android.content.ActivityNotFoundException
import android.content.Intent
import android.content.pm.PackageManager
import android.graphics.Bitmap
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.view.View
import android.webkit.CookieManager
import android.webkit.JavascriptInterface
import android.webkit.ValueCallback
import android.webkit.WebChromeClient
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.Button
import android.widget.ProgressBar
import androidx.activity.OnBackPressedCallback
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import androidx.core.splashscreen.SplashScreen.Companion.installSplashScreen
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout

class MainActivity : AppCompatActivity() {

    companion object {
        const val HOME = "https://soubenco.com/"
        /** The website recognises this marker and shows app-only prices. */
        const val APP_MARKER = " SoubencoApp/1.0 (Android)"
        private val IN_APP_HOSTS = listOf("soubenco.com", "www.soubenco.com")
        /** Payment pages must stay inside the app so customers return to Soubenco after paying. */
        private val PAYMENT_HOSTS = listOf("aamarpay.com", "sandbox.aamarpay.com", "secure.aamarpay.com", "sslcommerz.com", "bkash.com", "nagad.com.bd")
    }

    private lateinit var web: WebView
    private lateinit var swipe: SwipeRefreshLayout
    private lateinit var progress: ProgressBar
    private lateinit var offline: View
    private var fileCallback: ValueCallback<Array<Uri>>? = null
    private var ready = false

    private val pickFile = registerForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        fileCallback?.onReceiveValue(if (uri != null) arrayOf(uri) else null)
        fileCallback = null
    }

    private val askNotify = registerForActivityResult(ActivityResultContracts.RequestPermission()) { }

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        val splash = installSplashScreen()
        super.onCreate(savedInstanceState)
        splash.setKeepOnScreenCondition { !ready }
        setContentView(R.layout.activity_main)

        web = findViewById(R.id.web)
        swipe = findViewById(R.id.swipe)
        progress = findViewById(R.id.progress)
        offline = findViewById(R.id.offline)
        findViewById<Button>(R.id.retry).setOnClickListener { reload() }

        CookieManager.getInstance().apply {
            setAcceptCookie(true)
            setAcceptThirdPartyCookies(web, true)
        }

        with(web.settings) {
            javaScriptEnabled = true
            domStorageEnabled = true
            databaseEnabled = true
            loadWithOverviewMode = true
            useWideViewPort = true
            mediaPlaybackRequiresUserGesture = false
            cacheMode = WebSettings.LOAD_DEFAULT
            setSupportZoom(false)
            userAgentString = userAgentString + APP_MARKER
        }
        web.addJavascriptInterface(Bridge(), "SoubencoAndroid")

        web.webViewClient = object : WebViewClient() {
            override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean =
                handleUrl(request.url)

            override fun onPageStarted(view: WebView, url: String?, favicon: Bitmap?) {
                progress.visibility = View.VISIBLE
            }

            override fun onPageFinished(view: WebView, url: String?) {
                ready = true
                progress.visibility = View.GONE
                swipe.isRefreshing = false
                CookieManager.getInstance().flush()
            }

            override fun onReceivedError(view: WebView, request: WebResourceRequest, error: WebResourceError) {
                if (request.isForMainFrame) showOffline()
            }
        }

        web.webChromeClient = object : WebChromeClient() {
            override fun onProgressChanged(view: WebView, newProgress: Int) {
                progress.progress = newProgress
                if (newProgress > 60) ready = true
            }

            override fun onShowFileChooser(
                webView: WebView, callback: ValueCallback<Array<Uri>>, params: FileChooserParams
            ): Boolean {
                fileCallback?.onReceiveValue(null)
                fileCallback = callback
                return try {
                    pickFile.launch("image/*"); true
                } catch (e: Exception) {
                    fileCallback = null; false
                }
            }
        }

        // Only allow pull-to-refresh when the page is scrolled to the very top.
        swipe.setColorSchemeColors(ContextCompat.getColor(this, R.color.brand_violet))
        swipe.setOnChildScrollUpCallback { _, _ -> web.scrollY > 0 }
        swipe.setOnRefreshListener { reload() }

        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                if (web.canGoBack()) web.goBack() else finish()
            }
        })

        val start = intent?.data?.takeIf { isInApp(it) }?.toString() ?: intent?.getStringExtra("open_url") ?: HOME
        if (savedInstanceState != null) web.restoreState(savedInstanceState) else load(start)

        NotifyWorker.schedule(this)
        if (Build.VERSION.SDK_INT >= 33 &&
            ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED
        ) {
            askNotify.launch(Manifest.permission.POST_NOTIFICATIONS)
        }
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        val url = intent.data?.takeIf { isInApp(it) }?.toString() ?: intent.getStringExtra("open_url")
        if (url != null) load(url)
    }

    override fun onSaveInstanceState(outState: Bundle) {
        super.onSaveInstanceState(outState)
        web.saveState(outState)
    }

    override fun onPause() {
        super.onPause()
        CookieManager.getInstance().flush()
    }

    private fun isInApp(uri: Uri): Boolean {
        val host = uri.host?.lowercase() ?: return false
        return IN_APP_HOSTS.contains(host)
    }

    private fun isPayment(uri: Uri): Boolean {
        val host = uri.host?.lowercase() ?: return false
        return PAYMENT_HOSTS.any { host == it || host.endsWith(".$it") }
    }

    /** Soubenco and payment pages open inside the app; phone, WhatsApp, maps and other sites open outside. */
    private fun handleUrl(uri: Uri): Boolean {
        val scheme = uri.scheme?.lowercase()
        if ((scheme == "https" || scheme == "http") && (isInApp(uri) || isPayment(uri))) return false
        return try {
            startActivity(Intent(Intent.ACTION_VIEW, uri)); true
        } catch (e: ActivityNotFoundException) {
            true
        }
    }

    private fun load(url: String) {
        if (!isOnline()) { showOffline(); return }
        offline.visibility = View.GONE
        web.visibility = View.VISIBLE
        web.loadUrl(url)
    }

    private fun reload() {
        if (!isOnline()) { swipe.isRefreshing = false; showOffline(); return }
        offline.visibility = View.GONE
        web.visibility = View.VISIBLE
        if (web.url.isNullOrEmpty()) web.loadUrl(HOME) else web.reload()
    }

    private fun showOffline() {
        ready = true
        swipe.isRefreshing = false
        progress.visibility = View.GONE
        web.visibility = View.INVISIBLE
        offline.visibility = View.VISIBLE
    }

    private fun isOnline(): Boolean {
        val cm = getSystemService(ConnectivityManager::class.java) ?: return true
        val caps = cm.getNetworkCapabilities(cm.activeNetwork) ?: return false
        return caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
    }

    /** Lets the website hand the app a private key for background price/stock alerts. */
    inner class Bridge {
        @JavascriptInterface
        fun setToken(token: String?) {
            val prefs = getSharedPreferences(NotifyWorker.PREFS, MODE_PRIVATE)
            val old = prefs.getString(NotifyWorker.KEY_TOKEN, "") ?: ""
            val now = token ?: ""
            if (old != now) prefs.edit().putString(NotifyWorker.KEY_TOKEN, now).putLong(NotifyWorker.KEY_LAST, -1L).apply()
        }
    }
}
