// NTAG213/215/216 en « NFC simple » (dépannage quand les NTAG 424 DNA manquent) : URL fixe /q/<carte>, comme
// le QR code, sans signature. Commandes NFC-A brutes relayées par le téléphone (NfcA.transceive).
// Protection en écriture par mot de passe (PWD_AUTH, 32 bits) diversifié par puce, avec AUTHLIM : après
// 7 essais faux, la puce refuse définitivement toute écriture. La lecture reste libre.
// Référence : NXP NTAG213/215/216 (NTAG21x) datasheet, rév. 3.2.
import * as n from "./ntag424.ts";

export type Modele = { nom: string; pages: number; config: number; cc: number };

/** Taille mémoire (octet 6 de GET_VERSION) → modèle, nombre de pages et page CFG0. */
const MODELES: Record<number, Modele> = {
  0x0f: { nom: "NTAG213", pages: 45, config: 0x29, cc: 0x12 },
  0x11: { nom: "NTAG215", pages: 135, config: 0x83, cc: 0x3e },
  0x13: { nom: "NTAG216", pages: 231, config: 0xe3, cc: 0x6d },
};
const ACK = 0x0a;
const AUTHLIM = 7;

/** GET_VERSION : modèle NTAG21x, ou null si la puce n'en est pas une. */
export async function identifier(t: n.Transmit): Promise<Modele | null> {
  let v: Buffer;
  try {
    v = await t(Buffer.from([0x60]));
  } catch {
    return null;
  }
  if (v.length !== 8 || v[1] !== 0x04 || v[2] !== 0x04) return null;
  return MODELES[v[6]] ?? null;
}

async function lire(t: n.Transmit, page: number): Promise<Buffer> {
  const r = await t(Buffer.from([0x30, page]));
  if (r.length !== 16) throw new Error(`lecture de la page ${page} refusée par la puce`);
  return r;
}

async function ecrire(t: n.Transmit, page: number, quatre: Buffer) {
  const r = await t(Buffer.concat([Buffer.from([0xa2, page]), quatre]));
  if (r.length !== 1 || (r[0] & 0x0f) !== ACK) throw new Error(`écriture de la page ${page} refusée par la puce`);
}

/** Mot de passe (4 octets) et PACK (2 octets) dérivés de la clé de la puce. */
export const secrets = (cle: Buffer) => ({ pwd: cle.subarray(0, 4), pack: cle.subarray(4, 6) });

/** Message NDEF (TLV 03) contenant l'URL, terminé par FE et complété à un multiple de 4 octets. */
export function tlvUrl(url: string): Buffer {
  const fichier = n.fichierUrl(url).subarray(2); // sans NLEN : le Type 2 utilise un TLV
  const corps = Buffer.concat([Buffer.from([0x03, fichier.length]), fichier, Buffer.from([0xfe])]);
  return Buffer.concat([corps, Buffer.alloc((4 - (corps.length % 4)) % 4)]);
}

/** Lit l'URL comme un téléphone (TLV NDEF à partir de la page 4). */
export async function lireUrlType2(t: n.Transmit): Promise<string> {
  let octets = Buffer.alloc(0);
  for (let p = 4; p < 4 + 64 && octets.length < 260; p += 4) {
    octets = Buffer.concat([octets, await lire(t, p)]);
    const i = octets.indexOf(0x03);
    if (i >= 0 && octets.length >= i + 2 + octets[i + 1]) {
      const message = octets.subarray(i + 2, i + 2 + octets[i + 1]);
      const nlen = Buffer.from([0, message.length]);
      return n.lireUri(Buffer.concat([nlen, message]));
    }
  }
  throw new Error("la carte ne contient pas d'URL");
}

/**
 * Programme une NTAG213/215/216 avec une URL fixe et la protège en écriture.
 * Sûr à relancer : une carte déjà protégée par la clé Tapikard est simplement réécrite.
 */
export async function programmerNtag21x(
  t: n.Transmit, url: string, uid: Buffer, cle: Buffer, log: (m: string) => void = () => {},
): Promise<{ modele: string }> {
  const m = await identifier(t);
  if (!m) throw new Error("puce NFC-A non reconnue (ni NTAG213, ni NTAG215, ni NTAG216)");
  const p0 = await lire(t, 0);
  const uidLu = Buffer.concat([p0.subarray(0, 3), p0.subarray(4, 8)]);
  if (!uidLu.equals(uid)) throw new Error(`UID incohérent (${uid.toString("hex")} / ${uidLu.toString("hex")})`);
  const tlv = tlvUrl(url);
  if (tlv.length > m.cc * 8) throw new Error("URL trop longue pour cette puce");

  // 1. Déjà protégée ? Avec la clé Tapikard, on se reconnecte ; sinon, carte d'un tiers.
  const { pwd, pack } = secrets(cle);
  const cfg = await lire(t, m.config); // CFG0 (MIRROR, RFUI, MIRROR_PAGE, AUTH0), CFG1 (ACCESS, RFUI ×3), PWD et PACK lus à 0
  const auth0 = cfg[3];
  if (auth0 <= 0x04) {
    const r = await t(Buffer.concat([Buffer.from([0x1b]), pwd])).catch(() => Buffer.alloc(0));
    if (r.length !== 2 || !r.equals(pack)) throw new Error("carte protégée par un autre mot de passe (ni vierge, ni Tapikard)");
    log("carte Tapikard déjà protégée : reprogrammation");
  }

  // 2. Capability Container (page 3, programmable une seule fois) : carte formatée NDEF si ce n'est pas déjà fait.
  const cc = p0.subarray(12, 16);
  if (cc[0] !== 0xe1) {
    if (cc.some((b) => b !== 0)) throw new Error("Capability Container inattendu : carte non formatée NDEF");
    await ecrire(t, 3, Buffer.from([0xe1, 0x10, m.cc, 0x00]));
  }

  // 3. URL.
  for (let i = 0; i < tlv.length; i += 4) await ecrire(t, 4 + i / 4, tlv.subarray(i, i + 4));
  log("URL écrite");

  // 4. Protection : mot de passe et PACK, AUTHLIM (CFG1), puis AUTH0 = page 4 (CFG0) en dernier.
  if (auth0 > 0x04) {
    await ecrire(t, m.config + 2, pwd);
    await ecrire(t, m.config + 3, Buffer.concat([pack, Buffer.alloc(2)]));
    // ACCESS : PROT = 0 (seule l'écriture est protégée), compteur NFC inchangé, AUTHLIM = 7.
    await ecrire(t, m.config + 1, Buffer.from([(cfg[4] & 0x18) | AUTHLIM, cfg[5], cfg[6], cfg[7]]));
    await ecrire(t, m.config, Buffer.from([cfg[0], cfg[1], cfg[2], 0x04]));
    log("écriture protégée par mot de passe");
  }

  // 5. Contrôle, comme un téléphone.
  const lue = await lireUrlType2(t);
  if (lue !== url) throw new Error(`URL relue inattendue : ${lue}`);
  log("URL relue comme un téléphone");
  return { modele: m.nom };
}
