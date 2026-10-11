# Affiche A3 Jack&Cie : HTML → PDF vectoriel (impression) + PNG (aperçu).
# Usage : python3 build.py <dossier> [url_installation] [logo_nlapps]
import sys, os, subprocess, html, base64, segno
F = sys.argv[1]
URL = sys.argv[2] if len(sys.argv) > 2 and sys.argv[2] else None
NL = sys.argv[3] if len(sys.argv) > 3 and sys.argv[3] else None
ICON = {
 'health': 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z',
 'mic': 'M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z',
 'session': 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z',
 'calendar': 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5',
 'wallet': 'M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9m18 0V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v3',
 'share': 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
}
atouts = [
 ('health', 'Santé et soins', 'Maréchal, vaccins, vermifuges : rappels automatiques.'),
 ('mic', 'Assistant vocal', '« Cours demain à 16 h avec Jimmy » : c\'est noté.'),
 ('session', 'Séances et cours', 'Préparation, exercices, bilan et progression.'),
 ('calendar', 'Agenda', 'Rendez-vous, cours et soins au même endroit.'),
 ('wallet', 'Budget', 'Pension et dépenses récurrentes, paiements suivis.'),
 ('share', 'Partage', 'Demi-pension, propriétaires, écurie : chacun ses droits.'),
]
def svg(name): return f'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="{ICON[name]}"/></svg>'
def b64(path):
    ext = os.path.splitext(path)[1].lower().lstrip('.'); mime = {'jpg': 'jpeg', 'jpeg': 'jpeg', 'png': 'png', 'webp': 'webp', 'svg': 'svg+xml'}[ext]
    return f'data:image/{mime};base64,' + base64.b64encode(open(path, 'rb').read()).decode()
if URL:
    qr = segno.make(URL, error='h')
    qr.save(F + '/qr.svg', scale=10, border=0, dark='#0d3122', light=None)
    qrhtml = f'<img class="qrimg" src="{b64(F + "/qr.svg")}" alt="QR code">'
    urltext = html.escape(URL.replace('https://', '').rstrip('/'))
else:
    qrhtml = '<div class="qrph">QR code<br><small>en attente de l\'adresse<br>de la page d\'installation</small></div>'
    urltext = 'adresse à confirmer'
nl = f'<img class="nlimg" src="{b64(NL)}" alt="NLAPPS">' if NL else '<span class="nlph">NLAPPS</span>'
items = ''.join(f'<div class="at"><span class="ic">{svg(i)}</span><div><b>{html.escape(t)}</b><p>{html.escape(d)}</p></div></div>' for i, t, d in atouts)
page = f'''<!doctype html><html lang="fr"><head><meta charset="utf-8"><style>
@page {{ size: 297mm 420mm; margin: 0 }}
*{{margin:0;padding:0;box-sizing:border-box}}
html,body{{width:297mm;height:420mm}}
body{{font-family:Inter,'Inter Display',system-ui;color:#141915;background:#faf7f1;-webkit-print-color-adjust:exact;print-color-adjust:exact;position:relative;overflow:hidden}}
.arc{{position:absolute;border-radius:50%;border:1mm solid #ede3d1;z-index:0}}
header,h1,.lead,.mid,.offer,footer{{position:relative;z-index:1}}
header{{text-align:center;padding-top:16mm}}
header img{{height:54mm}}
h1{{margin-top:7mm;font-size:14.5mm;line-height:1.02;letter-spacing:-.035em;font-weight:800;color:#0d3122;text-align:center}}
h1 em{{font-style:normal;color:#a8875e}}
.lead{{margin:4mm auto 0;max-width:215mm;text-align:center;font-size:5.4mm;line-height:1.35;color:#4f5751}}
.mid{{display:grid;grid-template-columns:80mm 1fr;gap:12mm;margin:10mm 22mm 0;align-items:center}}
.phone{{width:76mm;height:158mm;border-radius:12mm;background:#0b0f0d;padding:2.8mm;box-shadow:0 8mm 20mm rgba(13,49,34,.28)}}
.phone img{{width:100%;height:100%;object-fit:cover;object-position:top;border-radius:9.5mm;display:block}}
.atouts{{display:grid;gap:5.2mm}}
.at{{display:flex;gap:5mm;align-items:flex-start}}
.ic{{flex:none;width:13mm;height:13mm;border-radius:4.5mm;background:#0d3122;color:#fff;display:flex;align-items:center;justify-content:center}}
.ic svg{{width:7.5mm;height:7.5mm}}
.at b{{display:block;font-size:6mm;letter-spacing:-.015em;color:#0d3122}}
.at p{{margin-top:.8mm;font-size:4.3mm;line-height:1.3;color:#4f5751}}
.offer{{position:absolute;left:18mm;right:18mm;bottom:20mm;height:80mm;z-index:1;border-radius:9mm;background:#0d3122;color:#fff;display:grid;grid-template-columns:1fr 1fr 74mm;align-items:center}}
.off{{padding:0 9mm;border-right:.4mm solid rgba(255,255,255,.15);height:58mm;display:flex;flex-direction:column;justify-content:center}}
.tag{{font-size:4mm;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#d1bb9d}}
.big{{margin-top:2.5mm;font-size:12mm;font-weight:800;letter-spacing:-.03em;line-height:1}}
.off p{{margin-top:3mm;font-size:4.4mm;line-height:1.35;color:rgba(255,255,255,.8)}}
.qr{{display:flex;flex-direction:column;align-items:center;justify-content:center}}
.qrbox{{width:56mm;height:56mm;background:#fff;border-radius:5mm;padding:4mm;display:flex;align-items:center;justify-content:center}}
.qrimg{{width:100%;height:100%}}
.qrph{{width:100%;height:100%;border:.6mm dashed #a8875e;border-radius:3mm;color:#8a6c48;font-weight:700;font-size:4.6mm;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center}}
.qrph small{{font-weight:500;font-size:3mm}}
.scan{{margin-top:3mm;font-size:4.3mm;font-weight:700}}
.url{{font-size:3.4mm;color:rgba(255,255,255,.7)}}
footer{{position:absolute;left:0;right:0;bottom:6.5mm;z-index:1;display:flex;align-items:center;justify-content:center;gap:3mm;font-size:4mm;color:#6b736d}}
.nlimg{{height:9mm}}
.site{{margin-left:2mm;padding-left:3mm;border-left:.3mm solid #cbd0ca;font-weight:600;color:#3a413c}}
.nlph{{font-weight:800;letter-spacing:.08em;color:#141915;border:.4mm dashed #9aa29b;padding:1mm 3mm;border-radius:2mm}}
</style></head><body>
<div class="arc" style="width:240mm;height:240mm;left:-150mm;top:170mm"></div>
<div class="arc" style="width:180mm;height:180mm;right:-120mm;top:30mm"></div>
<header><img src="{b64(F + '/jackcie-logo.png')}" alt="Jack&amp;Cie"></header>
<h1>Toute la vie de votre cheval,<br><em>dans votre poche.</em></h1>
<p class="lead">L'application des cavaliers, propriétaires et écuries : santé, séances, agenda et budget, réunis et partagés.</p>
<section class="mid"><div class="phone"><img src="{b64(F + '/ecran.jpg')}" alt=""></div><div class="atouts">{items}</div></section>
<section class="offer">
 <div class="off"><span class="tag">Version gratuite</span><span class="big">0 €</span><p>Pour suivre un cheval,<br>sans limite de durée.</p></div>
 <div class="off"><span class="tag">Toutes les autres offres</span><span class="big">14 jours</span><p>d'essai gratuit : plusieurs chevaux,<br>assistant vocal, budget, écurie…</p></div>
 <div class="qr"><div class="qrbox">{qrhtml}</div><span class="scan">Scannez pour installer</span><span class="url">{urltext}</span></div>
</section>
<footer>Une application {nl}<span class="site">nlapps.fr</span></footer>
</body></html>'''
open(F + '/affiche.html', 'w').write(page)
subprocess.run(['node', F + '/print.mjs', F], check=True)
