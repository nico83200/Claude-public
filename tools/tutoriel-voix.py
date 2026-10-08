#!/usr/bin/env python3
"""
Montage de la vidéo tutoriel avec sa voix off.

    python3 tools/tutoriel-voix.py <video.webm> <phrases.json> <sortie.mp4> <audio1.mp3> [audio2.mp3 …] [--fin=remerciement.mp3]

<video.webm>   : enregistrement de tools/tutoriel-salarie.mjs (lancé avec VOIX=durees.json pour que le rythme suive la voix).
<phrases.json> : une entrée par phrase de la voix off, dans l'ordre : l'accueil (sur l'écran titre) puis un sous-titre par phrase :
                 [{"src": 0, "a": 0.0, "b": 3.2}, {"src": 0, "a": 3.4, "b": 11.7}, …]
                 src = numéro du fichier audio (0 pour audio1), a / b = début et fin de la phrase dans ce fichier (secondes).

--fin : phrase de conclusion, dite sur l'écran final « À vous de jouer ! » (prolongé si la phrase est plus longue que lui).

Le numéro du sous-titre affiché est lu dans l'image (repère de 7 carrés en bas à gauche, voir tutoriel-salarie.mjs) :
chaque phrase démarre exactement à l'apparition de son sous-titre, quelles que soient les irrégularités de l'enregistrement ;
si une phrase dépasse son passage dans la vidéo, la dernière image du passage est figée le temps nécessaire.
Le repère est ensuite effacé, le volume harmonisé (-16 LUFS) et la vidéo encodée en H.264 + AAC.
"""
import json
import subprocess
import sys

import numpy as np

INTRO_MARK = 51      # repère de l'écran titre (phrase d'accueil)
INTRO_DELAY = 0.8    # l'accueil commence peu après l'apparition du titre
STEP_DELAY = 0.15    # chaque phrase commence juste après son sous-titre


def run(*cmd: str) -> bytes:
    return subprocess.check_output(cmd)


def marks(video: str) -> tuple[dict[int, float], float]:
    """Première apparition de chaque numéro de repère, et instant de la dernière image portant un repère (secondes)."""
    pts = [float(x) for x in run('ffprobe', '-v', 'error', '-select_streams', 'v', '-show_entries', 'frame=pts_time', '-of', 'csv=p=0', video).split()]
    raw = run('ffmpeg', '-v', 'error', '-i', video, '-vf', 'crop=84:12:0:708,format=gray', '-f', 'rawvideo', '-')
    frames = np.frombuffer(raw, np.uint8).reshape(-1, 12, 84)
    first: dict[int, float] = {}
    last = 0.0
    for i in range(min(len(frames), len(pts))):
        c = frames[i, 6, 6::12].astype(int)  # centre de chacun des 7 carrés
        if c[0] < 200 or any(80 < v < 175 for v in c):
            continue  # pas de repère, ou image de transition
        v = sum(1 << (k - 1) for k in range(1, 7) if c[k] > 128)
        last = pts[i]
        if v and v not in first:
            first[v] = pts[i]
    return first, last


def main() -> None:
    args = sys.argv[1:]
    outro = next((a[6:] for a in args if a.startswith('--fin=')), None)
    video, plan_file, out, *audios = [a for a in args if not a.startswith('--fin=')]
    plan = json.load(open(plan_file))
    m, last_mark = marks(video)
    missing = [i for i in range(1, len([p for p in plan if not p.get('outro')])) if i not in m] + ([] if INTRO_MARK in m else [INTRO_MARK])
    if missing:
        sys.exit(f'Repères introuvables dans la vidéo : {missing}')
    duration = float(run('ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', video))
    if outro:
        plan = plan + [{'src': len(audios), 'a': 0.0, 'b': float(run('ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', outro)), 'outro': True}]
        audios = audios + [outro]
    steps = len([p for p in plan if not p.get('outro')]) - 1
    dur = lambda i: plan[i]['b'] - plan[i]['a']

    # Une phrase plus longue que son passage dans la vidéo (l'enregistrement du navigateur prend parfois du retard
    # sur une page lourde) : on fige la dernière image du passage le temps nécessaire. La voix ne prend ainsi jamais
    # de retard sur les sous-titres. Coupures : apparition de chaque sous-titre, puis fin des sous-titres.
    cuts = [m[i] for i in range(1, steps + 1)] + [last_mark]
    starts = [m[INTRO_MARK] + INTRO_DELAY] + [m[i] + STEP_DELAY for i in range(1, steps + 1)]
    holds = []
    for k, c in enumerate(cuts):
        need = starts[k] + dur(k) + 0.3 - c  # la phrase k doit finir avant la coupure suivante
        holds.append(round(need, 2) if need > 0.05 else 0.0)
    shift = lambda t: t + sum(h for c, h in zip(cuts, holds) if c <= t + 1e-6)
    if any(holds):
        print('images figées :', ', '.join(f'{h:.1f} s avant la phrase {k + 1}' for k, h in enumerate(holds) if h))
    duration += sum(holds)
    extend = 0.0
    if outro:
        # Conclusion sur l'écran final (qui suit la dernière image portant un repère) ; l'écran est prolongé si besoin
        end = shift(last_mark) + 1.0 + dur(len(plan) - 1) + 1.5
        extend = max(0.0, end - duration)
        duration += extend

    parts, labels = [], []
    for i, p in enumerate(plan):
        t = shift(last_mark) + 1.0 if p.get('outro') else shift(m[INTRO_MARK]) + INTRO_DELAY if i == 0 else shift(m[i]) + STEP_DELAY
        d = dur(i)
        ms = int(t * 1000)
        parts.append(f"[{p['src'] + 1}:a]atrim={p['a']}:{p['b']},asetpts=PTS-STARTPTS,afade=t=in:d=0.04,"
                     f"afade=t=out:st={max(0.0, d - 0.08):.2f}:d=0.08,aresample=48000,adelay={ms}|{ms}[c{i}]")
        labels.append(f'[c{i}]')
    parts.append(''.join(labels) + f'amix=inputs={len(labels)}:normalize=0,apad,atrim=0:{duration:.2f},loudnorm=I=-16:TP=-1.5:LRA=11[a]')
    # Vidéo découpée aux coupures, image figée où il le faut, puis recollée
    bounds = [0.0] + cuts + [float(run('ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', video))]
    segs = []
    for j in range(len(bounds) - 1):
        h = holds[j] if j < len(holds) else 0.0
        parts.append(f'[s{j}in]trim=start={bounds[j]:.3f}:end={bounds[j + 1]:.3f},setpts=PTS-STARTPTS'
                     + (f',tpad=stop_mode=clone:stop_duration={h:.2f}' if h else '') + f'[s{j}]')
        segs.append(f'[s{j}]')
    parts.insert(0, f'[0:v]split={len(segs)}' + ''.join(f'[s{j}in]' for j in range(len(segs))))
    # Repère effacé : recouvert par le fond pris sur la même ligne, juste à droite
    parts.append(''.join(segs) + f'concat=n={len(segs)}:v=1:a=0,tpad=stop_mode=clone:stop_duration={extend:.2f},split[v0][v1];[v1]crop=90:14:100:706[patch];[v0][patch]overlay=0:706,'
                 f'fade=t=in:st=0:d=0.6,fade=t=out:st={duration - 1.1:.2f}:d=1,format=yuv420p[v]')

    inputs = ['-i', video] + [x for a in audios for x in ('-i', a)]
    subprocess.run(['ffmpeg', '-loglevel', 'error', '-y', *inputs, '-filter_complex', ';\n'.join(parts),
                    '-map', '[v]', '-map', '[a]', '-c:v', 'libx264', '-preset', 'slow', '-crf', '23',
                    '-c:a', 'aac', '-b:a', '160k', '-ac', '2', '-movflags', '+faststart', out], check=True)
    print(f'{out} : {duration:.0f} s, {len(plan)} phrases calées')


if __name__ == '__main__':
    main()
