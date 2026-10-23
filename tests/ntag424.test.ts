import { test } from "node:test";
import assert from "node:assert/strict";
import * as n from "../lib/ntag424.ts";

const H = (s: string) => Buffer.from(s, "hex");
const ZERO = Buffer.alloc(16);
const scripte = (paires: [string, string][]): n.Transmit => {
  const file = [...paires];
  return async (apdu) => {
    const [attendu, rep] = file.shift()!;
    assert.equal(apdu.toString("hex").toUpperCase(), attendu, "APDU inattendu");
    return H(rep);
  };
};
const RND_A = H("13C5DB8A5930439FC3DEF9A4C675360F");
const AUTH: [string, string][] = [
  ["9071000002000000", "A04C124213C186F22399D33AC2A3021591AF"],
  ["90AF00002035C3E05A752E0144BAC0DE51C1F22C56B34408A23D8AEA266CAB947EA8E0118D00", "3FA64DB5446D1F34CD6EA311167F5E4985B89690C04A05F17FA7AB2F081206639100"],
];

test("AN12196 table 14 — authentification et clés de session", async () => {
  const s = await n.authentifier(scripte(AUTH), 0, ZERO, RND_A);
  assert.equal(s.ti.toString("hex").toUpperCase(), "9D00C4DF");
  assert.equal(s.kEnc.toString("hex").toUpperCase(), "1309C877509E5A215007FF0ED19CA564");
  assert.equal(s.kMac.toString("hex").toUpperCase(), "4C6626F5E72EA694202139295C7A7FC7");
});

test("AN12196 table 18 — ChangeFileSettings (SUN)", async () => {
  assert.equal(n.encoderSdm({ picc: 0x20, macInput: 0x43, mac: 0x43, ctrRetKey: 1 }).toString("hex").toUpperCase(), "4000E0C1F121200000430000430000");
  const t = scripte([...AUTH, ["905F0000190261B6D97903566E84C3AE5274467E89EAD799B7C1A0EF7A0400", "57BFF87B1241E93D9100"]]);
  const s = await n.authentifier(t, 0, ZERO, RND_A);
  s.cmdCtr = 1;
  await n.changeFileSettings(s, { picc: 0x20, macInput: 0x43, mac: 0x43, ctrRetKey: 1 });
  assert.equal(s.cmdCtr, 2);
});

test("AN12196 tables 25 et 26 — ChangeKey", async () => {
  const s = new n.Session(scripte([["90C4000029022CF362B7BF4311FF3BE1DAA295E8C68DE09050560D19B9E16C2393AE9CD1FAC75D0CE20BCD1D06E600", "203BB55D1089D5879100"]]),
    H("7614281A"), H("4CF3CB41A22583A61E89B158D252FC53"), H("5529860B2FC5FB6154B7F28361D30BF9"), 2);
  await n.changeKey(s, 2, H("F3847D627727ED3BC9C4CC050489B966"), 1, ZERO);
  const s2 = new n.Session(scripte([["90C400002900C0EB4DEEFEDDF0B513A03A95A75491818580503190D4D05053FF75668A01D6FDA6610234BDED643200", "9100"]]),
    H("7614281A"), H("4CF3CB41A22583A61E89B158D252FC53"), H("5529860B2FC5FB6154B7F28361D30BF9"), 3);
  await n.changeKey(s2, 0, H("5004BF991F408672B1EF00F08F9E8647"), 1);
  assert.equal(n.crc32nk(H("F3847D627727ED3BC9C4CC050489B966")).toString("hex").toUpperCase(), "789DFADC");
});

test("AN12196 table 28 — GetCardUID", async () => {
  const s = new n.Session(scripte([["90510000088E2C155ADDA99BE300", "70756055688505B52A5E26E59E329CD6595F672298EA41B79100"]]),
    H("DF055522"), H("2B4D963C014DC36F24F69A50A394F875"), H("379D32130CE61705DD5FD8C36B95D764"));
  assert.equal((await n.getCardUid(s)).toString("hex").toUpperCase(), "04958CAA5C5E80");
});

test("Gabarit NDEF : positions SUN et relecture", () => {
  const g = n.gabaritSun("https://choose.url.com/ntag424");
  assert.deepEqual([g.sdm.picc, g.sdm.mac], [0x20 + 2, 0x43 + 2]);
  assert.equal(n.lireUri(g.fichier), g.url);
});

test("Identification de la puce (GetVersion)", async () => {
  assert.deepEqual(n.decrireVersion(H("04040230001105")), { nom: "NTAG 424 DNA", ntag424: true });
  assert.equal(n.decrireVersion(H("04010133001805")).nom, "MIFARE DESFire EV3 (4 Ko)");
  assert.equal(n.decrireVersion(H("04010101001805")).nom, "MIFARE DESFire EV1 (4 Ko)");
  assert.equal(n.decrireVersion(H("04080130001305")).nom, "MIFARE DESFire Light (plus de 512 octets)");
  // Carte DESFire vierge : pas d'application NDEF, la version explique pourquoi.
  const t = scripte([
    ["9060000000", "04010133001805" + "91AF"],
    ["90AF000000", "04010103001805" + "91AF"],
    ["90AF000000", "04A1B2C3D4E5F6BA34999F5003120091" + "9100"],
  ]);
  const v = await n.lireVersion(t);
  assert.match(n.diagnosticPuce(0x6a82, v), /MIFARE DESFire EV3 \(4 Ko\), pas une NTAG 424 DNA/);
  assert.match(n.diagnosticPuce(0x6a82, null), /ne répond pas comme une puce NXP/);
});
