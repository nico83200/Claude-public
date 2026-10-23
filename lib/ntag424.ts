// Protocole NTAG 424 DNA côté serveur (encodage depuis le téléphone : le téléphone relaie les APDU,
// le serveur fait la cryptographie). Portage de tools/encodeur/ntag424.py, vérifié contre NXP AN12196.
import { createCipheriv, createDecipheriv, randomBytes } from "node:crypto";
import { aesCmac, diversifyKey } from "./sun.ts";

export type Transmit = (apdu: Buffer) => Promise<Buffer>;

export const NDEF_AID = Buffer.from("D2760000850101", "hex");
export const NDEF_FILE_ID = 0xe104;
export const NDEF_FILE_NO = 0x02;
export const FACTORY_KEY: Buffer = Buffer.alloc(16);

const STATUTS: Record<number, string> = {
  0x91ae: "erreur d'authentification (mauvaise clé)", 0x911e: "erreur d'intégrité", 0x917e: "erreur de longueur",
  0x919d: "permission refusée", 0x919e: "paramètre invalide", 0x9140: "clé inexistante", 0x911c: "commande non autorisée",
  0x6700: "longueur incorrecte", 0x6982: "accès refusé", 0x6a82: "fichier introuvable", 0x6a86: "paramètres incorrects",
};

export class CardError extends Error {
  sw: number;
  constructor(sw: number, quoi: string) {
    super(`${quoi} : SW ${sw.toString(16).toUpperCase().padStart(4, "0")} — ${STATUTS[sw] ?? "erreur"}`);
    this.sw = sw;
  }
}
export class IntegrityError extends Error {}

// --- Primitives ------------------------------------------------------------------------------

function aes(mode: "ecb" | "cbc", sens: "enc" | "dec", key: Buffer, iv: Buffer | null, data: Buffer): Buffer {
  const c = sens === "enc" ? createCipheriv(`aes-128-${mode}`, key, iv) : createDecipheriv(`aes-128-${mode}`, key, iv);
  c.setAutoPadding(false);
  return Buffer.concat([c.update(data), c.final()]);
}
export const ecbEnc = (k: Buffer, b: Buffer) => aes("ecb", "enc", k, null, b);
export const cbcEnc = (k: Buffer, iv: Buffer, b: Buffer) => aes("cbc", "enc", k, iv, b);
export const cbcDec = (k: Buffer, iv: Buffer, b: Buffer) => aes("cbc", "dec", k, iv, b);
export const macTronque = (full: Buffer) => Buffer.from(full.filter((_, i) => i % 2 === 1));
export const rotl = (b: Buffer) => Buffer.concat([b.subarray(1), b.subarray(0, 1)]);

export function pad(data: Buffer): Buffer {
  const out = Buffer.concat([data, Buffer.from([0x80])]);
  return Buffer.concat([out, Buffer.alloc((16 - (out.length % 16)) % 16)]);
}
export function unpad(data: Buffer): Buffer {
  let i = data.length - 1;
  while (i >= 0 && data[i] === 0) i--;
  if (i < 0 || data[i] !== 0x80) throw new IntegrityError("bourrage invalide");
  return data.subarray(0, i);
}

const CRC_TABLE = Array.from({ length: 256 }, (_, n) => {
  let c = n;
  for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
  return c >>> 0;
});
/** CRC32 de la nouvelle clé (JAMCRC : CRC32 sans inversion finale), petit-boutiste. */
export function crc32nk(data: Buffer): Buffer {
  let c = 0xffffffff;
  for (const b of data) c = CRC_TABLE[(c ^ b) & 0xff] ^ (c >>> 8);
  const out = Buffer.alloc(4);
  out.writeUInt32LE(c >>> 0);
  return out;
}

export function native(ins: number, data: Buffer = Buffer.alloc(0) as Buffer): Buffer {
  return Buffer.concat([Buffer.from([0x90, ins, 0, 0]), data.length ? Buffer.concat([Buffer.from([data.length]), data]) : Buffer.alloc(0), Buffer.from([0])]);
}
export function split(resp: Buffer): [Buffer, number] {
  if (resp.length < 2) throw new CardError(0, "réponse vide");
  return [resp.subarray(0, -2), resp.readUInt16BE(resp.length - 2)];
}
export const isoSelectApp = () => Buffer.concat([Buffer.from("00A4040C07", "hex"), NDEF_AID, Buffer.from([0])]);
export const isoSelectFile = (id: number) => Buffer.from([0x00, 0xa4, 0x00, 0x0c, 0x02, id >> 8, id & 0xff, 0x00]);
export const isoUpdateBinary = (off: number, data: Buffer) => Buffer.concat([Buffer.from([0x00, 0xd6, off >> 8, off & 0xff, data.length]), data]);
export const isoReadBinary = (off: number, len: number) => Buffer.from([0x00, 0xb0, off >> 8, off & 0xff, len & 0xff]);

// --- Session EV2 --------------------------------------------------------------------------------

export function vecteursSession(rndA: Buffer, rndB: Buffer): [Buffer, Buffer] {
  const xor = Buffer.from(rndA.subarray(2, 8).map((x, i) => x ^ rndB[i]));
  const queue = Buffer.concat([rndA.subarray(0, 2), xor, rndB.subarray(6, 16), rndA.subarray(8, 16)]);
  return [Buffer.concat([Buffer.from("A55A00010080", "hex"), queue]), Buffer.concat([Buffer.from("5AA500010080", "hex"), queue])];
}

export class Session {
  transmit: Transmit; ti: Buffer; kEnc: Buffer; kMac: Buffer; cmdCtr: number; keyNo: number;
  constructor(transmit: Transmit, ti: Buffer, kEnc: Buffer, kMac: Buffer, cmdCtr = 0, keyNo = 0) {
    this.transmit = transmit; this.ti = ti; this.kEnc = kEnc; this.kMac = kMac; this.cmdCtr = cmdCtr; this.keyNo = keyNo;
  }

  private ctr() { const b = Buffer.alloc(2); b.writeUInt16LE(this.cmdCtr); return b; }
  private iv(label: string) { return ecbEnc(this.kEnc, Buffer.concat([Buffer.from(label, "hex"), this.ti, this.ctr(), Buffer.alloc(8)])); }
  mac(prefixe: number, donnees: Buffer) { return macTronque(aesCmac(this.kMac, Buffer.concat([Buffer.from([prefixe]), this.ctr(), this.ti, donnees]))); }

  apdu(ins: number, entete: Buffer, donnees: Buffer, full: boolean): Buffer {
    const corps = full && donnees.length ? cbcEnc(this.kEnc, this.iv("A55A"), pad(donnees)) : donnees;
    return native(ins, Buffer.concat([entete, corps, this.mac(ins, Buffer.concat([entete, corps]))]));
  }

  async send(ins: number, entete: Buffer = Buffer.alloc(0), donnees: Buffer = Buffer.alloc(0), full = true, quoi = ""): Promise<Buffer> {
    const [rdata, sw] = split(await this.transmit(this.apdu(ins, entete, donnees, full)));
    if (sw !== 0x9100 && sw !== 0x9000) throw new CardError(sw, quoi || `commande ${ins.toString(16)}`);
    this.cmdCtr++;
    if (!rdata.length) return rdata;
    const corps = rdata.subarray(0, -8);
    if (!this.mac(0x00, corps).equals(rdata.subarray(-8))) throw new IntegrityError(`MAC de réponse invalide (${quoi})`);
    return full && corps.length ? unpad(cbcDec(this.kEnc, this.iv("5AA5"), corps)) : corps;
  }
}

export async function authentifier(transmit: Transmit, keyNo: number, key: Buffer, rndA: Buffer = randomBytes(16)): Promise<Session> {
  let data: Buffer, sw: number;
  [data, sw] = split(await transmit(native(0x71, Buffer.from([keyNo, 0x00]))));
  if (sw !== 0x91af || data.length !== 16) throw new CardError(sw, `authentification clé ${keyNo}`);
  const rndB = cbcDec(key, Buffer.alloc(16), data);
  [data, sw] = split(await transmit(native(0xaf, cbcEnc(key, Buffer.alloc(16), Buffer.concat([rndA, rotl(rndB)])))));
  if (sw !== 0x9100 || data.length !== 32) throw new CardError(sw, `authentification clé ${keyNo}`);
  const clair = cbcDec(key, Buffer.alloc(16), data);
  if (!clair.subarray(4, 20).equals(rotl(rndA))) throw new IntegrityError("la carte n'a pas prouvé qu'elle connaît la clé");
  const [sv1, sv2] = vecteursSession(rndA, rndB);
  return new Session(transmit, clair.subarray(0, 4), aesCmac(key, sv1), aesCmac(key, sv2), 0, keyNo);
}

export async function getCardUid(s: Session): Promise<Buffer> {
  return (await s.send(0x51, undefined, undefined, true, "GetCardUID")).subarray(0, 7);
}

export async function changeKey(s: Session, keyNo: number, nouvelle: Buffer, version: number, ancienne?: Buffer) {
  let donnees: Buffer;
  if (keyNo === s.keyNo) donnees = Buffer.concat([nouvelle, Buffer.from([version])]);
  else {
    if (!ancienne) throw new Error("ancienne clé requise");
    donnees = Buffer.concat([Buffer.from(nouvelle.map((x, i) => x ^ ancienne[i])), Buffer.from([version]), crc32nk(nouvelle)]);
  }
  await s.send(0xc4, Buffer.from([keyNo]), donnees, true, `ChangeKey ${keyNo}`);
}

export type SdmConfig = { picc: number; macInput: number; mac: number; metaKey?: number; fileKey?: number; ctrRetKey?: number };

export function encoderSdm(c: SdmConfig): Buffer {
  const o3 = (v: number) => Buffer.from([v & 0xff, (v >> 8) & 0xff, (v >> 16) & 0xff]);
  return Buffer.concat([
    Buffer.from([0x40, 0x00, 0xe0, 0xc1, 0xf0 | (c.ctrRetKey ?? 0xf), ((c.metaKey ?? 2) << 4) | (c.fileKey ?? 1)]),
    o3(c.picc), o3(c.macInput), o3(c.mac),
  ]);
}

export async function changeFileSettings(s: Session, c: SdmConfig) {
  await s.send(0x5f, Buffer.from([NDEF_FILE_NO]), encoderSdm(c), true, "ChangeFileSettings");
}

// --- Identification de la puce (GetVersion) ---------------------------------------------------------

/** GetVersion (commande native 60) : 7 octets « matériel » (fabricant, type, sous-type, version, mémoire, protocole),
 *  ou null si la carte ne la comprend pas (puce non NXP, carte bancaire, Java Card…). */
export async function lireVersion(t: Transmit): Promise<Buffer | null> {
  let [data, sw] = split(await t(native(0x60)));
  if (sw !== 0x91af || data.length < 7) return null;
  const materiel = Buffer.from(data.subarray(0, 7));
  // Lire les deux trames suivantes (logiciel, numéro de série) pour laisser la carte dans un état propre.
  for (let i = 0; i < 2 && sw === 0x91af; i++) [data, sw] = split(await t(native(0xaf)));
  return materiel;
}

function memoire(code: number): string {
  const octets = 2 ** (code >> 1);
  const txt = octets >= 1024 ? `${octets / 1024} Ko` : `${octets} octets`;
  return code & 1 ? `plus de ${txt}` : txt;
}

/** Nom lisible de la puce d'après les octets « matériel » de GetVersion. */
export function decrireVersion(v: Buffer): { nom: string; ntag424: boolean } {
  const [fabricant, type, sousType, majeure, , stockage] = v;
  if (fabricant !== 0x04) return { nom: `puce d'un autre fabricant que NXP (code ${fabricant.toString(16).padStart(2, "0").toUpperCase()})`, ntag424: false };
  const taille = memoire(stockage);
  if (type === 0x04) {
    if (majeure === 0x30) return { nom: sousType === 0x08 ? "NTAG 424 DNA TagTamper" : "NTAG 424 DNA", ntag424: true };
    if (majeure === 0x10) return { nom: "NTAG 413 DNA", ntag424: false };
    return { nom: "puce NXP NTAG (autre que NTAG 424 DNA)", ntag424: false };
  }
  if (type === 0x01) {
    const ev = ({ 0x00: "", 0x01: " EV1", 0x12: " EV2", 0x22: " EV2 XL", 0x33: " EV3" } as Record<number, string>)[majeure] ?? "";
    return { nom: `MIFARE DESFire${ev} (${taille})`, ntag424: false };
  }
  if (type === 0x08) return { nom: `MIFARE DESFire Light (${taille})`, ntag424: false };
  if (type === 0x02) return { nom: `MIFARE Plus (${taille})`, ntag424: false };
  if (type === 0x81 || type === 0x91) return { nom: "carte à microprocesseur NXP (Java Card / JCOP)", ntag424: false };
  return { nom: `puce NXP de type inconnu (${taille})`, ntag424: false };
}

/** Explication quand l'application NDEF est introuvable : la puce n'est presque toujours pas une NTAG 424 DNA. */
export function diagnosticPuce(sw: number, version: Buffer | null): string {
  const code = `SW ${sw.toString(16).toUpperCase().padStart(4, "0")}`;
  if (!version) {
    return `Cette carte n’est pas une NTAG 424 DNA : elle n’a pas d’application NDEF (${code}) et ne répond pas comme une puce NXP (carte bancaire, de transport, d’accès…). Utilisez des cartes NTAG 424 DNA.`;
  }
  const { nom, ntag424 } = decrireVersion(version);
  const ref = `version de la puce : ${version.toString("hex").toUpperCase()}`;
  if (ntag424) return `Puce identifiée comme ${nom}, mais son application NDEF est introuvable (${code}, ${ref}). Mettez cette carte de côté.`;
  return `Cette carte est une ${nom}, pas une NTAG 424 DNA : elle n’a pas l’application NDEF nécessaire à la signature SUN et ne peut pas être encodée. Utilisez des cartes NTAG 424 DNA (${ref}).`;
}

// --- NDEF ----------------------------------------------------------------------------------------

export function gabaritSun(baseUrl: string): { fichier: Buffer; url: string; sdm: SdmConfig } {
  const url = `${baseUrl.replace(/\/$/, "")}/c?e=${"0".repeat(32)}&m=${"0".repeat(16)}`;
  const prefixe = url.startsWith("https://") ? "https://" : url.startsWith("http://") ? "http://" : null;
  if (!prefixe) throw new Error("URL invalide");
  const reste = Buffer.from(url.slice(prefixe.length));
  const payload = Buffer.concat([Buffer.from([prefixe === "https://" ? 0x04 : 0x03]), reste]);
  const record = Buffer.concat([Buffer.from([0xd1, 0x01, payload.length, 0x55]), payload]);
  const nlen = Buffer.alloc(2);
  nlen.writeUInt16BE(record.length);
  const tete = 7;
  const e = tete + reste.indexOf("e=") + 2;
  const m = tete + reste.indexOf("&m=") + 3;
  return { fichier: Buffer.concat([nlen, record]), url, sdm: { picc: e, macInput: m, mac: m } };
}

export function lireUri(fichier: Buffer): string {
  const nlen = fichier.readUInt16BE(0);
  const rec = fichier.subarray(2, 2 + nlen);
  if (rec.length < 5 || rec[3] !== 0x55) throw new Error("le fichier NDEF ne contient pas d'URL");
  const payload = rec.subarray(4, 4 + rec[2]);
  const prefixe = ({ 4: "https://", 3: "http://" } as Record<number, string>)[payload[0]] ?? "";
  return prefixe + payload.subarray(1).toString();
}

export { diversifyKey };
