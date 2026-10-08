/*
 * Vidéo tutoriel « Commander un article hors catalogue » (données de démonstration).
 *   [VOIX=durees.json] PWPATH=… node tools/tutoriel-hors-catalogue.mjs <dossier-sortie> <photo.jpg> [url]
 * Salariée claire.secretaire@demo.fr (panier vide) puis administrateur achats@groupe-sante.fr (sans double authentification).
 */
import { tutoriel } from './tutoriel-base.mjs';

const PHOTO = process.argv[3];
const t = await tutoriel({ out: process.argv[2], app: process.argv[4] || 'http://127.0.0.1:8096/', base: 'Approvia-tutoriel-hors-catalogue' });
const { p, APP, VOIX, wait, L, say, hold, at, moveTo, click, type, setQty, card, part, login, finish } = t;

await login('claire.secretaire@demo.fr');
await card('Commander un article hors catalogue', 'Proposer un nouvel article · Le commander · Validation par le service achats<br><span style="font-size:17px;opacity:.75">Approvia — édité par NLapps</span>', VOIX ? Math.max(4200, VOIX.intro * 1000 + 1600) : 6000, 51);

// ================================================================ Partie 1 : le salarié propose l'article
await part(1, 'Proposer l\'article', 'Recherche · Description · Photo · Quantité');
await p.goto(APP + 'dashboard'); await wait(300);
await say(1, 'Un article introuvable', 'Vous cherchez un article qui n\'existe pas dans le catalogue ? Tapez quand même votre recherche, ici « spéculum ».');
await at(0.4); await type('.topbar input[type=search], header input[name=q]', 'spéculum');
await at(0.9); await p.keyboard.press('Enter'); await p.waitForLoadState();
const prop = 'a:has-text("Proposez un article hors catalogue"), a.btn:has-text("Proposer")';
await say(2, 'Aucun ne convient ?', 'Approvia montre les articles les plus proches. Aucun ne convient ? En bas de la liste, cliquez sur « Proposez un article hors catalogue ».', { hl: prop });
await at(0.85); await click(prop, { nav: true, after: 300 });
await say(3, 'Décrire l\'article', 'Décrivez l\'article le plus précisément possible : nom, marque, référence et conditionnement.', { hl: '.card:has(input[name=name])' });
await L('input[name=name]').fill(''); await type('input[name=name]', 'Spéculums vaginaux jetables taille M');
await type('input[name=brand]', 'SpecuMed');
await type('input[name=reference]', 'SPM-25M');
await type('input[name=unit]', 'Boîte de 25');
await say(4, 'Préciser l\'usage', 'Précisez l\'usage : la taille, pour quel soin, ou pourquoi l\'ancien modèle ne convient plus.');
await type('textarea[name=description]', 'Pour les frottis en salle 2, l\'ancien modèle n\'est plus fabriqué.');
await say(5, 'Ajouter une photo', 'Ajoutez une photo de l\'article ou de son étiquette : sur tablette ou téléphone, l\'appareil photo s\'ouvre directement.', { hl: '.card:has(input[name=photo])' });
await moveTo(L('input[name=photo]')); await L('input[name=photo]').setInputFiles(PHOTO); await wait(600);
await say(6, 'Où le trouver ?', 'Si vous le connaissez, indiquez où le trouver : fournisseur ou magasin, prix constaté et lien vers le site.', { hl: '.card:has(input[name=supplier_hint])' });
await type('input[name=supplier_hint]', 'MédiDistrib');
await L('input[name=estimated_price]').fill(''); await type('input[name=estimated_price]', '18,90');
await type('input[name=url]', 'https://www.medidistrib.example/speculum-m');
await say(7, 'Quantité et envoi', 'Laissez « J\'en ai besoin » coché, indiquez la quantité, puis envoyez la proposition : l\'article est ajouté à votre panier.', { hl: '.card:has(input[name=add_to_cart])' });
await setQty('input[name=qty]', 2);
await at(0.85); await click('button:has-text("Envoyer la proposition")', { nav: true, after: 300 });
if (!p.url().includes('r=cart')) await p.goto(APP + 'cart');
await say(8, 'Dans le panier', 'Dans le panier, l\'article apparaît dans « Articles hors catalogue », à valider par le service achats. Envoyez votre demande comme d\'habitude.', { hl: '.supplier-block:has-text("hors catalogue")' });
await at(0.85); await click('button[name=then][value=submit]', { nav: true, after: 300 });
await hold(); await p.goto(APP + 'requests&scope=suggestions'); await wait(300);
await say(9, 'Suivre la proposition', '« Suivi des demandes », onglet « Mes articles proposés » : vous voyez la réponse du service achats.', { hl: '.card:has(.list)' });

// ================================================================ Partie 2 : le service achats valide
await part(2, 'Côté service achats', 'Vérifier · Compléter · Ajouter au catalogue');
await login('achats@groupe-sante.fr');
await p.goto(APP + 'admin/suggestions'); await wait(300);
await say(10, 'Les articles proposés', 'Les propositions arrivent dans « Articles proposés » ; le menu indique le nombre en attente.', { hl: '.sidebar a[href*="admin/suggestions"], .sidebar a[href*="admin%2Fsuggestions"]' });
await at(0.75); await click('a[href*="admin/suggestion&id"], a[href*="admin%2Fsuggestion&id"]', { nav: true, after: 300 });
await say(11, 'Ce qu\'a indiqué le salarié', 'La proposition reprend tout ce qu\'a indiqué le salarié : description, photo, quantité demandée et où trouver l\'article.', { hl: '.card:has-text("Ce qu\'a indiqué le salarié")' });
await say(12, 'Existe-t-il déjà ?', 'L\'article existe déjà sous un autre nom ? « Existe-t-il déjà ? » permet de le rattacher au lieu d\'en créer un nouveau.', { hl: '.card:has-text("Existe-t-il déjà")' });
const f = 'form[action*="suggestion/add"]';
await say(13, 'Compléter la fiche', 'Sinon, complétez la fiche : fournisseur, catégorie et prix. Vous pouvez laisser un message au salarié.', { hl: f });
await moveTo(L(`${f} select[name=supplier_id]`)); await L(`${f} select[name=supplier_id]`).selectOption({ index: 1 }); await wait(500);
const cat = L(`${f} select[name=category_id]`); await moveTo(cat);
const catVal = await cat.evaluate((s) => [...s.options].find((o) => /médical/i.test(o.text))?.value || s.options[1]?.value);
await cat.selectOption(catVal); await wait(500);
await type(`${f} input[name=negotiated_price]`, '16,50');
await type(`${f} input[name=admin_note]`, 'Ajouté au catalogue MédiDistrib, commande avec la prochaine livraison.');
await say(14, 'Ajouter au catalogue', '« Ajouter au catalogue » : l\'article est créé pour tous les centres, et la demande du salarié passe dans « Demandes à traiter », comme les autres.', { hl: `${f} button[type=submit]` });
await at(0.8); await click(`${f} button[type=submit]`, { nav: true, after: 300 });
await say(15, 'Le salarié est prévenu', 'Le salarié est prévenu par une notification. Si la proposition est refusée, il voit le motif dans son suivi.', { hl: '.flash' });
await moveTo(L('h1'));

await finish('À vous de jouer !', 'Rechercher → « Proposer » → décrire, photo, quantité → envoyer la demande<br>Service achats : Articles proposés → compléter → « Ajouter au catalogue »');
