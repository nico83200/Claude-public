// Programmation NTAG213/215/216 en NFC simple, contre la puce émulée de tools/encodeur (Python).
import { test } from "node:test";
import assert from "node:assert/strict";
import { spawn } from "node:child_process";
import { existsSync } from "node:fs";
import { createInterface } from "node:readline";
import { lireUrlType2, programmerNtag21x, tlvUrl } from "../lib/ntag21x.ts";
import type { Transmit } from "../lib/ntag424.ts";

const OUTILS = new URL("../tools/encodeur/", import.meta.url).pathname;
const UID = Buffer.from("04A1B2C3D4E5F6", "hex");
const CLE = Buffer.from("00112233445566778899AABBCCDDEEFF", "hex");
const URL_CARTE = "https://nfc.exemple.fr/q/Ab3dE5gH7j";

function carte(options: string, apres = "") {
  const py = spawn("python3", ["-c", `
import sys; sys.path.insert(0, ${JSON.stringify(OUTILS)})
from ntag21x_emulee import Ntag21xEmulee
c = Ntag21xEmulee(bytes.fromhex("04A1B2C3D4E5F6"), ${options})
${apres}
for l in sys.stdin:
    l = l.strip()
    if l == "ETAT": print(c.mem[4*c.config:4*c.config+16].hex(), flush=True)
    elif l == "RESET": c.authentifiee = False; print("ok", flush=True)
    else: print(c.transmit(bytes.fromhex(l)).hex(), flush=True)
`]);
  const lignes = createInterface({ input: py.stdout })[Symbol.asyncIterator]();
  const ligne = async (l: string) => { py.stdin.write(l + "\n"); return String((await lignes.next()).value); };
  const t: Transmit = async (c) => Buffer.from(await ligne(c.toString("hex")), "hex");
  return { t, etat: () => ligne("ETAT"), reset: () => ligne("RESET"), fin: () => py.kill() };
}
const disponible = existsSync(OUTILS + "ntag21x_emulee.py");

test("TLV NDEF Type 2", () => {
  const tlv = tlvUrl("https://a.fr/q/x");
  assert.equal(tlv[0], 0x03);
  assert.equal(tlv.length % 4, 0);
  assert.equal(tlv[2 + tlv[1]], 0xfe);
});

for (const modele of ["ntag213", "ntag215", "ntag216"]) {
  test(`${modele} : programmée, protégée en écriture, relue, puis reprogrammable avec la clé Tapikard`, { skip: !disponible }, async () => {
    const c = carte(`"${modele}"`);
    try {
      const r = await programmerNtag21x(c.t, URL_CARTE, UID, CLE);
      assert.equal(r.modele, modele.toUpperCase());
      assert.equal(await lireUrlType2(c.t), URL_CARTE);
      const cfg = await c.etat();
      assert.equal(cfg.slice(6, 8), "04", "AUTH0 = page 4");
      assert.equal(cfg.slice(16, 24), "00112233", "mot de passe dérivé");
      // Hors session, l'écriture est refusée : personne ne peut changer l'adresse.
      await c.reset();
      assert.equal((await c.t(Buffer.from("A2040000000000", "hex").subarray(0, 6))).toString("hex"), "00");
      await c.reset();
      await programmerNtag21x(c.t, URL_CARTE + "2", UID, CLE);
      assert.equal(await lireUrlType2(c.t), URL_CARTE + "2");
    } finally {
      c.fin();
    }
  });
}

test("NTAG215 vierge sans Capability Container : formatée NDEF", { skip: !disponible }, async () => {
  const c = carte(`"ntag215", cc_usine=False`);
  try {
    await programmerNtag21x(c.t, URL_CARTE, UID, CLE);
    assert.equal(await lireUrlType2(c.t), URL_CARTE);
  } finally {
    c.fin();
  }
});

test("NTAG215 protégée par un tiers : refusée", { skip: !disponible }, async () => {
  const c = carte(`"ntag215"`, `c.mem[4*c.config+3] = 4; c.mem[4*(c.config+2):4*(c.config+2)+4] = b"\\x99"*4`);
  try {
    await assert.rejects(programmerNtag21x(c.t, URL_CARTE, UID, CLE), /autre mot de passe/);
  } finally {
    c.fin();
  }
});
