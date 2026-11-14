// Retour au même endroit après une saisie : node tests/js-return-position.test.mjs
import { internal, nextRecord, returnTarget, shouldRestore, MAX_AGE } from '../resources/js/return-position.js';
import assert from 'node:assert/strict';

const base = 'https://app.jackcie.fr/budget?horse=3';
assert.equal(internal('/budget/12', base), '/budget/12');
assert.equal(internal('https://app.jackcie.fr/budget/12/modifier?x=1', base), '/budget/12/modifier?x=1');
assert.equal(internal('https://ailleurs.fr/budget', base), null, 'autre site');
assert.equal(internal('#haut', base), null, 'simple ancre');

// Liste des dépenses → fiche → modification → enregistrement : retour à la liste, ligne de la dépense.
let rec = nextRecord(null, '/budget?horse=3', '/budget/12', 0);
assert.deepEqual([rec.origin, rec.originPath, rec.row, rec.submitted], ['/budget?horse=3', '/budget', '/budget/12', false]);
rec = nextRecord(rec, '/budget/12', '/budget/12/modifier', 1000);
assert.deepEqual([rec.origin, rec.row, rec.chain], ['/budget?horse=3', '/budget/12', ['/budget/12', '/budget/12/modifier']], 'le parcours continue');
assert.equal(returnTarget(rec, '/budget/12/modifier', 2000), '/budget?horse=3');
rec = nextRecord(rec, '/budget/12/modifier', '/budget/12', 2000, true);
assert.equal(rec.submitted, true);
assert.equal(shouldRestore(rec, '/budget?horse=3', 3000, 'navigate'), true);
assert.equal(shouldRestore(rec, '/budget', 3000, 'back_forward'), false, 'retour arrière : le navigateur s\'en charge');
assert.equal(shouldRestore(rec, '/sante', 3000, 'navigate'), false, 'autre page');
assert.equal(shouldRestore(rec, '/budget', MAX_AGE + 5000, 'navigate'), false, 'trop ancien');

// Simple consultation (aucun enregistrement) : pas de défilement imposé.
const look = nextRecord(null, '/budget', '/budget/12', 0);
assert.equal(shouldRestore(look, '/budget', 1000, 'navigate'), false);

// Action dans la liste même (« Payée ») : nouveau parcours depuis la liste, retour sur la ligne.
const inList = nextRecord(look, '/budget', '/budget/12/paiement', 5000, true);
assert.deepEqual([inList.originPath, inList.row, inList.submitted], ['/budget', '/budget/12/paiement', true]);
assert.equal(returnTarget(inList, '/budget', 6000), null, 'déjà sur la page d\'origine');
// Une page sans rapport démarre un nouveau parcours.
assert.equal(nextRecord(rec, '/agenda', '/agenda/5', 4000).originPath, '/agenda');
console.log('Retour au même endroit : OK');
