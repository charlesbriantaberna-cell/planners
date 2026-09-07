package com.kfjsdfs.studentplanner;

import android.annotation.SuppressLint;
import android.content.Intent;
import android.net.Uri;
import android.net.http.SslError;
import android.os.Bundle;
import android.view.View;
import android.webkit.CookieManager;
import android.webkit.SslErrorHandler;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Button;
import android.widget.LinearLayout;
import android.widget.ProgressBar;

import androidx.activity.OnBackPressedCallback;
import androidx.appcompat.app.AppCompatActivity;
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;
import androidx.webkit.WebSettingsCompat;
import androidx.webkit.WebViewFeature;

/**
 * Student Planner — Android wrapper.
 *
 * HOSTED-BACKEND BUILD: this app loads the PHP/MySQL backend in
 * backend/ (now the updated version, with groups + group chat) from a
 * server you host it on, via the URL in res/values/strings.xml
 * (R.string.app_url) — see that file for how to set it. This is a
 * thin WebView shell around that hosted site, not an offline app: the
 * "database" is the MySQL database on your server, reachable from any
 * device that logs in, and the backend/ folder needs to actually be
 * uploaded to a PHP host (or run locally via XAMPP) for the app to
 * work.
 *
 * The offline, bundled HTML/JS + localStorage version of this app
 * (originally used to run fully offline with no server) is still kept
 * at app/src/main/assets/www/ in case that mode is wanted again later
 * — this app just no longer points at it.
 */
public class MainActivity extends AppCompatActivity {

    private WebView webView;
    private SwipeRefreshLayout swipeRefresh;
    private ProgressBar progressBar;
    private LinearLayout offlineView;

    private ValueCallback<Uri[]> filePathCallback;
    private static final int FILE_CHOOSER_REQUEST_CODE = 1001;

    @SuppressLint("SetJavaScriptEnabled")
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        webView = findViewById(R.id.webView);
        swipeRefresh = findViewById(R.id.swipeRefresh);
        progressBar = findViewById(R.id.progressBar);
        offlineView = findViewById(R.id.offlineView);
        Button retryButton = findViewById(R.id.retryButton);

        setupWebView();

        retryButton.setOnClickListener(v -> loadApp());
        swipeRefresh.setOnRefreshListener(this::loadApp);

        // Proper back-button behaviour: go back through WebView history
        // first; only exit the app when there's nothing left to go back to.
        getOnBackPressedCallback();

        loadApp();
    }

    private void getOnBackPressedCallback() {
        getOnBackPressedDispatcher().addCallback(this, new OnBackPressedCallback(true) {
            @Override
            public void handleOnBackPressed() {
                if (webView.canGoBack()) {
                    webView.goBack();
                } else {
                    setEnabled(false);
                    getOnBackPressedDispatcher().onBackPressed();
                }
            }
        });
    }

    @SuppressLint("SetJavaScriptEnabled")
    private void setupWebView() {
        WebSettings settings = webView.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);          // needed for localStorage / most modern JS
        settings.setDatabaseEnabled(true);
        settings.setLoadWithOverviewMode(true);
        settings.setUseWideViewPort(true);
        settings.setSupportZoom(false);
        settings.setBuiltInZoomControls(false);
        settings.setCacheMode(WebSettings.LOAD_DEFAULT);
        settings.setMixedContentMode(WebSettings.MIXED_CONTENT_COMPATIBILITY_MODE);
        settings.setAllowFileAccess(true);
        settings.setAllowFileAccessFromFileURLs(true);
        settings.setAllowUniversalAccessFromFileURLs(true);
        settings.setMediaPlaybackRequiresUserGesture(false);

        // Some free hosts (InfinityFree and similar) run bot/abuse filters
        // that specifically block the default WebView User-Agent (it ends
        // in "; wv)", which flags it as an embedded/non-browser client) —
        // this can make specific pages 404 or get blocked in-app even
        // though the exact same URL loads fine in a real mobile browser.
        // Swap in a normal Chrome-for-Android UA (WITHOUT "; wv") so the
        // app looks like an ordinary browser to the server.
        String ua = settings.getUserAgentString().replace("; wv", "");
        settings.setUserAgentString(ua);

        // Make sure the PHP session cookie set by login.php is actually
        // kept and sent on every later request/AJAX fetch (e.g. chat.php
        // and group_chat.php polling) — without this, some WebView
        // configurations can silently drop cookies between navigations.
        CookieManager cookieManager = CookieManager.getInstance();
        cookieManager.setAcceptCookie(true);
        cookieManager.setAcceptThirdPartyCookies(webView, true);

        // Fix: Android tries to "dark mode" web content automatically on
        // devices with system dark theme on. Since this app's design is
        // ALREADY dark navy, that double-darkening makes everything look
        // near-black and unreadable. Force it off so our real colors show.
        if (WebViewFeature.isFeatureSupported(WebViewFeature.ALGORITHMIC_DARKENING)) {
            WebSettingsCompat.setAlgorithmicDarkeningAllowed(settings, false);
        } else if (WebViewFeature.isFeatureSupported(WebViewFeature.FORCE_DARK)) {
            WebSettingsCompat.setForceDark(settings, WebSettingsCompat.FORCE_DARK_OFF);
        }

        webView.setWebViewClient(new WebViewClient() {
            @Override
            public void onPageFinished(WebView view, String url) {
                super.onPageFinished(view, url);
                progressBar.setVisibility(View.GONE);
                swipeRefresh.setRefreshing(false);
                swipeRefresh.setVisibility(View.VISIBLE);
                offlineView.setVisibility(View.GONE);
            }

            @Override
            public void onReceivedError(WebView view, int errorCode, String description, String failingUrl) {
                super.onReceivedError(view, errorCode, description, failingUrl);
                // Only show the offline screen for the main page failing,
                // not for minor sub-resource errors (icons, fonts, etc.)
                if (failingUrl != null && failingUrl.equals(view.getUrl())) {
                    showOffline();
                }
            }

            @Override
            public void onReceivedSslError(WebView view, SslErrorHandler handler, SslError error) {
                // Don't silently trust bad certs; let the user know instead
                // of the page just failing to load with no explanation.
                super.onReceivedSslError(view, handler, error);
            }
        });

        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public void onProgressChanged(WebView view, int newProgress) {
                super.onProgressChanged(view, newProgress);
                if (newProgress >= 95) {
                    progressBar.setVisibility(View.GONE);
                }
            }

            // Handles <input type="file"> (e.g. avatar upload, message attachments)
            @Override
            public boolean onShowFileChooser(WebView view, ValueCallback<Uri[]> callback,
                                              FileChooserParams params) {
                if (filePathCallback != null) {
                    filePathCallback.onReceiveValue(null);
                }
                filePathCallback = callback;

                Intent intent = params.createIntent();
                try {
                    startActivityForResult(intent, FILE_CHOOSER_REQUEST_CODE);
                } catch (Exception e) {
                    filePathCallback = null;
                    return false;
                }
                return true;
            }
        });
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        if (requestCode == FILE_CHOOSER_REQUEST_CODE) {
            if (filePathCallback == null) {
                super.onActivityResult(requestCode, resultCode, data);
                return;
            }
            Uri[] results = null;
            if (resultCode == RESULT_OK && data != null) {
                String dataString = data.getDataString();
                if (dataString != null) {
                    results = new Uri[]{Uri.parse(dataString)};
                }
            }
            filePathCallback.onReceiveValue(results);
            filePathCallback = null;
        } else {
            super.onActivityResult(requestCode, resultCode, data);
        }
    }

    @Override
    protected void onPause() {
        super.onPause();
        // Persist the session cookie to disk so it survives the app being
        // backgrounded or the process being killed, not just kept in memory.
        CookieManager.getInstance().flush();
    }

    private void loadApp() {
        offlineView.setVisibility(View.GONE);
        progressBar.setVisibility(View.VISIBLE);
        webView.loadUrl(getString(R.string.app_url));
    }

    private void showOffline() {
        // Shown when the hosted backend can't be reached (no internet,
        // server down, or app_url in strings.xml hasn't been set yet).
        progressBar.setVisibility(View.GONE);
        swipeRefresh.setRefreshing(false);
        swipeRefresh.setVisibility(View.GONE);
        offlineView.setVisibility(View.VISIBLE);
    }
}
