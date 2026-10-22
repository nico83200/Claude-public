// Vérification des scans NTAG 424 DNA (SUN / Secure Dynamic Messaging).
// Portage TypeScript de tools/sun_verify.py — références NXP AN12196 et AN10922.
import { createCipheriv, createDecipheriv, timingSafeEqual } from "node:crypto";

function aesEcb(key: Buffer, block: Buffer): Buffer {
  const c = createCipheriv("aes-128-ecb", key, null);
  c.setAutoPadding(false);
  return Buffer.concat([c.update(block), c.final()]);
}

function shiftLeft(b: Buffer): Buffer {
  const out = Buffer.alloc(16);
  for (let i = 0; i < 16; i++) out[i] = ((b[i] << 1) | (i < 15 ? b[i + 1] >> 7 : 0)) & 0xff;
  if (b[0] & 0x80) out[15] ^= 0x87;
  return out;
}

/** AES-CMAC (RFC 4493). */
export function aesCmac(key: Buffer, data: Buffer): Buffer {
  const k1 = shiftLeft(aesEcb(key, Buffer.alloc(16)));
  const k2 = shiftLeft(k1);
  const n = Math.max(1, Math.ceil(data.length / 16));
  const complete = data.length > 0 && data.length % 16 === 0;
  const last = Buffer.alloc(16);
  data.copy(last, 0, (n - 1) * 16);
  if (!complete) last[data.length - (n - 1) * 16] = 0x80;
  const sub = complete ? k1 : k2;
  for (let i = 0; i < 16; i++) last[i] ^= sub[i];
  let x: Buffer = Buffer.alloc(16);
  for (let b = 0; b < n - 1; b++) {
    const blk = data.subarray(b * 16, b * 16 + 16);
    for (let i = 0; i < 16; i++) x[i] ^= blk[i];
    x = aesEcb(key, x);
  }
  for (let i = 0; i < 16; i++) x[i] ^= last[i];
  return aesEcb(key, x);
}

/** Déchiffre PICCData (AES-128-CBC, IV nul) → UID (7 octets) + compteur de lecture. */
export function decryptPiccData(kMeta: Buffer, eHex: string): { uid: Buffer; counter: number } {
  const d = createDecipheriv("aes-128-cbc", kMeta, Buffer.alloc(16));
  d.setAutoPadding(false);
  const data = Buffer.concat([d.update(Buffer.from(eHex, "hex")), d.final()]);
  const tag = data[0];
  if (!(tag & 0x80) || !(tag & 0x40) || (tag & 0x0f) !== 7) throw new Error("PICCData invalide");
  return { uid: data.subarray(1, 8), counter: data.readUIntLE(8, 3) };
}

/** Clé propre à chaque carte : AN10922, CMAC(master, 0x01 || UID || n° de clé || constante). */
export function diversifyKey(master: Buffer, uid: Buffer, keyNo: number): Buffer {
  return aesCmac(master, Buffer.concat([Buffer.from([0x01]), uid, Buffer.from([keyNo]), Buffer.from("Tapikard")]));
}

/** CMAC SDM tronqué (8 octets, octets d'index impair) pour un UID et un compteur. */
export function expectedCmac(kFile: Buffer, uid: Buffer, counter: number): Buffer {
  const ctr = Buffer.alloc(3);
  ctr.writeUIntLE(counter, 0, 3);
  const sv2 = Buffer.concat([Buffer.from("3CC300010080", "hex"), uid, ctr]);
  const full = aesCmac(aesCmac(kFile, sv2), Buffer.alloc(0));
  return Buffer.from(full.filter((_, i) => i % 2 === 1));
}

export type SunResult = { uid: string; counter: number };

/** Vérifie e/m. Lève une erreur si l'URL est mal formée ou la signature invalide.
 *  Le contrôle anti-rejeu (compteur > dernier compteur connu) est fait par l'appelant. */
export function verifySun(kMeta: Buffer, kFileForUid: (uid: Buffer) => Buffer, eHex: string, mHex: string): SunResult {
  if (!/^[0-9a-fA-F]{32}$/.test(eHex) || !/^[0-9a-fA-F]{16}$/.test(mHex)) throw new Error("Paramètres SUN mal formés");
  const { uid, counter } = decryptPiccData(kMeta, eHex);
  const expected = expectedCmac(kFileForUid(uid), uid, counter);
  if (!timingSafeEqual(expected, Buffer.from(mHex, "hex"))) throw new Error("CMAC invalide");
  return { uid: uid.toString("hex").toUpperCase(), counter };
}

/** Clés SUN depuis l'environnement. SUN_FACTORY_KEYS=1 (ou dev sans clés) : clés d'usine non diversifiées. */
export function sunKeys(): { kMeta: Buffer; kFileForUid: (uid: Buffer) => Buffer } {
  const meta = process.env.SUN_K_META;
  const master = process.env.SUN_MASTER_K1;
  const factory = process.env.SUN_FACTORY_KEYS === "1" || (!meta && !master && process.env.NODE_ENV !== "production");
  if (factory) return { kMeta: Buffer.alloc(16), kFileForUid: () => Buffer.alloc(16) };
  if (!meta || !master || !/^[0-9a-fA-F]{32}$/.test(meta) || !/^[0-9a-fA-F]{32}$/.test(master)) {
    throw new Error("SUN_K_META et SUN_MASTER_K1 (32 caractères hex) sont obligatoires");
  }
  const kMeta = Buffer.from(meta, "hex");
  const kMaster = Buffer.from(master, "hex");
  return { kMeta, kFileForUid: (uid) => diversifyKey(kMaster, uid, 1) };
}
