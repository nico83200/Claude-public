# Assemble la vidéo publicitaire 9:16 : décor animé + extraits réels de l'application dans l'écran du téléphone.
import subprocess, sys
P, SRC = sys.argv[1], sys.argv[2]
X, Y, W, H = 260, 580, 560, 1212           # écran du téléphone dans l'image 1080×1920
clips = [  # (début source, fin source, début dans la pub, fin dans la pub)
    (26.0, 33.5, 3.0, 7.0),     # accueil
    (38.8, 42.5, 7.0, 10.0),    # assistant : consigne tapée
    (49.1, 51.6, 10.0, 12.0),   # assistant : séance créée
    (72.6, 74.85, 12.0, 16.0),   # santé : rappels de suivi
    (100.3, 107.5, 16.0, 19.8), # budget, « Payé »
]
f = [f"[1:v]split={len(clips)}" + ''.join(f'[s{i}]' for i in range(len(clips)))]
for i, (a, b, oa, ob) in enumerate(clips):
    k = (ob - oa) / (b - a)
    f.append(f"[s{i}]trim=start={a}:end={b},setpts=(PTS-STARTPTS)*{k:.5f}+{oa}/TB,scale={W}:{H}:flags=lanczos,format=yuva420p,fade=in:st={oa}:d=0.18:alpha=1[c{i}]")
last = '0:v'
for i, (_, _, oa, ob) in enumerate(clips):
    f.append(f"[{last}][c{i}]overlay={X}:{Y}:enable='between(t,{oa},{ob - 0.001})':eof_action=pass[o{i}]")
    last = f'o{i}'
f.append(f"[{last}][2:v]overlay=0:0:enable='between(t,2.99,19.8)',format=yuv420p[v]")
subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-framerate', '30', '-i', P + '/frames/%04d.png', '-i', SRC, '-loop', '1', '-framerate', '30', '-i', P + '/bezel.png',
    '-f', 'lavfi', '-i', 'anullsrc=channel_layout=stereo:sample_rate=48000',
    '-filter_complex', ';'.join(f), '-map', '[v]', '-map', '3:a', '-t', '24', '-r', '30',
    '-c:v', 'libx264', '-preset', 'slow', '-crf', '19', '-profile:v', 'high', '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-b:a', '128k', '-shortest', '-movflags', '+faststart',
    P + '/jackcie-pub-9x16.mp4'], check=True)
print('ok')
