{{-- En-tête commun : PWA installable (Android, iOS, ordinateur) --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="{{ $themeColor ?? '#FFFFFF' }}">
<meta name="color-scheme" content="light">
<meta name="application-name" content="Jack&amp;Cie">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Jack&amp;Cie">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="format-detection" content="telephone=no">
<link rel="manifest" href="/manifest.webmanifest">
{{-- L'invite d'installation native (Android / ordinateur) peut arriver avant le chargement des scripts :
     on la garde dès maintenant pour que le bouton « Installer » installe la vraie application, et non un raccourci. --}}
<script>window.__jcInstall=null;addEventListener('beforeinstallprompt',function(e){e.preventDefault();window.__jcInstall=e;dispatchEvent(new Event('jc-installable'));});</script>
<link rel="icon" href="/icons/favicon-32.png" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png" sizes="180x180">
<title>{{ isset($title) && $title ? $title.' · ' : '' }}{{ $appName }}</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
