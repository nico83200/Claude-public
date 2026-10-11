// Lecture des fiches contact (vCard) : node tests/js-vcard.test.mjs
import { parseVCard, guessKind } from '../resources/js/contacts.js';
import assert from 'node:assert/strict';
const iphone = `BEGIN:VCARD\r\nVERSION:3.0\r\nPRODID:-//Apple Inc.//iPhone OS 17.0//EN\r\nN:Bernard;Julie;;;\r\nFN:Julie Bernard\r\nORG:Clinique équine du Var;\r\nTITLE:Vétérinaire\r\nitem1.EMAIL;type=INTERNET;type=pref:julie@clinique-equine.fr\r\nTEL;type=CELL;type=VOICE;type=pref:+33 6 12 34 56 78\r\nTEL;type=WORK;type=VOICE:04 94 00 00 00\r\nitem2.ADR;type=WORK;type=pref:;;12 chemin des Écuries;Hyères;;83400;France\r\nNOTE:Urgences 24h/24\\, appeler le portable\r\nEND:VCARD\r\n`;
const [a] = parseVCard(iphone);
assert.deepEqual([a.first_name, a.last_name, a.company, a.phone, a.email, a.address, a.kind], ['Julie', 'Bernard', 'Clinique équine du Var', '+33 6 12 34 56 78', 'julie@clinique-equine.fr', '12 chemin des Écuries, 83400 Hyères', 'veterinarian']);
assert.equal(a.notes, 'Urgences 24h/24, appeler le portable');
// Android, vCard 2.1, quoted-printable et ligne repliée
const android = 'BEGIN:VCARD\nVERSION:2.1\nN;CHARSET=UTF-8;ENCODING=QUOTED-PRINTABLE:Lef=C3=A8vre;J=C3=A9r=C3=B4me;;;\nFN;CHARSET=UTF-8;ENCODING=QUOTED-PRINTABLE:J=C3=A9r=C3=B4me Lef=C3=A8vre\nTEL;CELL:0611223344\nEMAIL;HOME:jerome@exemple.fr\nNOTE;CHARSET=UTF-8;ENCODING=QUOTED-PRINTABLE:Mar=C3=A9chal-ferrant, parage na=\nturel\nEND:VCARD\nBEGIN:VCARD\nVERSION:4.0\nFN:Sellerie du Sud\nORG:Sellerie du Sud\nTEL;VALUE=uri:tel:+33-4-94-11-22-33\nEND:VCARD\n';
const [b, c] = parseVCard(android);
assert.deepEqual([b.first_name, b.last_name, b.phone, b.email, b.kind, b.notes], ['Jérôme', 'Lefèvre', '0611223344', 'jerome@exemple.fr', 'farrier', 'Maréchal-ferrant, parage naturel']);
assert.deepEqual([c.last_name, c.company, c.phone, c.kind], ['Sellerie du Sud', '', '+33-4-94-11-22-33', 'saddler']);
assert.equal(guessKind('Monitrice BPJEPS'), 'instructor');
assert.equal(guessKind('Ostéopathe animalier'), 'osteopath');
assert.equal(parseVCard('pas une fiche').length, 0);
console.log('vCard : OK (iPhone 3.0, Android 2.1 QP, 4.0, déductions de fonction)');
