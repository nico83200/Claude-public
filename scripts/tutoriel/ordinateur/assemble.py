# Tutoriel ordinateur : images capturées → scènes ajustées à la durée exacte de leur paragraphe → voix off intégrée.
import json, subprocess, sys
out, voix, durees = sys.argv[1], sys.argv[2], sys.argv[3]
m = json.load(open(out + '/marks.json')); frames, marks = m['frames'], m['marks']
D = json.load(open(durees))['durations']
start, end = marks[0]['start'], marks[-1]['end']
sel = [f for f in frames if f['t'] <= start][-1:] + [f for f in frames if start < f['t'] < end]
lines = ['ffconcat version 1.0']
for i, f in enumerate(sel):
    t = max(f['t'], start); nxt = sel[i + 1]['t'] if i + 1 < len(sel) else end
    lines += [f"file '{f['file']}'", f"duration {max(nxt - t, 0.001):.4f}"]
lines.append(f"file '{sel[-1]['file']}'")
open(out + '/frames.ffconcat', 'w').write('\n'.join(lines))
# 1) vidéo brute au rythme du tournage
subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-f', 'concat', '-safe', '0', '-i', out + '/frames.ffconcat',
    '-vf', 'fps=30,scale=1920:1080:flags=lanczos,format=yuv420p', '-c:v', 'libx264', '-preset', 'fast', '-crf', '16', out + '/brut.mp4'], check=True)
# 2) chaque scène ramenée à la durée de son paragraphe, puis la voix
parts = []
for i, k in enumerate(marks):
    L = k['end'] - k['start']; T = D[k['id']]
    parts.append(f"[0:v]trim=start={k['start'] - start:.3f}:end={k['end'] - start:.3f},setpts=(PTS-STARTPTS)*{T / L:.5f}[v{i}]")
    print(f"{k['id']:<11} {L:5.2f}s → {T:5.2f}s")
graph = ';'.join(parts) + ';' + ''.join(f'[v{i}]' for i in range(len(marks))) + f'concat=n={len(marks)}:v=1:a=0,fps=30,format=yuv420p[v];[1:a]apad=pad_dur=1.2[a]'
subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-i', out + '/brut.mp4', '-i', voix, '-filter_complex', graph, '-map', '[v]', '-map', '[a]',
    '-c:v', 'libx264', '-preset', 'slow', '-crf', '23', '-tune', 'animation', '-c:a', 'aac', '-b:a', '96k', '-ac', '1', '-shortest', '-movflags', '+faststart',
    out + '/tutoriel-ordinateur.mp4'], check=True)
# image d'aperçu : accueil
t = sum(D[k] for k in ['intro', 'notifs']) + 2.5
subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-ss', f'{t:.2f}', '-i', out + '/tutoriel-ordinateur.mp4', '-frames:v', '1', '-q:v', '3', out + '/tutoriel-ordinateur.jpg'], check=True)
print('ok')
