# Sous-titres WebVTT et texte de voix off minuté, à partir des repères du tournage.
import json, re, sys
d = sys.argv[1]
marks = json.load(open(d + '/marks.json'))['marks']
scenes = {s['id']: s for s in json.load(open(sys.argv[2]))}
offset = marks[0]['start']  # la vidéo finale commence à la première scène
WPS = 2.5
def ts(t):
    h, r = divmod(t, 3600); m, s = divmod(r, 60)
    return f"{int(h):02d}:{int(m):02d}:{s:06.3f}"
def short(t):
    m, s = divmod(int(round(t)), 60); return f"{m}:{s:02d}"
vtt = ["WEBVTT", ""]
script = []
n = 1
for mk in marks:
    sc = scenes[mk['id']]
    start = mk['start'] - offset + 0.3
    sentences = [x.strip() for x in re.split(r'(?<=[.!?:])\s+(?=[A-ZÀ-ÖØ-Ý«])', sc['text']) if x.strip()]
    # découpe des phrases longues pour l'affichage (2 lignes max)
    cues = []
    for s in sentences:
        words = s.split()
        while len(words) > 16:
            cut = 14
            cues.append(' '.join(words[:cut])); words = words[cut:]
        cues.append(' '.join(words))
    t = start
    for c in cues:
        dur = len(c.split()) / WPS
        vtt += [str(n), f"{ts(t)} --> {ts(t + dur)}", c, ""]
        n += 1; t += dur
    script.append((short(mk['start'] - offset), short(mk['end'] - offset), sc['title'], sc['text'], len(sc['text'].split()) / WPS, mk['end'] - mk['start']))
open(d + '/tutoriel-mobile.vtt', 'w').write('\n'.join(vtt))
total = marks[-1]['end'] - offset
md = [f"# Voix off — tutoriel Jack&Cie (mobile)", "", f"Durée de la vidéo : {short(total)}. Débit visé : environ 150 mots par minute, ton chaleureux et posé.", ""]
for a, b, title, text, vs, ss in script:
    md += [f"## {a} – {b} · {title}", "", text, "", f"_(≈ {vs:.0f} s de voix pour {ss:.0f} s d'images)_", ""]
open(d + '/voix-off.md', 'w').write('\n'.join(md))
print(f"{n-1} sous-titres, durée {short(total)}")
