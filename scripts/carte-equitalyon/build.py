# Cartes « teaser » EquitaLyon (format carte de visite 85 × 55 mm), sans nom d'application :
# 4 accroches, chacune avec sa source de suivi. Planche A4 recto-verso prête à imprimer (8 cartes,
# 2 par accroche, fonds perdus 3 mm, traits de coupe), verso aligné pour un retournement bord long.
# Usage : python3 scripts/carte-equitalyon/build.py  →  dist/carte-equitalyon/
import base64, html, os, subprocess, segno
HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.join(HERE, '..', '..')
FONTS = os.path.join(HERE, '..', 'affiche', 'fonts')
OUT = os.path.join(ROOT, 'dist', 'carte-equitalyon'); os.makedirs(OUT, exist_ok=True)
CODE = 'EQUITA2026'
IDENT = 'NLapps · EI N. Lefèvre · SIRET 519 791 719 00114 · Ne pas jeter sur la voie publique'

VERSIONS = [
    ('carte-poche', 'Votre cheval a un carnet de santé.', 'Et s\'il tenait dans votre poche ?'),
    ('carte-oubli', 'Vaccins, maréchal, séances, dépenses…', 'Vous oubliez quoi en ce moment ?'),
    ('carte-voix', '« Le maréchal vient mardi. »', 'Dites-le à voix haute. C\'est noté.'),
    ('carte-temps', 'Propriétaire de cheval ?', 'Scannez. Vous allez gagner du temps.'),
]

def b64(path, mime):
    return f'data:{mime};base64,' + base64.b64encode(open(path, 'rb').read()).decode()

def qr(src):
    path = os.path.join(HERE, f'qr-{src}.svg')
    segno.make(f'https://jackcie.fr/?code={CODE}&src={src}', error='q').save(path, scale=10, border=0, dark='#0d3122', light=None)
    data = b64(path, 'image/svg+xml'); os.remove(path)
    return data

fonts = ''.join(f"@font-face{{font-family:'InterPrint';font-weight:{w};src:url(\"{b64(os.path.join(FONTS, 'Inter-' + n + '.ttf'), 'font/ttf')}\") format('truetype')}}\n"
                for w, n in [(400, 'Regular'), (500, 'Medium'), (600, 'SemiBold'), (700, 'Bold'), (800, 'ExtraBold')])
GIFT = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12v8a1 1 0 01-1 1H5a1 1 0 01-1-1v-8M2.5 7.5h19v4.5h-19zM12 21V7.5M12 7.5H8a2.25 2.25 0 110-4.5c2.5 0 4 4.5 4 4.5zm0 0h4a2.25 2.25 0 100-4.5c-2.5 0-4 4.5-4 4.5z"/></svg>'
TURN = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h4v4M20 4l-5 5M8 20H4v-4M4 20l5-5"/></svg>'

# Planche A4 : 2 colonnes × 4 lignes de cellules 91 × 61 mm (carte 85 × 55 + 3 mm de fonds perdus).
CW, CH, B = 91, 61, 3
X0, Y0 = (210 - 2 * CW) / 2, (297 - 4 * CH) / 2
slots = [(r, c) for r in range(4) for c in range(2)]
# Chaque ligne porte une accroche (2 exemplaires par accroche).
assign = {(r, c): VERSIONS[r] for r, c in slots}

def recto(v):
    src, a, b = v
    return f'''<div class="card recto"><div class="arc"></div>
      <div class="safe"><p class="a">{html.escape(a)}</p><p class="b">{html.escape(b)}</p>
      <p class="turn">{TURN} Retournez la carte</p></div></div>'''

def verso(v):
    src, a, b = v
    return f'''<div class="card verso"><div class="safe">
      <div class="qr"><img src="{qr(src)}" alt=""></div>
      <div class="side"><p class="scan">Scannez&nbsp;!</p><p class="gift">{GIFT} 30 jours offerts</p><p class="event">Spécial EQUITA<span>LYON</span> 2026</p><p class="nocard">Sans carte bancaire</p></div>
      <p class="ident">{html.escape(IDENT)}</p></div></div>'''

def marks():
    # Traits de coupe dans les marges extérieures, alignés sur chaque bord de carte (format fini).
    out = []
    xs = sorted({X0 + c * CW + B for c in range(2)} | {X0 + c * CW + CW - B for c in range(2)})
    ys = sorted({Y0 + r * CH + B for r in range(4)} | {Y0 + r * CH + CH - B for r in range(4)})
    for x in xs:
        out.append(f'<i style="left:{x}mm;top:{Y0 - 7}mm;width:0;height:5mm"></i><i style="left:{x}mm;top:{Y0 + 4 * CH + 2}mm;width:0;height:5mm"></i>')
    for y in ys:
        out.append(f'<i style="top:{y}mm;left:{X0 - 7}mm;height:0;width:5mm"></i><i style="top:{y}mm;left:{X0 + 2 * CW + 2}mm;height:0;width:5mm"></i>')
    return ''.join(out)

def sheet(face):
    cells = []
    for r, c in slots:
        v = assign[(r, c)]
        # Verso : colonnes inversées pour tomber au dos de la bonne carte (retournement bord long).
        col = c if face == 'recto' else 1 - c
        cells.append(f'<div class="cell" style="left:{X0 + col * CW}mm;top:{Y0 + r * CH}mm">{recto(v) if face == "recto" else verso(v)}</div>')
    label = 'RECTO' if face == 'recto' else 'VERSO · retournement bord long'
    return f'<section class="page"><div class="marks">{marks()}</div>{"".join(cells)}<p class="label">Cartes EquitaLyon · {label} · coupe au trait, format fini 85 × 55 mm</p></section>'

def card_only(face, v):
    return f'<section class="single">{recto(v) if face == "recto" else verso(v)}</section>'

STYLE = f'''{fonts}
*{{margin:0;padding:0;box-sizing:border-box}}
body{{font-family:InterPrint,Inter,system-ui;-webkit-print-color-adjust:exact;print-color-adjust:exact}}
.page{{position:relative;width:210mm;height:297mm;overflow:hidden;page-break-after:always;background:#fff}}
.cell{{position:absolute;width:{CW}mm;height:{CH}mm}}
.marks i{{position:absolute;border-left:.15mm solid #000;border-top:.15mm solid #000}}
.label{{position:absolute;left:0;right:0;bottom:8mm;text-align:center;font-size:2.4mm;color:#888}}
.card{{position:relative;width:{CW}mm;height:{CH}mm;overflow:hidden}}
.safe{{position:absolute;left:{B + 4}mm;right:{B + 4}mm;top:{B + 4}mm;bottom:{B + 3.5}mm}}
.recto{{background:radial-gradient(120% 120% at 0% 0%,#1f5f44 0%,#0d3122 55%,#0a2519 100%);color:#fff}}
.recto .arc{{position:absolute;width:70mm;height:70mm;right:-30mm;top:-34mm;border-radius:50%;border:.5mm solid rgba(209,187,157,.35)}}
.recto .a{{font-size:3.6mm;font-weight:600;line-height:1.25;color:rgba(255,255,255,.82);max-width:62mm}}
.recto .b{{margin-top:2.2mm;font-size:6mm;font-weight:800;line-height:1.08;letter-spacing:-.02em;color:#e7dac3;max-width:70mm}}
.recto .turn{{position:absolute;left:0;bottom:0;display:flex;align-items:center;gap:1.2mm;font-size:2.4mm;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#d1bb9d}}
.recto .turn svg{{width:3.2mm;height:3.2mm}}
.verso{{background:#faf7f1;color:#0d3122}}
.verso .qr{{position:absolute;left:0;top:0;width:33mm;height:33mm;background:#fff;border-radius:2.4mm;padding:1.6mm;box-shadow:0 0 0 .3mm #e7dac3}}
.verso .qr img{{width:100%;height:100%;display:block}}
.verso .side{{position:absolute;left:37mm;top:1mm;right:0}}
.verso .scan{{font-size:6.2mm;font-weight:800;letter-spacing:-.02em;line-height:1}}
.verso .gift{{margin-top:2.4mm;display:flex;align-items:center;gap:1.2mm;font-size:3.4mm;font-weight:800;color:#a8875e}}
.verso .gift svg{{width:3.8mm;height:3.8mm}}
.verso .event{{margin-top:1.2mm;font-size:2.7mm;font-weight:700;letter-spacing:.04em}}
.verso .event span{{color:#a8875e}}
.verso .nocard{{margin-top:.8mm;font-size:2.5mm;color:#4f5751}}
.verso .ident{{position:absolute;left:0;right:0;bottom:-1mm;font-size:1.6mm;line-height:1.3;color:#7a817c}}
.single{{width:{CW}mm;height:{CH}mm;page-break-after:always}}
'''

def doc(body, w, h):
    return f'<!doctype html><html lang="fr"><head><meta charset="utf-8"><style>@page{{size:{w}mm {h}mm;margin:0}}{STYLE}</style></head><body>{body}</body></html>'

open(os.path.join(HERE, 'planche.html'), 'w').write(doc(sheet('recto') + sheet('verso'), 210, 297))
# Pour un imprimeur : chaque carte seule (91 × 61 mm avec fonds perdus), recto puis verso, 4 versions.
open(os.path.join(HERE, 'cartes.html'), 'w').write(doc(''.join(card_only('recto', v) + card_only('verso', v) for v in VERSIONS), CW, CH))
subprocess.run(['node', os.path.join(HERE, 'print.mjs'), HERE, OUT], check=True)
for f in ('planche.html', 'cartes.html'):
    os.remove(os.path.join(HERE, f))
print('✓', OUT)
