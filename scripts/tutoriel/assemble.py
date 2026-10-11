# Assemble les images capturées en vidéo MP4 (H.264, 30 i/s), de la première à la dernière scène.
import json, subprocess, sys
d = sys.argv[1]
m = json.load(open(d + '/marks.json'))
frames, marks = m['frames'], m['marks']
start, end = marks[0]['start'], marks[-1]['end']
# image affichée au début : la dernière capturée avant le début de la première scène
sel = [f for f in frames if f['t'] <= start][-1:] + [f for f in frames if start < f['t'] < end]
lines = ['ffconcat version 1.0']
for i, f in enumerate(sel):
    t = max(f['t'], start)
    nxt = sel[i + 1]['t'] if i + 1 < len(sel) else end
    lines += [f"file '{f['file']}'", f"duration {max(nxt - t, 0.001):.4f}"]
lines.append(f"file '{sel[-1]['file']}'")
open(d + '/frames.ffconcat', 'w').write('\n'.join(lines))
subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-f', 'concat', '-safe', '0', '-i', d + '/frames.ffconcat',
    '-vf', 'fps=30,scale=780:1688:flags=lanczos,format=yuv420p', '-c:v', 'libx264', '-preset', 'slow', '-crf', '24',
    '-tune', 'animation', '-movflags', '+faststart', '-an', d + '/tutoriel-mobile.mp4'], check=True)
# image d'aperçu : l'accueil (troisième scène)
t = marks[2]['start'] - start + 3
subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-ss', f'{t:.2f}', '-i', d + '/tutoriel-mobile.mp4', '-frames:v', '1', '-q:v', '3', d + '/tutoriel-mobile.jpg'], check=True)
print(len(sel), 'images, durée', round(end - start, 1), 's')
