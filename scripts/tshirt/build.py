# T-shirt Jack&Cie (salons, concours) : fichiers d'impression devant / dos (PDF vectoriel et PNG 300 ppp
# sur fond transparent, à taille réelle) + simulation sur un t-shirt vert.
# Usage : python3 scripts/tshirt/build.py [source]
import sys, os, subprocess, html, base64, segno
from PIL import Image
HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.join(HERE, '..', '..')
FONTS = os.path.join(HERE, '..', 'affiche', 'fonts')
SOURCE = sys.argv[1] if len(sys.argv) > 1 else 'tshirt'
URL = f'https://jackcie.fr/?src={SOURCE}'
OUT = os.path.join(ROOT, 'dist', 'tshirt'); os.makedirs(OUT, exist_ok=True)
TEE = '#123d2b'          # vert bouteille du textile (simulation)
CREAM, GOLD = '#f6efe2', '#cdb38c'

def b64(path, mime):
    return f'data:{mime};base64,' + base64.b64encode(open(path, 'rb').read()).decode()

# Logo en version claire pour un textile foncé : le vert devient crème, l'or reste or.
logo = Image.open(f'{ROOT}/public/brand/jackcie-logo.png').convert('RGBA')
px = logo.load()
for y in range(logo.height):
    for x in range(logo.width):
        r, g, b, a = px[x, y]
        if a and not (r > g + 25 and r > b + 40):      # tout ce qui n'est pas doré
            px[x, y] = (246, 239, 226, a)
        elif a:
            px[x, y] = (205, 179, 140, a)
logo.save(f'{HERE}/logo-clair.png')
segno.make(URL, error='h').save(f'{HERE}/qr.svg', scale=10, border=0, dark='#0d3122', light=None)

fonts = ''.join(f"@font-face{{font-family:'InterPrint';font-weight:{w};src:url(\"{b64(os.path.join(FONTS, 'Inter-' + n + '.ttf'), 'font/ttf')}\") format('truetype')}}\n"
                for w, n in [(500, 'Medium'), (700, 'Bold'), (800, 'ExtraBold')])
BASE = f'''{fonts}*{{margin:0;padding:0;box-sizing:border-box}}
body{{font-family:InterPrint;background:transparent;-webkit-print-color-adjust:exact;print-color-adjust:exact}}'''
LOGO = b64(f'{HERE}/logo-clair.png', 'image/png')

# Devant : 28 × 21 cm, centré sur la poitrine.
FRONT = (280, 210)
front = f'''<!doctype html><html lang="fr"><head><meta charset="utf-8"><style>@page{{size:{FRONT[0]}mm {FRONT[1]}mm;margin:0}}{BASE}
.f{{width:{FRONT[0]}mm;height:{FRONT[1]}mm;display:flex;flex-direction:column;align-items:center;text-align:center;color:{CREAM}}}
.f img{{height:72mm}}
.f .ask{{margin-top:8mm;font-size:34mm;font-weight:800;white-space:nowrap;letter-spacing:-.03em;line-height:1;color:{GOLD}}}
.f .how{{margin-top:7mm;font-size:17.5mm;font-weight:700;letter-spacing:-.02em;line-height:1.12}}
</style></head><body><div class="f"><img src="{LOGO}" alt=""><p class="ask">Demandez-moi</p><p class="how">comment ne plus rien oublier<br>pour votre cheval.</p></div></body></html>'''

# Dos : 30 × 40 cm. Le QR code est posé sur un carré crème pour rester lisible (un QR code clair sur fond
# foncé n'est pas lu par tous les téléphones). 15,5 cm de côté : lisible à 1,5–2 m.
BACK = (300, 400)
back = f'''<!doctype html><html lang="fr"><head><meta charset="utf-8"><style>@page{{size:{BACK[0]}mm {BACK[1]}mm;margin:0}}{BASE}
.b{{width:{BACK[0]}mm;height:{BACK[1]}mm;display:flex;flex-direction:column;align-items:center;text-align:center;color:{CREAM}}}
.b h1{{font-size:27mm;font-weight:800;letter-spacing:-.035em;line-height:1.02}}
.b h1 em{{font-style:normal;color:{GOLD}}}
.b .qr{{margin-top:16mm;width:175mm;height:175mm;background:{CREAM};border-radius:12mm;padding:10mm}}
.b .qr img{{width:100%;height:100%;display:block}}
.b .scan{{margin-top:8mm;font-size:20mm;font-weight:800;letter-spacing:-.02em}}
.b .site{{margin-top:2mm;font-size:13mm;font-weight:500;color:{GOLD};letter-spacing:.02em}}
</style></head><body><div class="b"><h1>Toute la vie<br>de votre cheval,<br><em>dans votre poche.</em></h1>
<div class="qr"><img src="{b64(HERE + '/qr.svg', 'image/svg+xml')}" alt=""></div><p class="scan">Scannez-moi&nbsp;!</p><p class="site">jackcie.fr</p></div></body></html>'''
open(f'{HERE}/devant.html', 'w').write(front)
open(f'{HERE}/dos.html', 'w').write(back)

# Simulation : silhouette de t-shirt (SVG), taille L à plat ≈ 55 × 74 cm → 6,5 unités par cm.
U = 6.5
BODY = 'M205 48 C232 92 368 92 395 48 L478 70 L590 160 L538 248 L482 214 L486 530 Q300 540 114 530 L118 214 L62 248 L10 160 L122 70 Z'
BODY_BACK = 'M205 48 C240 62 360 62 395 48 L478 70 L590 160 L538 248 L482 214 L486 530 Q300 540 114 530 L118 214 L62 248 L10 160 L122 70 Z'
def tee(body, collar, art, w_cm, h_cm, top, label):
    w, h = w_cm * U, h_cm * U
    return f'''<figure><svg viewBox="0 0 600 560" xmlns="http://www.w3.org/2000/svg">
  <defs><linearGradient id="sh{label}" x1="0" x2="1"><stop offset="0" stop-color="#000" stop-opacity=".22"/><stop offset=".18" stop-color="#000" stop-opacity="0"/>
  <stop offset=".82" stop-color="#000" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity=".22"/></linearGradient>
  <radialGradient id="hl{label}" cx=".5" cy=".3" r=".6"><stop offset="0" stop-color="#fff" stop-opacity=".07"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></radialGradient>
  <filter id="tx{label}"><feTurbulence type="fractalNoise" baseFrequency="1.4" numOctaves="2" result="n"/><feColorMatrix type="saturate" values="0"/><feComponentTransfer><feFuncA type="table" tableValues="0 .06"/></feComponentTransfer><feComposite in2="SourceGraphic" operator="in"/></filter>
  <clipPath id="cl{label}"><path d="{body}"/></clipPath></defs>
  <path d="{body}" fill="{TEE}" stroke="#0a2a1d" stroke-width="2"/>
  <g clip-path="url(#cl{label})">
   <image href="{art}" x="{300 - w / 2}" y="{top}" width="{w}" height="{h}" opacity=".96"/>
   <rect width="600" height="560" fill="url(#sh{label})"/><rect width="600" height="560" fill="url(#hl{label})"/>
   <rect width="600" height="560" filter="url(#tx{label})"/>
   <path d="M150 300 C170 400 160 470 175 530 M452 320 C440 420 448 480 430 530" stroke="#000" stroke-opacity=".12" stroke-width="10" fill="none" style="filter:blur(6px)"/>
   <path d="M118 214 L122 70 M482 214 L478 70" stroke="#000" stroke-opacity=".18" stroke-width="3" fill="none"/>
  </g>
  {collar}
 </svg><figcaption>{label}</figcaption></figure>'''

COLLAR_F = '<path d="M205 48 C232 92 368 92 395 48" stroke="#0b2c1f" stroke-width="13" fill="none" stroke-linecap="round"/><path d="M205 48 C232 92 368 92 395 48" stroke="#1b4a36" stroke-width="3" fill="none" transform="translate(0 6)"/>'
COLLAR_B = '<path d="M205 48 C240 62 360 62 395 48" stroke="#0b2c1f" stroke-width="12" fill="none" stroke-linecap="round"/>'

open(f'{HERE}/simulation.html', 'w').write(f'''<!doctype html><html lang="fr"><head><meta charset="utf-8"><style>{BASE}
body{{background:#ece6db;width:1500px;padding:50px 40px 40px}}
.row{{display:flex;justify-content:center;gap:40px}}
figure{{width:680px;text-align:center}} svg{{width:100%;filter:drop-shadow(0 18px 24px rgba(0,0,0,.18))}}
figcaption{{margin-top:6px;font:700 22px InterPrint;color:#4f5751;letter-spacing:.2em;text-transform:uppercase}}
</style></head><body><div class="row">
{tee(BODY, COLLAR_F, 'devant.png', FRONT[0] / 10, FRONT[1] / 10, 128, 'Devant')}
{tee(BODY_BACK, COLLAR_B, 'dos.png', BACK[0] / 10, BACK[1] / 10, 112, 'Dos')}
</div></body></html>''')

subprocess.run(['node', f'{HERE}/print.mjs', HERE, OUT], check=True)
for f in ('devant.html', 'dos.html', 'simulation.html', 'qr.svg', 'logo-clair.png', 'devant.png', 'dos.png'):
    os.remove(os.path.join(HERE, f))
print('✓', OUT, URL)
