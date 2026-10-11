# Mixage de la publicité : voix placée scène par scène + musique atténuée sous la voix, -14 LUFS.
import subprocess, sys
P = sys.argv[1]; A = P + '/audio'
# (début, fin) de chaque phrase dans l'enregistrement → instant de départ dans la publicité
segments = [
    (0.00, 2.72, 0.20),    # Le carnet de votre cheval tient enfin dans votre poche.   (accroche)
    (2.72, 5.80, 3.00),    # Soins, séances, rendez-vous : tout est au même endroit.  (accueil)
    (5.80, 9.50, 7.05),    # Dites simplement « cours demain… »… c'est noté.          (assistant)
    (9.50, 12.55, 12.10),  # Le maréchal et les vaccins ? Rappelés automatiquement.   (santé)
    (12.55, 14.45, 16.15), # Et la pension, toujours à jour.                          (budget)
    (14.45, 18.36, 20.00), # Jack et Cie. Essai gratuit de quatorze jours, lien en bio ! (fin)
]
f = [f"[1:a]aresample=48000,asplit={len(segments)}" + ''.join(f'[v{i}]' for i in range(len(segments)))]
for i, (a, b, at) in enumerate(segments):
    ms = int(at * 1000)
    f.append(f"[v{i}]atrim={a}:{b},asetpts=PTS-STARTPTS,afade=t=in:d=0.03,afade=t=out:st={b - a - 0.05:.2f}:d=0.05,adelay={ms}|{ms}[w{i}]")
f.append(''.join(f'[w{i}]' for i in range(len(segments))) + f"amix=inputs={len(segments)}:normalize=0,apad=whole_dur=24.6,loudnorm=I=-16:TP=-2:LRA=7,aresample=48000,asplit=2[voice][key]")
f.append("[2:a]aresample=48000,volume=-7dB,apad=whole_dur=24.6[music]")
f.append("[music][key]sidechaincompress=threshold=0.03:ratio=6:attack=15:release=350:makeup=1[duck]")
f.append("[voice][duck]amix=inputs=2:normalize=0,loudnorm=I=-14:TP=-1.5:LRA=9,aresample=48000[a]")
# Vidéo : dernière image tenue 0,6 s de plus pour laisser finir « lien en bio »
f.append("[0:v]tpad=stop_mode=clone:stop_duration=0.6[v]")
subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-i', P + '/jackcie-pub-9x16-muet.mp4', '-i', A + '/voix.mp3', '-i', A + '/musique1.mp3',
    '-filter_complex', ';'.join(f), '-map', '[v]', '-map', '[a]', '-t', '24.6',
    '-c:v', 'libx264', '-preset', 'slow', '-crf', '19', '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-b:a', '160k', '-ar', '48000', '-movflags', '+faststart',
    P + '/jackcie-pub-9x16.mp4'], check=True)
print('ok')
