# Affiche A5 « Spécial EquitaLyon 2026 » : HTML → PDF vectoriel (imprimeur, fonds perdus 3 mm ;
# et format fini A5 pour une imprimante de bureau) + PNG d'aperçu.
# Usage : python3 build.py [url_inscription] [code]
import sys, os, subprocess, html, base64, segno
HERE = os.path.dirname(os.path.abspath(__file__))
SRC = os.path.join(HERE, '..', 'affiche')          # polices, logo NLapps, capture d'écran de l'affiche A3
ROOT = os.path.join(HERE, '..', '..')
CODE = sys.argv[2] if len(sys.argv) > 2 else 'EQUITA2026'
INSTAGRAM = 'https://www.instagram.com/jackcieapp/'
URL = sys.argv[1] if len(sys.argv) > 1 else f'https://app.jackcie.fr/register?code={CODE}&src=affiche'
ICON = {
 'mic': 'M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z',
 'health': 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z',
 'session': 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z',
 'scale': 'M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0012 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 01-2.031.352 5.988 5.988 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 01-2.031.352 5.989 5.989 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971z',
 'wallet': 'M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9m18 0V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v3',
 'share': 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
}
atouts = [
 ('mic', 'Assistant vocal', '« Le maréchal vient mardi » : c\'est noté.'),
 ('health', 'Santé et rappels', 'Vaccins, vermifuges, maréchal, dentiste.'),
 ('session', 'Séances et 125 exercices', 'Préparation, bilan et progression.'),
 ('scale', 'Poids au ruban', 'Courbe de poids et alerte en cas de variation.'),
 ('wallet', 'Budget et factures', 'Une photo de la facture, c\'est saisi.'),
 ('share', 'Partage', 'Demi-pension, coach, écurie : chacun ses droits.'),
]
def svg(name): return f'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="{ICON[name]}"/></svg>'
def b64(path):
    ext = os.path.splitext(path)[1].lower().lstrip('.'); mime = {'jpg': 'jpeg', 'jpeg': 'jpeg', 'png': 'png', 'svg': 'svg+xml', 'ttf': 'ttf'}[ext]
    return f'data:{"font" if ext == "ttf" else "image"}/{mime};base64,' + base64.b64encode(open(path, 'rb').read()).decode()
qr = segno.make(URL, error='q')
qr.save(HERE + '/qr.svg', scale=10, border=0, dark='#0d3122', light=None)
segno.make(INSTAGRAM, error='q').save(HERE + '/qr-instagram.svg', scale=10, border=0, dark='#0d3122', light=None)
IG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.3" cy="6.7" r="1" fill="currentColor" stroke="none"/></svg>'
items = ''.join(f'<div class="at"><span class="ic">{svg(i)}</span><div><b>{html.escape(t)}</b><p>{html.escape(d)}</p></div></div>' for i, t, d in atouts)
fonts = ''.join(f"@font-face{{font-family:'InterPrint';font-weight:{w};src:url(\"{b64(SRC + '/fonts/Inter-' + n + '.ttf')}\") format('truetype')}}\n"
                for w, n in [(400, 'Regular'), (500, 'Medium'), (600, 'SemiBold'), (700, 'Bold'), (800, 'ExtraBold')])

def page(bleed):
    W, H = 148 + 2 * bleed, 210 + 2 * bleed
    return f'''<!doctype html><html lang="fr"><head><meta charset="utf-8"><style>
@page {{ size: {W}mm {H}mm; margin: 0 }}
{fonts}
*{{margin:0;padding:0;box-sizing:border-box}}
html,body{{width:{W}mm;height:{H}mm;overflow:hidden}}
body{{font-family:InterPrint,Inter,system-ui;color:#141915;background:#faf7f1;-webkit-print-color-adjust:exact;print-color-adjust:exact;position:relative}}
/* Fonds perdus : le décor déborde de {bleed} mm, le contenu reste dans le format fini (marge de sécurité 7 mm). */
.band{{position:absolute;left:0;right:0;top:0;height:{bleed + 25}mm;background:#0d3122}}
.arc{{position:absolute;border-radius:50%;border:.6mm solid #ede3d1}}
.trim{{position:absolute;left:{bleed}mm;top:{bleed}mm;width:148mm;height:210mm}}
.top{{position:absolute;left:8mm;right:52mm;top:6mm;color:#fff}}
.ig{{position:absolute;right:7mm;top:3mm;display:flex;align-items:center;gap:2.6mm;color:#fff}}
.igtxt{{text-align:right;font-size:2.6mm;line-height:1.3;color:rgba(255,255,255,.85)}}
.igtxt b{{display:flex;align-items:center;justify-content:flex-end;gap:1mm;font-size:3.1mm;color:#fff}}
.igtxt svg{{width:3.4mm;height:3.4mm;color:#d1bb9d}}
.igqr{{width:19mm;height:19mm;background:#fff;border-radius:2mm;padding:1.4mm}}
.igqr img{{width:100%;height:100%;display:block}}
.kicker{{font-size:2.8mm;font-weight:700;letter-spacing:.28em;color:#d1bb9d}}
.event{{margin-top:1.4mm;font-size:8.2mm;font-weight:800;letter-spacing:.02em;line-height:1}}
.event span{{color:#d1bb9d}}
.logo{{position:absolute;left:0;right:0;top:28.5mm;text-align:center}}
.logo img{{height:25mm}}
h1{{position:absolute;left:7mm;right:7mm;top:55mm;text-align:center;font-size:11.6mm;line-height:1;letter-spacing:-.035em;font-weight:800;color:#0d3122}}
h1 em{{font-style:normal;color:#a8875e}}
.lead{{position:absolute;left:10mm;right:10mm;top:68.5mm;text-align:center;font-size:3.9mm;line-height:1.35;font-weight:600;color:#3a413c}}
.lead b{{color:#0d3122}}
.mid{{position:absolute;left:9mm;right:8mm;top:81mm;display:grid;grid-template-columns:34mm 1fr;gap:6mm;align-items:center}}
.phone{{width:34mm;height:70.5mm;border-radius:5.5mm;background:#0b0f0d;padding:1.3mm;box-shadow:0 3mm 8mm rgba(13,49,34,.25)}}
.phone img{{width:100%;height:100%;object-fit:cover;object-position:top;border-radius:4.3mm;display:block}}
.atouts{{display:grid;gap:2.9mm}}
.at{{display:flex;gap:2.6mm;align-items:center}}
.ic{{flex:none;width:7.4mm;height:7.4mm;border-radius:2.4mm;background:#0d3122;color:#fff;display:flex;align-items:center;justify-content:center}}
.ic svg{{width:4.3mm;height:4.3mm}}
.at b{{display:block;font-size:3.5mm;letter-spacing:-.01em;color:#0d3122;line-height:1.15}}
.at p{{margin-top:.3mm;font-size:2.65mm;line-height:1.25;color:#4f5751}}
.offer{{position:absolute;left:7mm;right:7mm;top:155mm;height:39mm;border-radius:5mm;background:#0d3122;color:#fff;display:grid;grid-template-columns:1fr 33mm;gap:4mm;align-items:center;padding:0 4.5mm 0 5.5mm}}
.tag{{font-size:2.5mm;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#d1bb9d}}
.code{{margin-top:1.6mm;display:inline-block;border:.5mm dashed #d1bb9d;border-radius:2.4mm;padding:1.3mm 3.2mm;font-size:7.6mm;font-weight:800;letter-spacing:.06em;color:#fff;line-height:1.1}}
.steps{{margin-top:2.4mm;font-size:2.75mm;line-height:1.5;color:rgba(255,255,255,.88)}}
.steps b{{color:#fff}}
.qr{{display:flex;flex-direction:column;align-items:center}}
.qrbox{{width:28mm;height:28mm;background:#fff;border-radius:2.6mm;padding:2mm}}
.qrbox img{{width:100%;height:100%;display:block}}
.scan{{margin-top:1.3mm;font-size:2.5mm;font-weight:700;text-align:center;line-height:1.2}}
.small{{position:absolute;left:8mm;right:8mm;top:196.6mm;text-align:center;font-size:2.05mm;line-height:1.35;color:#6b736d}}
.foot{{position:absolute;left:0;right:0;top:204.2mm;display:flex;align-items:center;justify-content:center;gap:1.4mm;font-size:2.3mm;color:#6b736d}}
.foot img{{height:4mm}}
.foot .site{{margin-left:1mm;padding-left:1.6mm;border-left:.2mm solid #cbd0ca;font-weight:600;color:#3a413c}}
</style></head><body>
<div class="band"></div>
<div class="arc" style="width:120mm;height:120mm;left:-80mm;top:{bleed + 95}mm"></div>
<div class="arc" style="width:90mm;height:90mm;right:-62mm;top:{bleed + 40}mm"></div>
<div class="trim">
 <div class="top"><div class="kicker">OFFRE SPÉCIALE SALON</div><div class="event">EQUITA<span>LYON</span> 2026</div></div>
 <div class="ig"><div class="igtxt"><b>{IG} Instagram</b>Suivez-nous<br>@jackcieapp</div><div class="igqr"><img src="{b64(HERE + '/qr-instagram.svg')}" alt="QR code Instagram"></div></div>
 <div class="logo"><img src="{b64(ROOT + '/public/brand/jackcie-logo.png')}" alt="Jack&amp;Cie"></div>
 <h1>30 jours <em>offerts</em></h1>
 <p class="lead"><b>Toutes les fonctionnalités</b>, sans carte bancaire.<br>L'application qui réunit toute la vie de votre cheval.</p>
 <section class="mid"><div class="phone"><img src="{b64(SRC + '/ecran.jpg')}" alt=""></div><div class="atouts">{items}</div></section>
 <section class="offer">
  <div><span class="tag">Votre code</span><br><span class="code">{html.escape(CODE)}</span>
   <p class="steps"><b>1.</b> Scannez le QR code &nbsp;<b>2.</b> Créez votre compte<br><b>3.</b> Le code s'active aussitôt : profitez de tout pendant 30 jours</p></div>
  <div class="qr"><div class="qrbox"><img src="{b64(HERE + '/qr.svg')}" alt="QR code"></div><span class="scan">Scannez et créez<br>votre compte</span></div>
 </section>
 <p class="small">Offre valable une fois par compte, sur l'offre Propriétaire (un cheval, toutes les fonctionnalités) ; plusieurs chevaux : offre<br>Propriétaire multi-chevaux. Sans engagement : à la fin des 30 jours, sans souscription, le compte passe à l'offre gratuite Découverte.</p>
 <div class="foot">Une application <img src="{b64(SRC + '/nlapps-logo.png')}" alt="NLapps"><span class="site">jackcie.fr</span></div>
</div>
</body></html>'''

OUT = os.path.join(ROOT, 'dist', 'affiche-equitalyon'); os.makedirs(OUT, exist_ok=True)
open(HERE + '/affiche-fonds-perdus.html', 'w').write(page(3))
open(HERE + '/affiche-a5.html', 'w').write(page(0))
subprocess.run(['node', HERE + '/print.mjs', HERE, OUT], check=True)
for f in ('affiche-fonds-perdus.html', 'affiche-a5.html', 'qr.svg', 'qr-instagram.svg'):
    os.remove(os.path.join(HERE, f))
