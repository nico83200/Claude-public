/*
 * Sous-titres de la vidéo tutoriel : fichier .srt (calé sur la vidéo) et texte de voix off minuté.
 * Appelé par tutoriel-salarie.mjs à la fin de l'enregistrement, ou à la main :
 *   node tools/tutoriel-sous-titres.mjs <sous-titres.json> [dossier-sortie] [décalage-secondes]
 */
import fs from 'fs';
import path from 'path';

const pad = (n, w = 2) => String(n).padStart(w, '0');
const srtTime = (s) => { const ms = Math.max(0, Math.round(s * 1000)); return `${pad(Math.floor(ms / 3600000))}:${pad(Math.floor(ms / 60000) % 60)}:${pad(Math.floor(ms / 1000) % 60)},${pad(ms % 1000, 3)}`; };
const clock = (s) => `${Math.floor(s / 60)}:${pad(Math.floor(s) % 60)}`;

export function writeCaptions(cues, dir, base = 'Centriva-tutoriel-salarie', offset = 0) {
  const list = cues.filter((c) => c.text && c.end != null).map((c) => ({ ...c, start: c.start + offset, end: c.end + offset }));
  // .srt : un sous-titre par étape, affiché tant que l'étape est à l'écran
  const srt = list.map((c, i) => `${i + 1}\n${srtTime(c.start)} --> ${srtTime(c.end - 0.05)}\n${c.kind === 'card' ? c.title + ' — ' + c.text : c.text}\n`).join('\n');
  fs.writeFileSync(path.join(dir, base + '.srt'), srt);
  // Texte de voix off : minutage, étape, phrase à lire, durée disponible
  const lines = [
    'CENTRIVA — TUTORIEL SALARIÉ',
    'Texte de la voix off (reprend les sous-titres de la vidéo)',
    '',
    'Chaque bloc indique le moment où la phrase apparaît à l\'écran (minutes:secondes) et le temps disponible pour la lire.',
    'Lisez posément : la durée indiquée laisse le temps de terminer la phrase avant l\'étape suivante.',
    '',
  ];
  let lastStep = null;
  for (const c of list) {
    const dur = Math.round(c.end - c.start);
    if (c.kind === 'card') {
      lines.push('', '════════════════════════════════════════', `[${clock(c.start)}]  ÉCRAN TITRE · ${dur} s`, `${c.title}`, `« ${c.text} »`, '════════════════════════════════════════');
      lastStep = null;
      continue;
    }
    if (c.n !== lastStep) lines.push('', `— Étape ${c.n} —`);
    lastStep = c.n;
    lines.push(`[${clock(c.start)}]  ${c.title} · ${dur} s`, `« ${c.text} »`, '');
  }
  fs.writeFileSync(path.join(dir, base + '-voix-off.txt'), lines.join('\n').replace(/\n{3,}/g, '\n\n') + '\n');
  return list.length;
}

if (process.argv[1] && import.meta.url.endsWith(path.basename(process.argv[1]))) {
  const [json, dir, offset] = process.argv.slice(2);
  const n = writeCaptions(JSON.parse(fs.readFileSync(json, 'utf8')), dir || path.dirname(json), undefined, parseFloat(offset || '0'));
  console.log(n, 'sous-titres écrits');
}
