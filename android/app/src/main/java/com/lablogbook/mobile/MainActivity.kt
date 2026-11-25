package com.lablogbook.mobile

import android.app.Activity
import android.app.DownloadManager
import android.content.Context
import android.net.Uri
import android.os.Bundle
import android.os.Message
import android.view.View
import android.webkit.CookieManager
import android.webkit.DownloadListener
import android.webkit.SslErrorHandler
import android.net.http.SslError
import android.webkit.WebChromeClient
import android.webkit.WebResourceRequest
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.EditText
import android.widget.LinearLayout
import android.widget.Button
import android.widget.Toast
import android.app.AlertDialog
import android.content.Intent
import android.provider.Settings

class MainActivity : Activity() {
    private lateinit var web: WebView
    private val prefs by lazy { getSharedPreferences("lab-logbook", Context.MODE_PRIVATE) }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        web = WebView(this)
        setContentView(web)
        web.settings.javaScriptEnabled = true
        web.settings.domStorageEnabled = true
        web.settings.databaseEnabled = true
        web.settings.loadsImagesAutomatically = true
        web.settings.javaScriptCanOpenWindowsAutomatically = true
        web.settings.setSupportMultipleWindows(true)
        web.settings.allowFileAccess = false
        web.settings.allowContentAccess = true
        CookieManager.getInstance().setAcceptCookie(true)
        CookieManager.getInstance().setAcceptThirdPartyCookies(web, false)

        web.webViewClient = object : WebViewClient() {
            override fun shouldOverrideUrlLoading(view: WebView?, request: WebResourceRequest?): Boolean {
                val uri = request?.url ?: return false
                return if (uri.scheme == "http" || uri.scheme == "https") false else {
                    try { startActivity(Intent(Intent.ACTION_VIEW, uri)) } catch (_: Exception) {}
                    true
                }
            }
            override fun onReceivedSslError(view: WebView?, handler: SslErrorHandler?, error: SslError?) {
                handler?.cancel() // Never bypass certificate validation.
            }
        }
        web.webChromeClient = object : WebChromeClient() {
            override fun onCreateWindow(view: WebView?, isDialog: Boolean, isUserGesture: Boolean, resultMsg: Message?): Boolean {
                val transport = resultMsg?.obj as? WebView.WebViewTransport ?: return false
                transport.webView = web
                resultMsg.sendToTarget()
                return true
            }
        }
        web.setDownloadListener(DownloadListener { url, userAgent, contentDisposition, mimeType, _ ->
            try {
                val request = DownloadManager.Request(Uri.parse(url))
                    .addRequestHeader("Cookie", CookieManager.getInstance().getCookie(url) ?: "")
                    .addRequestHeader("User-Agent", userAgent)
                    .setMimeType(mimeType)
                    .setTitle("Lab_Logbook - unduhan")
                    .setDescription("Mengunduh laporan atau file")
                    .setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED)
                    .setDestinationInExternalPublicDir(android.os.Environment.DIRECTORY_DOWNLOADS, URLUtilName.fileName(contentDisposition, url))
                (getSystemService(DOWNLOAD_SERVICE) as DownloadManager).enqueue(request)
                Toast.makeText(this, "Unduhan dimulai. Periksa folder Download.", Toast.LENGTH_LONG).show()
            } catch (e: Exception) {
                Toast.makeText(this, "Unduhan gagal: ${e.message}", Toast.LENGTH_LONG).show()
            }
        })
        web.setOnLongClickListener {
            val hit = web.hitTestResult
            if (hit.type == WebView.HitTestResult.SRC_ANCHOR_TYPE || hit.type == WebView.HitTestResult.SRC_IMAGE_ANCHOR_TYPE) {
                val url = hit.extra
                if (!url.isNullOrBlank()) {
                    AlertDialog.Builder(this).setItems(arrayOf("Buka tautan", "Salin alamat")) { _, which ->
                        if (which == 0) web.loadUrl(url)
                        else { val clip = getSystemService(CLIPBOARD_SERVICE) as android.content.ClipboardManager; clip.setPrimaryClip(android.content.ClipData.newPlainText("URL", url)) }
                    }.show()
                    true
                } else false
            } else false
        }
        val configured = prefs.getString("server_url", null)
        if (configured.isNullOrBlank()) showServerDialog() else web.loadUrl(configured)
    }

    private fun showServerDialog() {
        val input = EditText(this)
        input.hint = "https://domain-lab-anda.example"
        input.setText(prefs.getString("server_url", ""))
        AlertDialog.Builder(this)
            .setTitle("Alamat server Lab_Logbook")
            .setMessage("Masukkan URL dashboard PHP yang sudah berjalan di server. Gunakan HTTPS.")
            .setView(input)
            .setCancelable(false)
            .setPositiveButton("Simpan") { _, _ ->
                var url = input.text.toString().trim().trimEnd('/')
                if (!url.startsWith("https://")) {
                    Toast.makeText(this, "Gunakan alamat HTTPS yang valid.", Toast.LENGTH_LONG).show()
                    showServerDialog()
                } else {
                    prefs.edit().putString("server_url", url).apply()
                    web.loadUrl(url)
                }
            }
            .setNeutralButton("Bantuan") { _, _ ->
                Toast.makeText(this, "URL adalah alamat website Lab_Logbook di Cloudflare/server Anda.", Toast.LENGTH_LONG).show()
                showServerDialog()
            }.show()
    }

    override fun onBackPressed() {
        if (::web.isInitialized && web.canGoBack()) web.goBack() else super.onBackPressed()
    }
}

private object URLUtilName {
    fun fileName(contentDisposition: String?, url: String): String {
        val fromHeader = android.webkit.URLUtil.guessFileName(url, contentDisposition, null)
        return fromHeader.ifBlank { "lab-logbook-download" }
    }
}
