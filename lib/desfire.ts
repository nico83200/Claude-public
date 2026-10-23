// MIFARE DESFire EV2/EV3 en « NFC simple » (dépannage quand les NTAG 424 DNA manquent) :
// la puce porte une URL fixe /q/<carte>, comme le QR code, sans signature SUN. Elle peut donc être copiée.
// La puce est formatée en tag NFC Forum Type 4 (application D2760000850101, fichiers E103 et E104), lisible par
// tous les téléphones, puis verrouillée par des clés Tapikard pour que personne ne puisse l'effacer ou la réécrire.
// Références : NXP MF3D(H)x2 (DESFire EV2), AN11004 (NDEF sur DESFire), AN12196 (messagerie sécurisée EV2).
import { createCipheriv, createDecipheriv, randomBytes } from "node:crypto";
import * as n from "./ntag424.ts";

export const AID = Buffer.from([0x54, 0x50, 0x4b]);  // « TPK », octet de poids faible en premier
const PICC = Buffer.alloc(3);
const ISO_DF = Buffer.from([0x10, 0xe1]);            // E110, poids faible en premier
const TAILLE_NDEF = 256;
const VERSION_CLE = 0x01;
// Capability Container NFC Forum Type 4 : fichier NDEF E104 de 256 octets, lecture libre, écriture interdite.
const CC = Buffer.from("000F20003B00340406E104010000FF", "hex");

export const estDesfire = (v: Buffer) => v[0] === 0x04 && v[1] === 0x01;
/** EV2 et EV3 (AuthenticateEV2First). Les EV1 et plus anciennes ne sont pas prises en charge. */
export const desfireCompatible = (v: Buffer) => estDesfire(v) && [0x12, 0x22, 0x33].includes(v[3]);

function tdes(sens: "enc" | "dec", cle: Buffer, iv: Buffer, data: Buffer): Buffer {
  const c = sens === "enc" ? createCipheriv("des-ede-cbc", cle, iv) : createDecipheriv("des-ede-cbc", cle, iv);
  c.setAutoPadding(false);
  return Buffer.concat([c.update(data), c.final()]);
}

async function ok(t: n.Transmit, apdu: Buffer, quoi: string): Promise<Buffer> {
  const [data, sw] = n.split(await t(apdu));
  if (sw !== 0x9000 && sw !== 0x9100) throw new n.CardError(sw, quoi);
  return data;
}
const o3 = (v: number) => Buffer.from([v & 0xff, (v >> 8) & 0xff, (v >> 16) & 0xff]);
const selectionner = (t: n.Transmit, aid: Buffer) => ok(t, n.native(0x5a, aid), aid.equals(PICC) ? "sélection de la carte" : "sélection de l'application");

/** Authentification ISO (1A) avec la clé DES d'usine 00…00. Renvoie les deux dérivations possibles de la
 *  clé de session (DES : A0-3 B0-3 doublé ; 2K3DES : A0-3 B0-3 A4-7 B4-7). */
export async function authDesUsine(t: n.Transmit, rndA: Buffer = randomBytes(8)): Promise<Buffer[]> {
  const cle = Buffer.alloc(16);
  let [data, sw] = n.split(await t(n.native(0x1a, Buffer.from([0x00]))));
  if (sw !== 0x91af || data.length !== 8) throw new n.CardError(sw, "authentification DES d'usine");
  const rndB = tdes("dec", cle, Buffer.alloc(8), data);
  const jeton = tdes("enc", cle, data, Buffer.concat([rndA, n.rotl(rndB)]));
  [data, sw] = n.split(await t(n.native(0xaf, jeton)));
  if (sw !== 0x9100 || data.length !== 8) throw new n.CardError(sw, "authentification DES d'usine");
  if (!tdes("dec", cle, jeton.subarray(8), data).equals(n.rotl(rndA))) throw new n.IntegrityError("la carte n'a pas prouvé qu'elle connaît la clé DES");
  const des = Buffer.concat([rndA.subarray(0, 4), rndB.subarray(0, 4)]);
  return [Buffer.concat([des, des]), Buffer.concat([rndA.subarray(0, 4), rndB.subarray(0, 4), rndA.subarray(4, 8), rndB.subarray(4, 8)])];
}

/** ChangeKey EV1 de la clé maître de la carte (clé authentifiée) : DES d'usine → AES. */
export function changeKeyPiccEv1(session: Buffer, nouvelle: Buffer): Buffer {
  const tete = Buffer.from([0xc4, 0x80]);                // 0x80 : la nouvelle clé maître est une clé AES
  const clair = Buffer.concat([nouvelle, Buffer.from([VERSION_CLE])]);
  let corps = Buffer.concat([clair, n.crc32nk(Buffer.concat([tete, clair]))]);
  corps = Buffer.concat([corps, Buffer.alloc((8 - (corps.length % 8)) % 8)]);
  return n.native(0xc4, Buffer.concat([tete.subarray(1), tdes("enc", session, Buffer.alloc(8), corps)]));
}

type EtatPicc = "des_usine" | "aes_usine" | "tapikard";

/** Authentifie la clé maître de la carte : DES d'usine, AES d'usine ou clé Tapikard (carte déjà programmée). */
async function ouvrirPicc(t: n.Transmit, ciblePicc: Buffer): Promise<{ etat: EtatPicc; session: n.Session | null }> {
  await selectionner(t, PICC);
  try {
    await authDesUsine(t);
    return { etat: "des_usine", session: null };
  } catch (e) {
    if (!(e instanceof n.CardError)) throw e;
  }
  for (const [etat, cle] of [["aes_usine", n.FACTORY_KEY], ["tapikard", ciblePicc]] as const) {
    await selectionner(t, PICC);
    try {
      return { etat, session: await n.authentifier(t, 0, cle) };
    } catch (e) {
      if (!(e instanceof n.CardError)) throw e;
    }
  }
  throw new n.CardError(0x91ae, "clé maître de la carte inconnue (DESFire déjà configurée par quelqu'un d'autre)");
}

/** Change la clé maître de la carte pour la clé Tapikard. Renvoie false si la carte a refusé (elle reste utilisable). */
async function verrouillerPicc(t: n.Transmit, etat: EtatPicc, cible: Buffer, log: (m: string) => void): Promise<boolean> {
  try {
    if (etat === "aes_usine") {
      await selectionner(t, PICC);
      const s = await n.authentifier(t, 0, n.FACTORY_KEY);
      await s.send(0xc4, Buffer.from([0x80]), Buffer.concat([cible, Buffer.from([VERSION_CLE])]), true, "clé maître de la carte");
      return true;
    }
    for (const session of [0, 1]) {
      await selectionner(t, PICC);
      const sessions = await authDesUsine(t);
      const [, sw] = n.split(await t(changeKeyPiccEv1(sessions[session], cible)));
      if (sw === 0x9100) return true;
      if (sw !== 0x911e) throw new n.CardError(sw, "clé maître de la carte");
    }
    throw new Error("la carte refuse le changement de clé");
  } catch (e) {
    log(`clé maître de la carte non changée (${e instanceof Error ? e.message : e})`);
    return false;
  }
}

/** Lit l'URL comme un téléphone (sélection NFC Forum standard). */
export async function lireUrlType4(t: n.Transmit): Promise<string> {
  await ok(t, Buffer.concat([Buffer.from("00A4040007", "hex"), n.NDEF_AID, Buffer.from([0])]), "sélection de l'application NFC");
  await ok(t, n.isoSelectFile(n.NDEF_FILE_ID), "sélection du fichier NDEF");
  const nlen = (await ok(t, n.isoReadBinary(0, 2), "lecture NDEF")).readUInt16BE(0);
  if (nlen === 0 || nlen > TAILLE_NDEF - 2) throw new Error("la carte ne contient pas d'URL");
  const corps = await ok(t, n.isoReadBinary(2, nlen), "lecture NDEF");
  return n.lireUri(Buffer.concat([Buffer.from([nlen >> 8, nlen & 0xff]), corps]));
}

/**
 * Programme une DESFire EV2/EV3 avec une URL fixe. Toute la puce est reformatée à chaque passage : l'opération
 * peut être relancée sans risque (carte retirée trop tôt, reprogrammation).
 * `cles` : clé maître de la carte et clé de l'application, diversifiées par puce.
 */
export async function programmerDesfire(
  t: n.Transmit, url: string, cles: { picc: Buffer; app: Buffer }, log: (m: string) => void = () => {},
): Promise<{ verrouillee: boolean }> {
  const fichier = n.fichierUrl(url);
  if (fichier.length > TAILLE_NDEF) throw new Error("URL trop longue");

  // 1. Clé maître de la carte, puis effacement complet (FormatPICC).
  const { etat, session } = await ouvrirPicc(t, cles.picc);
  if (session) await session.send(0xfc, undefined, undefined, false, "effacement de la carte");
  else await ok(t, n.native(0xfc), "effacement de la carte");
  log(etat === "tapikard" ? "carte Tapikard effacée (reprogrammation)" : "carte effacée");

  // 2. Application NFC Forum Type 4 et ses deux fichiers, créés en accès libre pour y écrire.
  await selectionner(t, PICC);
  await ok(t, n.native(0xca, Buffer.concat([AID, Buffer.from([0x0f, 0xa1]), ISO_DF, n.NDEF_AID])), "création de l'application NFC");
  await selectionner(t, AID);
  const creer = (no: number, fid: number, taille: number) =>
    ok(t, n.native(0xcd, Buffer.concat([Buffer.from([no, fid & 0xff, fid >> 8, 0x00, 0x00, 0xee]), o3(taille)])), "création d'un fichier");
  await creer(1, 0xe103, CC.length);
  await creer(2, 0xe104, TAILLE_NDEF);
  for (const [no, contenu] of [[1, CC], [2, fichier]] as const) {
    for (let off = 0; off < contenu.length; off += 40) {
      const bloc = contenu.subarray(off, off + 40);
      await ok(t, n.native(0x3d, Buffer.concat([Buffer.from([no]), o3(off), o3(bloc.length), bloc])), "écriture de l'URL");
    }
  }
  log("URL écrite");

  // 3. Fichiers en lecture seule (écriture réservée à la clé Tapikard), réglages figés, clé de l'application changée.
  const s = await n.authentifier(t, 0, n.FACTORY_KEY);
  for (const no of [1, 2]) await s.send(0x5f, Buffer.from([no]), Buffer.from([0x00, 0x00, 0xe0]), true, "droits des fichiers");
  await s.send(0x54, undefined, Buffer.from([0x09]), true, "réglages de l'application");
  await n.changeKey(s, 0, cles.app, VERSION_CLE);
  log("application verrouillée");

  // 4. Clé maître de la carte : sans elle, n'importe qui pourrait effacer la puce et y écrire une autre adresse.
  const verrouillee = etat === "tapikard" || (await verrouillerPicc(t, etat, cles.picc, log));
  if (verrouillee && etat !== "tapikard") log("carte verrouillée (clé maître Tapikard)");

  // 5. Contrôle, comme un téléphone.
  const lue = await lireUrlType4(t);
  if (lue !== url) throw new Error(`URL relue inattendue : ${lue}`);
  log("URL relue comme un téléphone");
  return { verrouillee };
}
