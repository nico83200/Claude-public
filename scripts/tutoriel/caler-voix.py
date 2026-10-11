# Recale le tutoriel sur la voix off enregistrée : chaque scène dure le temps de son paragraphe.
# Usage : python3 caler-voix.py <dossier> — <dossier>/tuto/out contient le tournage (vidéo muette + marks.json),
# <dossier>/voix contient voix.mp3 et sil.txt (pauses détectées) :
#   ffmpeg -i voix.mp3 -af silencedetect=noise=-38dB:d=0.25 -f null - 2>&1 | grep -o "silence_\(start\|end\): [0-9.]*" | awk '{print $2}' | paste - - > sil.txt
# La liste `units` doit compter exactement un élément par morceau de parole (entre deux pauses).
import json, subprocess, sys
SP = sys.argv[1]
V = SP + '/voix'
src = SP + '/tuto/out/tutoriel-mobile.mp4'   # vidéo muette d'origine (scènes au rythme 2,5 mots/s)
marks = json.load(open(SP + '/tuto/out/marks.json'))['marks']
s0 = marks[0]['start']
sil = [tuple(map(float, l.split())) for l in open(V + '/sil.txt') if l.strip()]
AUDIO_END = float(subprocess.check_output(['ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', V + '/voix.mp3']))
# Morceaux de parole (entre deux pauses) et texte correspondant, dans l'ordre
chunks, t = [], 0.0
for a, b in sil:
    chunks.append((t, a)); t = b
chunks.append((t, AUDIO_END))
units = [  # (scène, texte) — 30 phrases ou propositions, une par morceau de parole
 ('intro', "Bienvenue dans Jack et Cie !"), ('intro', "En deux minutes, voyons comment suivre toute la vie de votre cheval depuis votre téléphone."),
 ('notifs', "À la première ouverture, l'application vous demande d'activer les notifications."), ('notifs', "Elles vous préviennent au bon moment :"), ('notifs', "le bilan après un cours, le passage du maréchal, un vaccin ou le paiement de la pension."),
 ('accueil', "Voici l'accueil."), ('accueil', "En haut, ce qui est prévu aujourd'hui."), ('accueil', "Juste en dessous, les prochaines échéances de soins."), ('accueil', "Et plus bas, vos chevaux."),
 ('assistant', "Le plus rapide, c'est l'assistant."), ('assistant', "Touchez le micro au centre, puis dites simplement ce que vous voulez,"), ('assistant', "par exemple : « Cours collectif demain à seize heures avec Jimmy sur Uni »."), ('assistant', "Ici, je l'écris au clavier."),
 ('assistant2', "L'assistant crée le cours et l'ajoute au calendrier."), ('assistant2', "Il s'est trompé ?"), ('assistant2', "Touchez « Annuler » : rien n'est perdu."),
 ('cheval', "Touchez un cheval pour ouvrir son dossier."), ('cheval', "Les boutons rapides ajoutent une séance, un soin ou signalent une observation."), ('cheval', "Les onglets regroupent la santé, les séances, l'alimentation, les documents et les dépenses."),
 ('sante', "Dans l'onglet Santé, retrouvez les soins et les traitements."), ('sante', "Le maréchal et les vaccins vous sont rappelés automatiquement, à la fréquence que vous choisissez."),
 ('bilan', "Après un cours, une notification vous propose d'en faire le bilan :"), ('bilan', "notez les exercices travaillés, votre ressenti, et ce qu'il faudra retravailler la prochaine fois."),
 ('calendrier', "Le calendrier rassemble tout :"), ('calendrier', "séances, cours, rendez-vous et soins, jour par jour."),
 ('budget', "Côté budget, notez vos dépenses, y compris celles qui reviennent chaque mois, comme la pension."), ('budget', "À chaque échéance, un rappel ; et un simple toucher sur « Payé » suffit pour suivre vos paiements."),
 ('menu', "Enfin, le menu donne accès au partage avec un demi-pensionnaire, à vos réglages, et à ces tutoriels, à revoir quand vous le souhaitez."),
 ('outro', "C'est à vous !"), ('outro', "Bonne route avec Jack et Cie."),
]
assert len(units) == len(chunks), (len(units), len(chunks))
# Début de chaque scène dans la voix = début de son premier morceau (la pause précédente reste à la scène d'avant)
order = [m['id'] for m in marks]
start_of = {}
for (scene, _), (a, b) in zip(units, chunks):
    start_of.setdefault(scene, a)
TAIL = 1.2  # dernière image tenue après la voix
bounds = [0.0] + [start_of[s] for s in order[1:]] + [AUDIO_END + TAIL]
parts, inputs = [], []
for i, m in enumerate(marks):
    L = m['end'] - m['start']; T = bounds[i + 1] - bounds[i]
    parts.append(f"[0:v]trim=start={m['start'] - s0:.3f}:end={m['end'] - s0:.3f},setpts=(PTS-STARTPTS)*{T / L:.5f}[v{i}]")
    print(f"{m['id']:<11} {L:5.1f}s → {T:5.1f}s (×{L / T:.2f})")
graph = ';'.join(parts) + ';' + ''.join(f'[v{i}]' for i in range(len(marks))) + f'concat=n={len(marks)}:v=1:a=0,fps=30,format=yuv420p[v];[1:a]apad=pad_dur={TAIL}[a]'
out = V + '/tutoriel-mobile.mp4'
subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-i', src, '-i', V + '/voix.mp3', '-filter_complex', graph, '-map', '[v]', '-map', '[a]',
    '-c:v', 'libx264', '-preset', 'slow', '-crf', '24', '-tune', 'animation', '-c:a', 'aac', '-b:a', '96k', '-ac', '1', '-shortest', '-movflags', '+faststart', out], check=True)
# Sous-titres calés sur la voix (les phrases longues sont coupées en deux à la virgule la plus centrale)
def ts(t):
    m, s = divmod(t, 60); return f"00:{int(m):02d}:{s:06.3f}"
cues = []
for (scene, text), (a, b) in zip(units, chunks):
    if len(text) > 80:
        commas = [i for i, c in enumerate(text) if c in ',;:' and 15 < i < len(text) - 15]
        if commas:
            k = min(commas, key=lambda i: abs(i - len(text) / 2)) + 1
            first, second = text[:k].strip(), text[k:].strip()
            mid = a + (b - a) * len(first) / (len(first) + len(second))
            cues += [(a, mid, first), (mid, b, second)]
            continue
    cues.append((a, b, text))
vtt = ['WEBVTT', '']
for n, (a, b, text) in enumerate(cues, 1):
    nxt = cues[n][0] if n < len(cues) else AUDIO_END
    vtt += [str(n), f"{ts(a)} --> {ts(min(b + 0.25, nxt, AUDIO_END))}", text, '']
open(V + '/tutoriel-mobile.vtt', 'w').write('\n'.join(vtt))
print(len(cues), 'sous-titres ; durée', round(AUDIO_END + TAIL, 1), 's')
