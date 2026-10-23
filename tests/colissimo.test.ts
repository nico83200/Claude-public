import { test } from "node:test";
import assert from "node:assert/strict";
import { lireReponse, requeteEtiquette } from "../lib/colissimo.ts";

test("Colissimo : réponse multipart (JSON + PDF)", () => {
  const b = "uuid:4b2c-limite";
  const corps = Buffer.concat([
    Buffer.from(`--${b}\r\nContent-Type: application/json;charset=UTF-8\r\nContent-Transfer-Encoding: binary\r\nContent-ID: <jsonInfos>\r\n\r\n`),
    Buffer.from(JSON.stringify({ messages: [{ id: "0", type: "INFOS", messageContent: "La requête a été traitée avec succès" }], labelV2Response: { parcelNumber: "6A12345678901" } })),
    Buffer.from(`\r\n--${b}\r\nContent-Type: application/octet-stream\r\nContent-Transfer-Encoding: binary\r\nContent-ID: <label>\r\n\r\n`),
    Buffer.from("%PDF-1.4 étiquette\r\n%%EOF"),
    Buffer.from(`\r\n--${b}--\r\n`),
  ]);
  const r = lireReponse(`multipart/mixed;boundary="${b}";type="application/json"`, corps);
  assert.equal(r.numero, "6A12345678901");
  assert.equal(r.pdf.toString().slice(0, 8), "%PDF-1.4");
  assert.ok(r.pdf.toString().endsWith("%%EOF"));
});

test("Colissimo : erreur renvoyée par La Poste", () => {
  const corps = Buffer.from(JSON.stringify({ messages: [{ id: "30109", type: "ERROR", messageContent: "Le code postal du destinataire est invalide" }] }));
  assert.throws(() => lireReponse("application/json", corps), /code postal du destinataire est invalide \(30109\)/);
});

test("Colissimo : requête d'étiquette", () => {
  Object.assign(process.env, { COLISSIMO_CONTRAT: "123456", COLISSIMO_MOT_DE_PASSE: "x", EXPEDITEUR_ADRESSE: "1 rue A", EXPEDITEUR_CP: "83000", EXPEDITEUR_VILLE: "Toulon" });
  const q = requeteEtiquette(
    { id: "cmd_1", quantity: 2 } as never,
    { name: "Camille Martin", address: "12 rue des Arts", address2: "Bât. B", zip: "69002", city: "Lyon", country: "FR", phone: "06 12 34 56 78" },
    { email: "c@ex.fr" }, new Date("2026-10-24T10:00:00Z"),
  );
  assert.equal(q.letter.service.productCode, "DOM");
  assert.equal(q.letter.service.depositDate, "2026-10-24");
  assert.deepEqual([q.letter.addressee.address.firstName, q.letter.addressee.address.lastName], ["Camille", "Martin"]);
  assert.equal(q.letter.addressee.address.line3, "Bât. B");
  assert.equal(q.letter.addressee.address.mobileNumber, "0612345678");
});
