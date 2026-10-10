<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Service indisponible</title>
<style>body{font-family:system-ui,sans-serif;background:#faf8f3;color:#1e293b;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;padding:24px;text-align:center}a{color:#2f6347;font-weight:600}h1{font-size:1.5rem}</style></head>
<body><div><img src="/icons/icon.svg" width="56" height="56" alt=""><p style="color:#64748b">Erreur 503</p><h1>Service indisponible</h1><p>{{ isset($exception) && $exception->getMessage() ? $exception->getMessage() : "Maintenance en cours, merci de revenir dans quelques minutes." }}</p><p><a href="/">Retour à l'accueil</a></p></div></body></html>
