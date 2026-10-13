# Recale les scènes sur la voix off enregistrée : les 13 plus longues pauses séparent les 14 paragraphes.
# Usage : python3 scripts/nouveautes-2.13/caler-voix.py voix.mp3
# Puis : node scripts/nouveautes-2.13/render.mjs <captures> <sortie> ; ffmpeg (voir README) ; textes.py pour les sous-titres.
import json, os, re, subprocess, sys
here = os.path.dirname(os.path.abspath(__file__))
voice = sys.argv[1]
sc = json.load(open(os.path.join(here, 'scenes.json')))
log = subprocess.run(['ffmpeg', '-i', voice, '-af', 'silencedetect=noise=-38dB:d=0.35', '-f', 'null', '-'], capture_output=True, text=True).stderr
starts = [float(x) for x in re.findall(r'silence_start: ([\d.]+)', log)]
ends = [float(x) for x in re.findall(r'silence_end: ([\d.]+)', log)]
total = float(re.search(r'Duration: (\d+):(\d+):([\d.]+)', log).groups()[2]) + 60 * int(re.search(r'Duration: (\d+):(\d+)', log).group(2))
gaps = sorted([(e - s, s, e) for s, e in zip(starts, ends) if s > 0.5 and e < total - 0.5], reverse=True)[:len(sc) - 1]
cuts = sorted((s + e) / 2 for _, s, e in gaps)
if len(cuts) != len(sc) - 1:
    sys.exit(f'{len(cuts) + 1} paragraphes détectés pour {len(sc)} scènes : marquez des pauses plus nettes entre les paragraphes.')
OFFSET = 0.6  # la voix commence 0,6 s après le début de la vidéo
bounds = [0] + cuts + [total]
for i, s in enumerate(sc):
    s['start'] = round(OFFSET + bounds[i], 2)
    s['dur'] = round(bounds[i + 1] - bounds[i], 2)
sc[-1]['dur'] = round(sc[-1]['dur'] + 1.5, 2)  # le logo de fin reste un peu après la voix
json.dump(sc, open(os.path.join(here, 'scenes.json'), 'w'), ensure_ascii=False, indent=1)
print('Scènes recalées :', ', '.join(f"{s['id']} {s['dur']}s" for s in sc))
