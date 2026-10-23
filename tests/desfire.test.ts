// Programmation DESFire EV2 en NFC simple, contre la DESFire émulée de tools/encodeur (Python).
import { test } from "node:test";
import assert from "node:assert/strict";
import { spawn } from "node:child_process";
import { existsSync } from "node:fs";
import { createInterface } from "node:readline";
import { programmerDesfire, lireUrlType4 } from "../lib/desfire.ts";
import type { Transmit } from "../lib/ntag424.ts";

const OUTILS = new URL("../tools/encodeur/", import.meta.url).pathname;
const CLES = { picc: Buffer.alloc(16, 0x21), app: Buffer.alloc(16, 0x22) };
const URL_CARTE = "https://nfc.exemple.fr/q/Ab3dE5gH7j";

/** Lance une DESFire émulée ; chaque ligne envoyée est un APDU, chaque ligne reçue la réponse. */
function carte(options: string) {
  const py = spawn("python3", ["-c", `
import sys; sys.path.insert(0, ${JSON.stringify(OUTILS)})
from desfire_emulee import DesfireEmulee
c = DesfireEmulee(bytes.fromhex("04512A1B2C3D80"), ${options})
for l in sys.stdin:
    l = l.strip()
    if l == "ETAT": print(repr((c.picc_cle[0], c.picc_cle[1].hex(), [a["cle"].hex() for a in c.apps.values()])), flush=True)
    else: print(c.transmit(bytes.fromhex(l)).hex(), flush=True)
`]);
  const lignes = createInterface({ input: py.stdout })[Symbol.asyncIterator]();
  const ligne = async (l: string) => { py.stdin.write(l + "\n"); return String((await lignes.next()).value); };
  const t: Transmit = async (apdu) => Buffer.from(await ligne(apdu.toString("hex")), "hex");
  return { t, etat: () => ligne("ETAT"), fin: () => py.kill() };
}

const disponible = existsSync(OUTILS + "desfire_emulee.py");

test("DESFire EV2 neuve : programmée, verrouillée, relue comme un téléphone, puis reprogrammable", { skip: !disponible }, async () => {
  const c = carte(`"desfire_ev2"`);
  try {
    const journal: string[] = [];
    const r = await programmerDesfire(c.t, URL_CARTE, CLES, (m) => journal.push(m));
    assert.equal(r.verrouillee, true);
    assert.equal(await lireUrlType4(c.t), URL_CARTE);
    assert.equal(await c.etat(), `('aes', '${"21".repeat(16)}', ['${"22".repeat(16)}'])`);
    // Reprogrammation (autre URL) avec la clé Tapikard déjà en place.
    const r2 = await programmerDesfire(c.t, URL_CARTE + "2", CLES, (m) => journal.push(m));
    assert.equal(r2.verrouillee, true);
    assert.equal(await lireUrlType4(c.t), URL_CARTE + "2");
    assert.ok(journal.includes("carte Tapikard effacée (reprogrammation)"), journal.join(" / "));
  } finally {
    c.fin();
  }
});

test("DESFire EV2 : clé de session 2K3DES acceptée aussi", { skip: !disponible }, async () => {
  const c = carte(`"desfire_ev2", "2k3des"`);
  try {
    assert.equal((await programmerDesfire(c.t, URL_CARTE, CLES)).verrouillee, true);
  } finally {
    c.fin();
  }
});

test("DESFire configurée par un tiers : refusée sans rien modifier", { skip: !disponible }, async () => {
  const c = carte(`"desfire_ev2"); c.picc_cle = ("aes", bytes([0x99]) * 16`);
  try {
    await assert.rejects(programmerDesfire(c.t, URL_CARTE, CLES), /clé maître de la carte inconnue/);
  } finally {
    c.fin();
  }
});
