<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Jack&amp;Cie se refait une beauté</title>
<style>
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:28px;background:radial-gradient(120% 80% at 50% 0%,#fff 0%,#faf7f1 55%,#f3ecdf 100%);color:#141915;font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;text-align:center}
main{max-width:460px;width:100%}
.logo{width:210px;max-width:70%;height:auto;margin:0 auto 6px;display:block;animation:float 4s ease-in-out infinite}
.tag{display:inline-block;background:#0d3122;color:#faf7f1;border-radius:999px;padding:7px 16px;font-size:13px;font-weight:600;letter-spacing:.04em;text-transform:uppercase}
h1{font-family:Didot,"Bodoni 72",Georgia,serif;font-size:2rem;line-height:1.15;color:#0d3122;margin:18px 0 10px;font-weight:600}
p{color:#475569;line-height:1.6;margin:0 0 14px;font-size:16px}
.bar{height:6px;border-radius:99px;background:#e7dac3;overflow:hidden;margin:22px auto 10px;max-width:280px}
.bar span{display:block;height:100%;width:40%;border-radius:99px;background:linear-gradient(90deg,#174d37,#a8875e);animation:slide 1.6s ease-in-out infinite}
.small{font-size:13px;color:#64748b}
.list{display:grid;gap:8px;margin:22px 0;text-align:left}
.list div{background:#fff;border-radius:16px;padding:12px 14px;box-shadow:0 1px 3px rgba(13,49,34,.07);font-size:14px;color:#334155}
.list b{color:#0d3122}
button{margin-top:6px;border:0;border-radius:999px;background:#174d37;color:#fff;font:600 15px system-ui;padding:12px 26px;cursor:pointer}
@keyframes slide{0%{transform:translateX(-110%)}100%{transform:translateX(260%)}}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
@media (prefers-reduced-motion:reduce){.logo,.bar span{animation:none}}
</style></head>
<body><main data-maintenance>
<img class="logo" src="/brand/jackcie-logo.png" alt="Jack&amp;Cie">
<span class="tag">Maintenance en cours</span>
<h1>Jack&amp;Cie se refait une beauté</h1>
<p>Nous installons une nouvelle version, avec de nouvelles fonctions pour vous et vos chevaux. Ça ne prend que quelques minutes.</p>
<div class="bar" aria-hidden="true"><span></span></div>
<p class="small">Cette page se recharge toute seule dès que tout est prêt.</p>
<div class="list">
  <div>🔒 <b>Vos données sont en sécurité</b> : rien n'est perdu pendant la mise à jour.</div>
  <div>🐴 <b>Vos chevaux vous attendent</b> : soins, séances, agenda et budget reviennent dans un instant.</div>
</div>
<button type="button" onclick="location.reload()">Réessayer maintenant</button>
</main>
<script>
// Une mise à jour restée inachevée est terminée automatiquement (ou annulée), puis la page se recharge.
(function retry(n) {
    fetch('/maintenance/reprise', { headers: { Accept: 'application/json' }, cache: 'no-store' })
        .then(function (r) { return r.ok ? r.json() : {}; })
        .then(function (d) { if (d && d.down === false) location.reload(); else if (n < 240) setTimeout(function () { retry(n + 1); }, 30000); })
        .catch(function () { if (n < 240) setTimeout(function () { retry(n + 1); }, 30000); });
})(0);
</script></body></html>
