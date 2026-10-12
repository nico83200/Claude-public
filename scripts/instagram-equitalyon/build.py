# Post Instagram (4:5, 1080×1350) et story (1080×1920) « Spécial EquitaLyon 2026 ».
# Usage : python3 scripts/instagram-equitalyon/build.py [code]
# Liens suivis (Administration › Statistiques et fiche du code) :
#   bio   : https://jackcie.nlapps.fr/register?code=EQUITA2026&src=instagram
#   story : https://jackcie.nlapps.fr/register?code=EQUITA2026&src=instagram-story
import sys, os, subprocess, html, base64
HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.join(HERE, '..', '..')
SRC = os.path.join(ROOT, 'scripts', 'affiche')
OUT = os.path.join(ROOT, 'dist', 'instagram-equitalyon'); os.makedirs(OUT, exist_ok=True)
CODE = sys.argv[1] if len(sys.argv) > 1 else 'EQUITA2026'
def b64(path):
    ext = os.path.splitext(path)[1].lower().lstrip('.'); mime = {'jpg': 'jpeg', 'png': 'png', 'ttf': 'ttf'}[ext]
    return f'data:{"font" if ext == "ttf" else "image"}/{mime};base64,' + base64.b64encode(open(path, 'rb').read()).decode()
fonts = ''.join(f"@font-face{{font-family:'InterP';font-weight:{w};src:url(\"{b64(SRC + '/fonts/Inter-' + n + '.ttf')}\") format('truetype')}}\n"
                for w, n in [(500, 'Medium'), (600, 'SemiBold'), (700, 'Bold'), (800, 'ExtraBold')])
LOGO, ICON, SCREEN = b64(ROOT + '/public/brand/jackcie-logo.png'), b64(ROOT + '/public/icons/icon-192.png'), b64(SRC + '/ecran.jpg')
CHECK = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>'
perks = ['Assistant vocal, santé et rappels', 'Séances et 125 exercices', 'Budget, factures et partage']
perks_html = ''.join(f'<li><span>{CHECK}</span>{html.escape(p)}</li>' for p in perks)

def page(w, h, story):
    return f'''<!doctype html><html lang="fr"><head><meta charset="utf-8"><style>
{fonts}
*{{margin:0;padding:0;box-sizing:border-box}}
html,body{{width:{w}px;height:{h}px;overflow:hidden}}
body{{font-family:InterP,Inter,system-ui;-webkit-font-smoothing:antialiased;color:#fff;position:relative;
 background:radial-gradient(120% 80% at 0% 0%,#1f5f44 0%,#0d3122 55%,#0a2519 100%)}}
.glow{{position:absolute;border-radius:50%;filter:blur(90px);opacity:.35;background:#c2a47e}}
.top{{position:absolute;left:72px;right:72px;top:{150 if story else 64}px;display:flex;justify-content:space-between;align-items:center}}
.brand{{display:flex;align-items:center;gap:16px;font-weight:800;font-size:34px;letter-spacing:-.02em}}
.brand img{{width:56px;height:56px;border-radius:22%}}
.chip{{border:2px solid #d1bb9d;color:#d1bb9d;border-radius:999px;padding:10px 22px;font-weight:800;font-size:24px;letter-spacing:.08em}}
.txt{{position:absolute;left:72px;right:72px;top:{330 if story else 175}px}}
.k{{font-weight:700;font-size:26px;letter-spacing:.24em;color:#d1bb9d}}
h1{{margin-top:{22 if story else 14}px;font-weight:800;font-size:{150 if story else 128}px;line-height:.95;letter-spacing:-.045em}}
h1 em{{font-style:normal;color:#d1bb9d}}
.b{{margin-top:{30 if story else 22}px;font-size:{36 if story else 31}px;line-height:1.3;font-weight:600;color:rgba(255,255,255,.9);max-width:{900 if story else 560}px}}
.b strong{{color:#fff}}
ul{{list-style:none;margin-top:{34 if story else 26}px;display:grid;gap:{18 if story else 14}px}}
li{{display:flex;align-items:center;gap:16px;font-size:{32 if story else 27}px;font-weight:600}}
li span{{flex:none;width:{44 if story else 38}px;height:{44 if story else 38}px;border-radius:50%;background:#d1bb9d;color:#0d3122;display:flex;align-items:center;justify-content:center}}
li svg{{width:60%;height:60%}}
.coupon{{position:absolute;left:72px;{'right:72px;top:1180px' if story else 'width:560px;top:875px'};background:#faf7f1;color:#0d3122;border-radius:34px;padding:{'34px 40px' if story else '28px 34px'};box-shadow:0 30px 70px rgba(0,0,0,.35)}}
.coupon small{{display:block;font-weight:700;font-size:22px;letter-spacing:.2em;color:#a8875e}}
.code{{margin-top:12px;display:inline-block;border:3px dashed #c2a47e;border-radius:18px;padding:{'12px 30px' if story else '8px 22px'};font-weight:800;font-size:{88 if story else 64}px;letter-spacing:.05em;line-height:1.05}}
.coupon p{{margin-top:14px;font-size:{27 if story else 23}px;line-height:1.35;font-weight:500;color:#3a413c}}
.cta{{position:absolute;left:72px;{'right:72px;top:1520px;justify-content:center' if story else 'bottom:64px'};display:flex;align-items:center;gap:14px;padding:22px 34px;border-radius:999px;background:#fff;color:#0d3122;font-weight:800;font-size:{34 if story else 28}px}}
.cta i{{font-style:normal;color:#a8875e}}
.phone{{position:absolute;{'display:none' if story else 'left:682px;top:440px'};width:400px;height:866px;border-radius:62px;background:#0b0f0d;padding:13px;box-shadow:0 40px 90px rgba(0,0,0,.45);transform:rotate(4deg)}}
.phone img{{width:100%;height:100%;object-fit:cover;object-position:top;border-radius:50px;display:block}}
.zone{{position:absolute;left:0;right:0;bottom:0;height:260px;display:{'flex' if story else 'none'};align-items:center;justify-content:center;color:rgba(255,255,255,.55);font-size:24px;font-weight:600}}
.small{{position:absolute;left:72px;right:72px;{'top:1650px' if story else 'bottom:150px;right:430px'};font-size:{22 if story else 18}px;line-height:1.35;color:rgba(255,255,255,.62);{'text-align:center' if story else ''}}}
</style></head><body>
<div class="glow" style="width:520px;height:520px;right:-180px;top:-160px"></div>
<div class="glow" style="width:420px;height:420px;left:-200px;bottom:-140px;opacity:.18"></div>
<div class="top"><div class="brand"><img src="{ICON}" alt="">Jack&amp;Cie</div><div class="chip">EQUITALYON 2026</div></div>
<div class="txt"><div class="k">OFFRE SPÉCIALE SALON</div><h1>30 jours<br><em>offerts</em></h1>
<p class="b"><strong>Toutes les fonctionnalités</strong>, sans carte bancaire. L'application qui réunit toute la vie de votre cheval.</p>
<ul>{perks_html}</ul></div>
<div class="phone"><img src="{SCREEN}" alt=""></div>
<div class="coupon"><small>VOTRE CODE</small><span class="code">{html.escape(CODE)}</span><p>Activé dès la création de votre compte.</p></div>
<div class="cta">{'👆 Touchez le lien ci-dessous' if story else '<i>➜</i> Lien en bio : créez votre compte'}</div>
<p class="small">Offre Propriétaire (un cheval) pendant 30 jours, une fois par compte. Sans engagement : ensuite, offre gratuite Découverte.</p>
</body></html>'''

for name, w, h, story in [('jackcie-equitalyon-post', 1080, 1350, False), ('jackcie-equitalyon-story', 1080, 1920, True)]:
    open(f'{HERE}/{name}.html', 'w').write(page(w, h, story))
subprocess.run(['node', HERE + '/render.mjs', HERE, OUT], check=True)
for name in ('jackcie-equitalyon-post', 'jackcie-equitalyon-story'):
    os.remove(f'{HERE}/{name}.html')
