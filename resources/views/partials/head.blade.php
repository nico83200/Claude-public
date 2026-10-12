{{-- En-tête commun : PWA installable (Android, iOS, ordinateur) --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="{{ $themeColor ?? '#FFFFFF' }}" data-light="{{ $themeColor ?? '#FFFFFF' }}">
<meta name="color-scheme" content="light dark">
{{-- Apparence (clair par défaut, sombre ou automatique) appliquée avant l'affichage : pas d'éclair blanc. --}}
<script>
(function () {
    var pref = @json(auth()->user()?->profile?->theme);
    try { if (pref) localStorage.setItem('jc-theme', pref); else pref = localStorage.getItem('jc-theme'); } catch (e) {}
    var mq = window.matchMedia('(prefers-color-scheme: dark)');
    function apply(p) {
        pref = p || 'light';
        var dark = pref === 'dark' || (pref === 'auto' && mq.matches);
        document.documentElement.dataset.theme = dark ? 'dark' : 'light';
        var m = document.querySelector('meta[name="theme-color"]');
        if (m) m.content = dark ? '#0e1210' : (m.dataset.light || m.content);
    }
    window.jcSetTheme = function (p) { try { localStorage.setItem('jc-theme', p); } catch (e) {} apply(p); };
    mq.addEventListener && mq.addEventListener('change', function () { if (pref === 'auto') apply(pref); });
    document.addEventListener('DOMContentLoaded', function () { apply(pref); });
    apply(pref);
})();
</script>
<script>
// Téléphone : une fenêtre contenant un champ date ou heure s'ouvre en plein écran, hors de la couche
// « modale » du navigateur, où certains téléphones n'affichent pas le sélecteur de date ni le clavier.
(function () {
    if (!window.HTMLDialogElement || !matchMedia('(max-width: 639px)').matches) return;
    var modal = HTMLDialogElement.prototype.showModal;
    HTMLDialogElement.prototype.showModal = function () {
        if (!this.querySelector('input[type=date],input[type=time],input[type=datetime-local],input[type=month]')) return modal.call(this);
        var d = this, root = document.documentElement;
        d.classList.add('jc-sheet'); root.classList.add('jc-sheet-open');
        d.addEventListener('close', function () { d.classList.remove('jc-sheet'); if (!document.querySelector('dialog.jc-sheet[open]')) root.classList.remove('jc-sheet-open'); }, { once: true });
        d.show();
        d.scrollTop = 0;
    };
    document.addEventListener('keydown', function (e) { var d = document.querySelector('dialog.jc-sheet[open]'); if (e.key === 'Escape' && d) d.close(); });
})();
</script>
<meta name="application-name" content="Jack&amp;Cie">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Jack&amp;Cie">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="format-detection" content="telephone=no">
<link rel="manifest" href="/app.webmanifest" crossorigin="use-credentials">
{{-- L'invite d'installation native (Android / ordinateur) peut arriver avant le chargement des scripts :
     on la garde dès maintenant pour que le bouton « Installer » installe la vraie application, et non un raccourci. --}}
<script>window.__jcInstall=null;addEventListener('beforeinstallprompt',function(e){e.preventDefault();window.__jcInstall=e;dispatchEvent(new Event('jc-installable'));});</script>
<link rel="icon" href="/icons/favicon-32.png" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png" sizes="180x180">
<title>{{ isset($title) && $title ? $title.' · ' : '' }}{{ $appName }}</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
