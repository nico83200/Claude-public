package fr.tapikard.encodeur;

import android.app.Activity;
import android.app.AlertDialog;
import android.content.ActivityNotFoundException;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.graphics.Insets;
import android.net.Uri;
import android.nfc.NfcAdapter;
import android.nfc.Tag;
import android.nfc.TagLostException;
import android.nfc.tech.IsoDep;
import android.os.Build;
import android.os.Bundle;
import android.os.VibrationEffect;
import android.os.Vibrator;
import android.provider.Settings;
import android.text.InputType;
import android.view.WindowInsets;
import android.webkit.CookieManager;
import android.webkit.JavascriptInterface;
import android.webkit.PermissionRequest;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.EditText;
import android.widget.FrameLayout;

import java.io.IOException;
import java.util.Locale;

/**
 * Application d'encodage Tapikard : affiche la page /encodage du site et lui prête le lecteur NFC du téléphone.
 * Le téléphone ne fait que relayer les commandes (APDU) entre le site et la carte : toute la cryptographie
 * et les clés restent sur le serveur.
 */
public class MainActivity extends Activity implements NfcAdapter.ReaderCallback {

    private static final String SITE_PAR_DEFAUT = "https://nfc.nlapps.fr";
    private static final String CHEMIN = "/encodage";

    private SharedPreferences prefs;
    private WebView web;
    private NfcAdapter nfc;
    private String site;
    private String etatNfc = "";

    // Accédés depuis le fil NFC et le fil du pont JavaScript.
    private volatile IsoDep carte;
    private volatile String uidCarte = "";
    private volatile boolean pageDuSite = false;
    // Demande d'accès à la caméra (scan des QR codes) en attente de l'autorisation Android.
    private PermissionRequest demandeCamera;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        prefs = getSharedPreferences("tapikard", MODE_PRIVATE);
        site = prefs.getString("site", SITE_PAR_DEFAUT);
        nfc = NfcAdapter.getDefaultAdapter(this);

        FrameLayout racine = new FrameLayout(this);
        racine.setBackgroundColor(0xFFF7F5F0);
        racine.setOnApplyWindowInsetsListener((v, insets) -> {
            if (Build.VERSION.SDK_INT >= 30) {
                Insets i = insets.getInsets(WindowInsets.Type.systemBars() | WindowInsets.Type.ime());
                v.setPadding(i.left, i.top, i.right, i.bottom);
                return WindowInsets.CONSUMED;
            }
            v.setPadding(insets.getSystemWindowInsetLeft(), insets.getSystemWindowInsetTop(),
                    insets.getSystemWindowInsetRight(), insets.getSystemWindowInsetBottom());
            return insets.consumeSystemWindowInsets();
        });
        web = new WebView(this);
        racine.addView(web, new FrameLayout.LayoutParams(FrameLayout.LayoutParams.MATCH_PARENT, FrameLayout.LayoutParams.MATCH_PARENT));
        setContentView(racine);

        WebSettings s = web.getSettings();
        s.setJavaScriptEnabled(true);
        s.setDomStorageEnabled(true);
        s.setAllowFileAccess(false);
        s.setAllowContentAccess(false);
        s.setSupportMultipleWindows(false);
        s.setUserAgentString(s.getUserAgentString() + " TapikardEncodeur/" + version());
        CookieManager.getInstance().setAcceptCookie(true);
        CookieManager.getInstance().setAcceptThirdPartyCookies(web, false);

        web.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest req) {
                Uri u = req.getUrl();
                if (estDuSite(u)) return false;
                // Les liens externes (email, autres sites) s'ouvrent hors de l'application.
                try {
                    startActivity(new Intent(Intent.ACTION_VIEW, u));
                } catch (ActivityNotFoundException ignored) {
                }
                return true;
            }

            @Override
            public void doUpdateVisitedHistory(WebView view, String url, boolean isReload) {
                pageDuSite = estDuSite(Uri.parse(url));
            }

            @Override
            public void onPageFinished(WebView view, String url) {
                pageDuSite = estDuSite(Uri.parse(url));
                CookieManager.getInstance().flush();
            }

            @Override
            public void onReceivedError(WebView view, WebResourceRequest req, WebResourceError err) {
                if (req.isForMainFrame()) afficherHorsLigne(String.valueOf(err.getDescription()));
            }
        });
        web.setWebChromeClient(new WebChromeClient() {
            @Override
            public void onPermissionRequest(PermissionRequest req) {
                boolean camera = false;
                for (String r : req.getResources()) camera |= PermissionRequest.RESOURCE_VIDEO_CAPTURE.equals(r);
                if (!camera || !estDuSite(req.getOrigin())) {
                    req.deny();
                    return;
                }
                if (checkSelfPermission(android.Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED) {
                    req.grant(new String[]{PermissionRequest.RESOURCE_VIDEO_CAPTURE});
                } else {
                    demandeCamera = req;
                    requestPermissions(new String[]{android.Manifest.permission.CAMERA}, 1);
                }
            }
        });
        web.addJavascriptInterface(new Pont(), "TapikardNfc");

        if (savedInstanceState != null) web.restoreState(savedInstanceState);
        else web.loadUrl(site + CHEMIN);
    }

    @Override
    public void onRequestPermissionsResult(int code, String[] permissions, int[] resultats) {
        super.onRequestPermissionsResult(code, permissions, resultats);
        if (demandeCamera == null) return;
        if (resultats.length > 0 && resultats[0] == PackageManager.PERMISSION_GRANTED) {
            demandeCamera.grant(new String[]{PermissionRequest.RESOURCE_VIDEO_CAPTURE});
        } else {
            demandeCamera.deny();
        }
        demandeCamera = null;
    }

    @Override
    protected void onSaveInstanceState(Bundle out) {
        super.onSaveInstanceState(out);
        web.saveState(out);
    }

    @Override
    protected void onResume() {
        super.onResume();
        String etat = etatNfc();
        if (!etatNfc.isEmpty() && !etat.equals(etatNfc)) web.reload(); // NFC activé/désactivé dans les réglages
        etatNfc = etat;
        if (nfc != null && nfc.isEnabled()) {
            Bundle options = new Bundle();
            // Vérification de présence espacée : la carte reste en session pendant les allers-retours réseau.
            options.putInt(NfcAdapter.EXTRA_READER_PRESENCE_CHECK_DELAY, 1500);
            nfc.enableReaderMode(this, this, NfcAdapter.FLAG_READER_NFC_A | NfcAdapter.FLAG_READER_SKIP_NDEF_CHECK, options);
        }
    }

    @Override
    protected void onPause() {
        super.onPause();
        if (nfc != null) nfc.disableReaderMode(this);
        CookieManager.getInstance().flush();
    }

    @Override
    public void onBackPressed() {
        if (web.canGoBack()) web.goBack();
        else super.onBackPressed();
    }

    /** Une carte vient d'être posée sur le téléphone (fil NFC). */
    @Override
    public void onTagDiscovered(Tag tag) {
        String uid = hex(tag.getId());
        IsoDep d = IsoDep.get(tag);
        boolean compatible = false;
        if (d != null) {
            try {
                d.connect();
                d.setTimeout(3000);
                compatible = true;
            } catch (IOException e) {
                d = null;
            }
        }
        IsoDep ancienne = carte;
        carte = compatible ? d : null;
        uidCarte = compatible ? uid : "";
        if (ancienne != null) {
            try {
                ancienne.close();
            } catch (IOException ignored) {
            }
        }
        final String js = "window.tapikardCarte&&window.tapikardCarte('" + uid + "'," + compatible + ")";
        runOnUiThread(() -> web.evaluateJavascript(js, null));
    }

    /** Pont appelé par la page /encodage (window.TapikardNfc). Les méthodes s'exécutent hors du fil principal. */
    private class Pont {
        @JavascriptInterface
        public String disponible() {
            return etatNfc();
        }

        @JavascriptInterface
        public String version() {
            return MainActivity.this.version();
        }

        /** Envoie une commande à la carte posée ; renvoie la réponse en hexadécimal, ou « ERR:… ». */
        @JavascriptInterface
        public String transceive(String apduHex) {
            if (!pageDuSite) return "ERR:page non autorisée";
            IsoDep d = carte;
            if (d == null) return "ERR:aucune carte";
            try {
                return hex(d.transceive(unhex(apduHex)));
            } catch (TagLostException e) {
                return "ERR:carte retirée trop tôt";
            } catch (IOException | IllegalArgumentException | IllegalStateException e) {
                return "ERR:" + e.getClass().getSimpleName() + (e.getMessage() != null ? " " + e.getMessage() : "");
            }
        }

        /** UID de la carte posée en ce moment, ou chaîne vide. */
        @JavascriptInterface
        public String carteActuelle() {
            if (!pageDuSite) return "";
            IsoDep d = carte;
            return d != null && d.isConnected() ? uidCarte : "";
        }

        @JavascriptInterface
        public void vibrer(int ms) {
            Vibrator v = getSystemService(Vibrator.class);
            if (v != null && v.hasVibrator()) v.vibrate(VibrationEffect.createOneShot(Math.max(1, Math.min(ms, 1000)), VibrationEffect.DEFAULT_AMPLITUDE));
        }

        @JavascriptInterface
        public void reglagesNfc() {
            runOnUiThread(() -> {
                try {
                    startActivity(new Intent(Settings.ACTION_NFC_SETTINGS));
                } catch (ActivityNotFoundException e) {
                    startActivity(new Intent(Settings.ACTION_SETTINGS));
                }
            });
        }

        @JavascriptInterface
        public void reglerSite() {
            runOnUiThread(MainActivity.this::demanderSite);
        }
    }

    private void demanderSite() {
        EditText champ = new EditText(this);
        champ.setInputType(InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_VARIATION_URI);
        champ.setText(site);
        champ.setSelectAllOnFocus(true);
        FrameLayout cadre = new FrameLayout(this);
        int m = Math.round(20 * getResources().getDisplayMetrics().density);
        cadre.setPadding(m, m / 2, m, 0);
        cadre.addView(champ);
        new AlertDialog.Builder(this)
                .setTitle("Adresse du site")
                .setMessage("Le site Tapikard sur lequel encoder les cartes.")
                .setView(cadre)
                .setPositiveButton("Enregistrer", (dlg, w) -> {
                    String saisie = champ.getText().toString().trim().replaceAll("/+$", "");
                    if (!saisie.startsWith("https://")) saisie = "https://" + saisie.replaceFirst("^[a-zA-Z]+://", "");
                    Uri u = Uri.parse(saisie);
                    if (u.getHost() == null || u.getHost().isEmpty()) return;
                    site = "https://" + u.getHost() + (u.getPort() > 0 ? ":" + u.getPort() : "");
                    prefs.edit().putString("site", site).apply();
                    web.clearHistory();
                    web.loadUrl(site + CHEMIN);
                })
                .setNeutralButton("Par défaut", (dlg, w) -> {
                    site = SITE_PAR_DEFAUT;
                    prefs.edit().remove("site").apply();
                    web.loadUrl(site + CHEMIN);
                })
                .setNegativeButton("Annuler", null)
                .show();
    }

    private void afficherHorsLigne(String raison) {
        pageDuSite = false;
        String html = "<!doctype html><meta name=viewport content='width=device-width,initial-scale=1'>"
                + "<body style='font-family:sans-serif;background:#F7F5F0;color:#121117;padding:40px 24px;text-align:center'>"
                + "<div style='width:64px;height:64px;border-radius:18px;background:#5B3DF5;margin:0 auto 18px'></div>"
                + "<h2>Site injoignable</h2><p style='color:#6b6774'>" + echapper(site) + "<br><small>" + echapper(raison) + "</small></p>"
                + "<p><a href='" + echapper(site + CHEMIN) + "' style='display:inline-block;background:#121117;color:#fff;padding:13px 26px;border-radius:999px;text-decoration:none;font-weight:600'>Réessayer</a></p>"
                + "<p><a href='#' onclick='TapikardNfc.reglerSite();return false' style='color:#5B3DF5'>Changer l’adresse du site</a></p></body>";
        web.loadDataWithBaseURL(null, html, "text/html", "UTF-8", null);
    }

    private boolean estDuSite(Uri u) {
        Uri s = Uri.parse(site);
        return u != null && "https".equals(u.getScheme()) && s.getHost() != null && s.getHost().equalsIgnoreCase(u.getHost()) && s.getPort() == u.getPort();
    }

    private String etatNfc() {
        if (nfc == null) return "absent";
        return nfc.isEnabled() ? "ok" : "desactive";
    }

    private String version() {
        try {
            return getPackageManager().getPackageInfo(getPackageName(), 0).versionName;
        } catch (Exception e) {
            return "?";
        }
    }

    private static String echapper(String s) {
        return s.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;").replace("'", "&#39;").replace("\"", "&quot;");
    }

    private static String hex(byte[] b) {
        StringBuilder sb = new StringBuilder(b.length * 2);
        for (byte x : b) sb.append(String.format(Locale.ROOT, "%02X", x));
        return sb.toString();
    }

    private static byte[] unhex(String s) {
        if (s == null || s.length() % 2 != 0) throw new IllegalArgumentException("hexadécimal invalide");
        byte[] out = new byte[s.length() / 2];
        for (int i = 0; i < out.length; i++) {
            int hi = Character.digit(s.charAt(2 * i), 16), lo = Character.digit(s.charAt(2 * i + 1), 16);
            if (hi < 0 || lo < 0) throw new IllegalArgumentException("hexadécimal invalide");
            out[i] = (byte) ((hi << 4) | lo);
        }
        return out;
    }
}
