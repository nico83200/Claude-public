# Affiche A4 générale Jack&Cie (écuries, selleries, salles d'attente) : présente l'app et invite à l'essayer.
# Sans lien avec un salon ni code promo. HTML → PDF vectoriel (imprimeur, fonds perdus 3 mm ; format fini A4
# pour une imprimante de bureau, à agrandir sans perte en A3) + PNG d'aperçu.
# Usage : python3 scripts/affiche-app/build.py [source]
import sys, os, subprocess, html, base64, segno
HERE = os.path.dirname(os.path.abspath(__file__))
SRC = os.path.join(HERE, '..', 'affiche')          # polices TrueType et logo NLapps
ROOT = os.path.join(HERE, '..', '..')
SOURCE = sys.argv[1] if len(sys.argv) > 1 else 'affiche-app'
URL = f'https://jackcie.fr/?src={SOURCE}'
# Mentions légales (identification de l'annonceur, imprimeur, code de l'environnement).
# Si l'affiche est confiée à un imprimeur, remplacer « Imprimé par nos soins » par son nom et sa ville.
PRINTER = 'Imprimé par nos soins'
LEGAL = f'Éditeur : Nicolas Lefèvre EI (NLapps) · SIRET 519 791 719 00114 · 83130 La Garde · {PRINTER} · Ne pas jeter sur la voie publique'

ICON = {
 'mic': 'M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z',
 'health': 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z',
 'session': 'M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z',
 'calendar': 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5',
 'wallet': 'M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9m18 0V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v3',
 'share': 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
 'check': 'M4.5 12.75l6 6 9-13.5',
}
atouts = [
 ('health', 'Santé et rappels', 'Vaccins, vermifuges, maréchal, dentiste : prévenu avant l\'échéance.'),
 ('calendar', 'Agenda partagé', 'Rendez-vous, séances et soins au même endroit.'),
 ('session', 'Séances et progression', 'Plus de 125 exercices, bilans et suivi.'),
 ('mic', 'Assistant vocal', '« Le maréchal vient mardi » : c\'est noté.'),
 ('wallet', 'Budget et factures', 'Une photo de la facture, la dépense est saisie.'),
 ('share', 'Partage', 'Demi-pension, coach, écurie : chacun ses droits.'),
]
def svg(name, w=1.7): return f'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{w}" stroke-linecap="round" stroke-linejoin="round"><path d="{ICON[name]}"/></svg>'
def b64(path):
    ext = os.path.splitext(path)[1].lower().lstrip('.'); mime = {'jpg': 'jpeg', 'jpeg': 'jpeg', 'png': 'png', 'svg': 'svg+xml', 'ttf': 'ttf'}[ext]
    return f'data:{"font" if ext == "ttf" else "image"}/{mime};base64,' + base64.b64encode(open(path, 'rb').read()).decode()

# Captures d'écran du site (webp) → jpg pour le moteur PDF.
from PIL import Image
for n in ('accueil', 'assistant'):
    Image.open(f'{ROOT}/site/img/app/mobile-{n}.webp').convert('RGB').save(f'{HERE}/ecran-{n}.jpg', quality=90)
segno.make(URL, error='q').save(HERE + '/qr.svg', scale=10, border=0, dark='#0d3122', light=None)
fonts = ''.join(f"@font-face{{font-family:'InterPrint';font-weight:{w};src:url(\"{b64(SRC + '/fonts/Inter-' + n + '.ttf')}\") format('truetype')}}\n"
                for w, n in [(400, 'Regular'), (500, 'Medium'), (600, 'SemiBold'), (700, 'Bold'), (800, 'ExtraBold')])
items = ''.join(f'<div class="at"><span class="ic">{svg(i)}</span><div><b>{html.escape(t)}</b><p>{html.escape(d)}</p></div></div>' for i, t, d in atouts)

def page(bleed):
    W, H = 210 + 2 * bleed, 297 + 2 * bleed
    return f'''<!doctype html><html lang="fr"><head><meta charset="utf-8"><style>
@page {{ size: {W}mm {H}mm; margin: 0 }}
{fonts}
*{{margin:0;padding:0;box-sizing:border-box}}
html,body{{width:{W}mm;height:{H}mm;overflow:hidden}}
body{{font-family:InterPrint,Inter,system-ui;color:#141915;background:#faf7f1;-webkit-print-color-adjust:exact;print-color-adjust:exact;position:relative}}
/* Fonds perdus : le décor déborde de {bleed} mm, le contenu reste dans le format fini (marge de sécurité 10 mm). */
.deco{{position:absolute;inset:0;overflow:hidden}}
.arc{{position:absolute;border-radius:50%;border:.8mm solid #ede3d1}}
.bottom{{position:absolute;left:0;right:0;bottom:0;height:{bleed + 84}mm;background:#0d3122}}
.trim{{position:absolute;left:{bleed}mm;top:{bleed}mm;width:210mm;height:297mm}}
.logo{{position:absolute;left:0;right:0;top:11mm;text-align:center}}
.logo img{{height:40mm}}
h1{{position:absolute;left:10mm;right:10mm;top:56mm;text-align:center;font-size:14.6mm;line-height:1.02;letter-spacing:-.035em;font-weight:800;color:#0d3122}}
h1 em{{font-style:normal;color:#a8875e}}
.lead{{position:absolute;left:22mm;right:22mm;top:89mm;text-align:center;font-size:5mm;line-height:1.38;font-weight:500;color:#3a413c}}
.lead b{{color:#0d3122;font-weight:700}}
.phones{{position:absolute;left:12mm;top:110mm;width:76mm;height:100mm}}
.phone{{position:absolute;width:44mm;height:95.2mm;border-radius:7mm;background:#0b0f0d;padding:1.6mm;box-shadow:0 4mm 10mm rgba(13,49,34,.28)}}
.phone img{{width:100%;height:100%;object-fit:cover;object-position:top;border-radius:5.6mm;display:block}}
.phone.back{{left:30mm;top:0;transform:rotate(5deg)}}
.phone.front{{left:0;top:4mm;transform:rotate(-3deg)}}
.atouts{{position:absolute;left:96mm;right:11mm;top:113mm;display:grid;gap:4.6mm}}
.at{{display:flex;gap:3.4mm;align-items:center}}
.ic{{flex:none;width:10mm;height:10mm;border-radius:3.2mm;background:#0d3122;color:#fff;display:flex;align-items:center;justify-content:center}}
.ic svg{{width:5.6mm;height:5.6mm}}
.at b{{display:block;font-size:4.5mm;letter-spacing:-.01em;color:#0d3122;line-height:1.15}}
.at p{{margin-top:.5mm;font-size:3.35mm;line-height:1.3;color:#4f5751}}
.offer{{position:absolute;left:12mm;right:12mm;top:224mm;height:50mm;color:#fff;display:grid;grid-template-columns:1fr 44mm;gap:8mm;align-items:center}}
.kicker{{font-size:3.2mm;font-weight:700;letter-spacing:.24em;text-transform:uppercase;color:#d1bb9d}}
.cta{{margin-top:2mm;font-size:11mm;font-weight:800;letter-spacing:-.03em;line-height:1}}
.cta em{{font-style:normal;color:#d1bb9d}}
.checks{{margin-top:4mm;display:grid;gap:1.6mm;font-size:3.7mm;color:rgba(255,255,255,.9)}}
.checks span{{display:flex;align-items:center;gap:2mm}}
.checks svg{{width:4.2mm;height:4.2mm;color:#d1bb9d;flex:none}}
.qr{{display:flex;flex-direction:column;align-items:center}}
.qrbox{{width:40mm;height:40mm;background:#fff;border-radius:3.4mm;padding:3mm}}
.qrbox img{{width:100%;height:100%;display:block}}
.scan{{margin-top:2mm;font-size:3.4mm;font-weight:700;text-align:center;line-height:1.2}}
.foot{{position:absolute;left:0;right:0;top:279.5mm;display:flex;align-items:center;justify-content:center;gap:2mm;font-size:3.2mm;color:rgba(255,255,255,.75)}}
.foot img{{height:5.4mm;background:#fff;border-radius:1.4mm;padding:.5mm 1.2mm}}
.foot .sep{{width:.25mm;height:4mm;background:rgba(255,255,255,.3);margin:0 1.4mm}}
.foot b{{color:#fff;font-weight:600}}
.legal{{position:absolute;left:10mm;right:10mm;top:287.4mm;text-align:center;white-space:nowrap;font-size:2.1mm;color:rgba(255,255,255,.55)}}
</style></head><body>
<div class="deco">
 <div class="arc" style="width:170mm;height:170mm;left:-110mm;top:{bleed + 60}mm"></div>
 <div class="arc" style="width:120mm;height:120mm;right:-80mm;top:{bleed - 30}mm"></div>
 <div class="bottom"></div>
</div>
<div class="trim">
 <div class="logo"><img src="{b64(ROOT + '/public/brand/jackcie-logo.png')}" alt="Jack&amp;Cie"></div>
 <h1>Toute la vie de votre cheval,<br><em>dans votre poche.</em></h1>
 <p class="lead">Santé, séances, agenda, budget : <b>tout au même endroit</b>,<br>partagé avec ceux qui s'occupent de votre cheval.</p>
 <div class="phones"><div class="phone back"><img src="{b64(HERE + '/ecran-assistant.jpg')}" alt=""></div><div class="phone front"><img src="{b64(HERE + '/ecran-accueil.jpg')}" alt=""></div></div>
 <div class="atouts">{items}</div>
 <section class="offer">
  <div><div class="kicker">Essayez maintenant</div><div class="cta">Commencez<br><em>gratuitement.</em></div>
   <div class="checks"><span>{svg('check', 2.4)} Gratuit pour un cheval, sans limite de durée</span><span>{svg('check', 2.4)} Sans carte bancaire</span><span>{svg('check', 2.4)} Téléphone, tablette et ordinateur</span></div></div>
  <div class="qr"><div class="qrbox"><img src="{b64(HERE + '/qr.svg')}" alt="QR code"></div><span class="scan">Scannez et créez<br>votre compte en 1 minute</span></div>
 </section>
 <div class="foot">Une application <img src="{b64(SRC + '/nlapps-logo.png')}" alt="NLapps"><span class="sep"></span><b>jackcie.fr</b><span class="sep"></span>Instagram <b>@jackcieapp</b></div>
 <p class="legal">{html.escape(LEGAL)}</p>
</div>
</body></html>'''

OUT = os.path.join(ROOT, 'dist', 'affiche-app'); os.makedirs(OUT, exist_ok=True)
open(HERE + '/affiche-fonds-perdus.html', 'w').write(page(3))
open(HERE + '/affiche-a4.html', 'w').write(page(0))
subprocess.run(['node', HERE + '/print.mjs', HERE, OUT], check=True)
for f in ('affiche-fonds-perdus.html', 'affiche-a4.html', 'qr.svg', 'ecran-accueil.jpg', 'ecran-assistant.jpg'):
    os.remove(os.path.join(HERE, f))
print('✓', OUT, URL)
