# Texte de la voix off (à enregistrer) et sous-titres WebVTT à partir de scenes.json.
# Usage : python3 scripts/nouveautes-2.13/textes.py <dossier-sortie>
import json, re, sys, os
here = os.path.dirname(os.path.abspath(__file__))
out = sys.argv[1]
os.makedirs(out, exist_ok=True)
sc = json.load(open(os.path.join(here, 'scenes.json')))
def ts(t):
    h, r = divmod(t, 3600); m, s = divmod(r, 60)
    return f"{int(h):02d}:{int(m):02d}:{s:06.3f}"
vtt = ["WEBVTT", ""]
txt = ["Voix off — Nouveautés Jack&Cie 2.13", "Ton chaleureux et posé. Une pause d'environ une demi-seconde entre deux paragraphes.", ""]
n = 1
for s in sc:
    txt.append(f"[{ts(s['start'])[3:8]}] {s['text']}")
    txt.append("")
    parts = [p.strip() for p in re.split(r'(?<=[.!?])\s+', s['text']) if p.strip()]
    words = [len(re.findall(r"\w+", p)) for p in parts]
    total, t = sum(words), s['start']
    for p, w in zip(parts, words):
        d = (s['dur'] - 0.4) * w / total
        vtt += [str(n), f"{ts(t)} --> {ts(t + d)}", p, ""]
        t += d; n += 1
open(os.path.join(out, 'jackcie-nouveautes-2.13.vtt'), 'w').write("\n".join(vtt))
open(os.path.join(out, 'voix-off-nouveautes-2.13.txt'), 'w').write("\n".join(txt))
print(n - 1, 'sous-titres')
