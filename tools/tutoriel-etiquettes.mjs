/*
 * Vidéo tutoriel « Imprimer les étiquettes d'étagère » (données de démonstration).
 *   [VOIX=durees.json] PWPATH=… node tools/tutoriel-etiquettes.mjs <dossier-sortie> [url]
 * Compte salarié claire.secretaire@demo.fr ; article n° 1 (gants nitrile S) suivi en stock au centre Les Tilleuls.
 */
import { tutoriel } from './tutoriel-base.mjs';

const t = await tutoriel({ out: process.argv[2], app: process.argv[3] || 'http://127.0.0.1:8096/', base: 'Centriva-tutoriel-etiquettes' });
const { p, APP, VOIX, wait, L, say, hold, at, moveTo, click, type, card, login, finish } = t;
const reload = async (fn) => { await Promise.all([p.waitForNavigation(), fn()]); await wait(300); };

await login('claire.secretaire@demo.fr');
await card('Imprimer les étiquettes', 'Des étiquettes d\'étagère avec code-barres, pour ranger et scanner la réserve<br><span style="font-size:17px;opacity:.75">Centriva — édité par NLapps</span>', VOIX ? Math.max(4200, VOIX.intro * 1000 + 1600) : 6000, 51);
await p.goto(APP + 'product&id=1'); await wait(300);

await say(1, 'À quoi servent les étiquettes', 'Une étiquette d\'étagère indique le nom de l\'article, son fournisseur, sa référence, son emplacement et un code-barres que l\'on peut scanner.');
await moveTo(L('h1'));
await say(2, 'Indiquer l\'emplacement', 'Sur la fiche de l\'article, la carte « Dans votre centre » permet d\'indiquer où il est rangé : il sera imprimé sur l\'étiquette.', { hl: '.stock-place' });
await L('.stock-place details').evaluate((d) => { d.open = true; });
await type('.stock-place input[name=location]', 'Réserve 1 · étagère B2');
await at(0.85); await click('.stock-place button[type=submit]', { nav: true, after: 300 });
const btn = 'a[href*="r=labels"]';
await say(3, 'Imprimer l\'étiquette', 'Cliquez ensuite sur « Imprimer l\'étiquette » : la page d\'impression s\'ouvre avec l\'aperçu.', { hl: btn });
await at(0.75); await hold();
await p.goto(APP + 'labels&ids=1'); await wait(300);
await say(4, 'Choisir le format', 'Choisissez le format de vos étiquettes : planche A4 de 24, 14 ou 8 étiquettes, ou rouleau pour imprimante d\'étiquettes.', { hl: 'select[name=format]' });
const fmt = (await L('select[name=format]').inputValue()) === 'a4-14' ? 'a4-24' : 'a4-14';
await at(0.6); await reload(() => L('select[name=format]').selectOption(fmt));
await say(5, 'Nombre d\'exemplaires', 'Indiquez le nombre d\'étiquettes à imprimer pour cet article, par exemple deux, une pour l\'étagère et une pour le bac.', { hl: 'input[name=copies]' });
await L('input[name=copies]').fill('2'); await reload(() => L('input[name=copies]').press('Enter'));
await say(6, 'Réutiliser une planche entamée', 'Une planche a déjà servi ? « Commencer à l\'étiquette n° » indique la première étiquette libre : rien n\'est gaspillé.', { hl: 'input[data-skip]' });
await L('input[data-skip]').fill('3'); await reload(() => L('input[data-skip]').press('Enter'));
await say(7, 'L\'aperçu', 'L\'aperçu montre exactement ce qui sera imprimé, avec l\'emplacement et la couleur de la catégorie sur le côté.');
await moveTo(L('.label, .sheet, .page').first());
await say(8, 'Réglages de l\'imprimante', 'Cliquez sur « Imprimer ». Dans la fenêtre d\'impression, choisissez l\'échelle 100 % et aucune marge, pour que les étiquettes tombent juste.', { hl: 'button:has-text("Imprimer")' });
await hold();
await p.goto(APP + 'stock'); await wait(300);
await say(9, 'Toute la réserve d\'un coup', 'Pour étiqueter toute la réserve, ouvrez « Inventaire » puis « Étiquettes » : une étiquette pour chaque article suivi dans votre centre.', { hl: 'a[href*="labels"]' });
await at(0.8); await hold();
await p.goto(APP + 'labels&stock=1'); await wait(300);
await say(10, 'Scanner les étiquettes', 'Ces codes-barres se scannent ensuite dans Centriva, avec l\'appareil photo ou une douchette : recherche, inventaire tablette et réception.');
await p.mouse.wheel(0, 400); await wait(800);

await finish('À vous de jouer !', 'Fiche de l\'article → emplacement → « Imprimer l\'étiquette » → format → imprimer à 100 %<br>Toute la réserve : Inventaire → « Étiquettes »');
