// Encodage d'une carte NTAG 424 DNA piloté par le serveur (le téléphone relaie les APDU).
// Même séquence que tools/encodeur/encodeur.py : reprise d'une carte interrompue, refus d'une carte tierce.
import * as n from "./ntag424.ts";
import { diversifyKey, verifySun } from "./sun.ts";
import { desfireCompatible, estDesfire, lireUrlType4, programmerDesfire } from "./desfire.ts";

export class EncodageError extends Error {}

export type Cles = { kMeta: Buffer; masterK1: Buffer; masterK0: Buffer };
export type Resultat = {
  uid: string; compteur: number; reparation: boolean; url: string;
  /** Nom de la puce ; `simple` : DESFire programmée avec une URL fixe (non sécurisée). */
  puce: string; simple: boolean; verrouillee?: boolean;
};

/** Carte DESFire détectée : l'opérateur doit accepter explicitement une carte non sécurisée. */
export class ConfirmationRequise extends Error {
  puce: string;
  constructor(puce: string) {
    super(`${puce} : carte non sécurisée, confirmation requise`);
    this.puce = puce;
  }
}

/** Clés de la DESFire (clé maître de la carte et clé de l'application), diversifiées par puce. */
export const clesDesfire = (c: Cles, uid: Buffer) => ({ picc: diversifyKey(c.masterK0, uid, 0x20), app: diversifyKey(c.masterK0, uid, 0x21) });

async function version(t: n.Transmit): Promise<Buffer | null> {
  try {
    return await n.lireVersion(t);
  } catch {
    return null;
  }
}

/** Lecture pour la vérification : URL et type de puce (NTAG 424 DNA signée, ou DESFire en NFC simple). */
export async function lireCarte(t: n.Transmit): Promise<{ url: string; simple: boolean; puce: string | null }> {
  const v = await version(t);
  if (v && estDesfire(v)) return { url: await lireUrlType4(t), simple: true, puce: n.decrireVersion(v).nom };
  return { url: await lireUrl(t), simple: false, puce: v ? n.decrireVersion(v).nom : null };
}

const VERSION_CLES = 0x01;

/** Clés du serveur ; l'encodage mobile exige aussi SUN_MASTER_K0 (la même que sur le poste d'encodage). */
export function clesServeur(): Cles | null {
  const hex = (v?: string) => (v && /^[0-9a-fA-F]{32}$/.test(v) && !/^0+$/.test(v) ? Buffer.from(v, "hex") : null);
  const kMeta = hex(process.env.SUN_K_META), masterK1 = hex(process.env.SUN_MASTER_K1), masterK0 = hex(process.env.SUN_MASTER_K0);
  return kMeta && masterK1 && masterK0 ? { kMeta, masterK1, masterK0 } : null;
}

export function clesDeLaCarte(c: Cles, uid: Buffer): Buffer[] {
  return [diversifyKey(c.masterK0, uid, 0), diversifyKey(c.masterK1, uid, 1), c.kMeta, diversifyKey(c.masterK0, uid, 3), diversifyKey(c.masterK0, uid, 4)];
}

async function ok(t: n.Transmit, apdu: Buffer, quoi: string): Promise<Buffer> {
  const [data, sw] = n.split(await t(apdu));
  if (sw !== 0x9000 && sw !== 0x9100) throw new n.CardError(sw, quoi);
  return data;
}

/** Sélectionne l'application NDEF ; si la puce ne l'a pas, l'identifie pour expliquer pourquoi. */
async function selectionnerNdef(t: n.Transmit) {
  const [, sw] = n.split(await t(n.isoSelectApp()));
  if (sw === 0x9000) return;
  let version: Buffer | null = null;
  try {
    version = await n.lireVersion(t);
  } catch {
    /* carte retirée ou muette : diagnostic sans version */
  }
  throw new EncodageError(n.diagnosticPuce(sw, version));
}

/** Lit l'URL comme un téléphone (déclenche le miroir SUN). */
export async function lireUrl(t: n.Transmit): Promise<string> {
  await selectionnerNdef(t);
  await ok(t, n.isoSelectFile(n.NDEF_FILE_ID), "sélection du fichier NDEF");
  const nlen = (await ok(t, n.isoReadBinary(0, 2), "lecture NDEF")).readUInt16BE(0);
  if (nlen === 0 || nlen > 254) throw new EncodageError("la carte ne contient pas d'URL");
  const corps = await ok(t, n.isoReadBinary(2, nlen), "lecture NDEF");
  const nl = Buffer.alloc(2);
  nl.writeUInt16BE(nlen);
  return n.lireUri(Buffer.concat([nl, corps]));
}

export function verifierUrl(url: string, c: Pick<Cles, "kMeta" | "masterK1">): { uid: string; compteur: number } {
  const q = new URL(url).searchParams;
  const r = verifySun(c.kMeta, (u) => diversifyKey(c.masterK1, u, 1), q.get("e") ?? "", q.get("m") ?? "");
  return { uid: r.uid, compteur: r.counter };
}

export async function encoderCarte(
  t: n.Transmit, baseUrl: string, cles: Cles, uidLecteur: Buffer | null, log: (m: string) => void = () => {},
  options: { cardId?: string; accepterNonSecurisee?: boolean } = {},
): Promise<Resultat> {
  // 0. Type de puce : une DESFire EV2/EV3 peut servir de dépannage, en NFC simple (URL fixe, sans signature).
  const v = await version(t);
  if (v && estDesfire(v)) {
    const { nom } = n.decrireVersion(v);
    if (!desfireCompatible(v)) throw new EncodageError(`Cette carte est une ${nom} : seules les DESFire EV2 et EV3 peuvent être programmées en NFC simple. Utilisez des cartes NTAG 424 DNA.`);
    if (!uidLecteur || !options.cardId) throw new EncodageError("UID ou identifiant de carte manquant");
    if (!options.accepterNonSecurisee) throw new ConfirmationRequise(nom);
    log(`${nom} : programmation en NFC simple (non sécurisée)`);
    const url = `${baseUrl.replace(/\/$/, "")}/q/${options.cardId}`;
    const r = await programmerDesfire(t, url, clesDesfire(cles, uidLecteur), log);
    return { uid: uidLecteur.toString("hex").toUpperCase(), compteur: 0, reparation: false, url, puce: nom, simple: true, verrouillee: r.verrouillee };
  }

  const gabarit = n.gabaritSun(baseUrl);

  // 1. URL : écriture libre sur une carte neuve (refusée si la carte est déjà verrouillée).
  await selectionnerNdef(t);
  await ok(t, n.isoSelectFile(n.NDEF_FILE_ID), "sélection du fichier NDEF");
  const [, sw] = n.split(await t(n.isoUpdateBinary(0, gabarit.fichier)));
  const urlEcrite = sw === 0x9000;
  if (![0x9000, 0x6982, 0x6985, 0x6986].includes(sw)) throw new n.CardError(sw, "écriture de l'URL");
  log(urlEcrite ? "URL écrite" : "carte déjà verrouillée : reprise avec les clés Tapikard");

  // 2. Clé 0 d'usine (carte neuve) ou diversifiée (carte déjà configurée).
  await ok(t, n.isoSelectApp(), "sélection de l'application NDEF");
  let cle0: Buffer = n.FACTORY_KEY;
  let reparation = false;
  let s: n.Session;
  try {
    s = await n.authentifier(t, 0, cle0);
  } catch (e) {
    if (!(e instanceof n.CardError) || e.sw !== 0x91ae || !uidLecteur) throw e;
    cle0 = clesDeLaCarte(cles, uidLecteur)[0];
    try {
      s = await n.authentifier(t, 0, cle0);
    } catch (e2) {
      if (e2 instanceof n.CardError && e2.sw === 0x91ae) throw new EncodageError("carte configurée avec d'autres clés (ni vierge, ni Tapikard)");
      throw e2;
    }
    reparation = true;
  }
  log(reparation ? "authentifiée (clé Tapikard, reprise)" : "authentifiée (clé d'usine)");
  const reauth = async () => {
    await ok(t, n.isoSelectApp(), "sélection de l'application NDEF");
    return n.authentifier(t, 0, cle0);
  };

  // 3. UID réel et clés propres à la carte.
  const uid = await n.getCardUid(s);
  if (uidLecteur && !uid.equals(uidLecteur)) throw new EncodageError(`UID incohérent (${uidLecteur.toString("hex")} / ${uid.toString("hex")})`);
  const cibles = clesDeLaCarte(cles, uid);

  // 4. Carte verrouillée : réécriture de l'URL avec WriteData, puis nouvelle session.
  if (!urlEcrite) {
    const f = gabarit.fichier;
    const o3 = (v: number) => Buffer.from([v & 0xff, (v >> 8) & 0xff, (v >> 16) & 0xff]);
    await ok(t, n.native(0x8d, Buffer.concat([Buffer.from([n.NDEF_FILE_NO]), o3(0), o3(f.length), f])), "réécriture de l'URL");
    s = await reauth();
  }

  // 5. Miroir SUN, puis clés 1 à 4 (ancienne valeur : d'usine ou déjà la bonne), clé 0 en dernier.
  await n.changeFileSettings(s, gabarit.sdm);
  log("SUN configuré");
  for (const k of [1, 2, 3, 4]) {
    let fait = false;
    for (const ancienne of [n.FACTORY_KEY, cibles[k]]) {
      try {
        await n.changeKey(s, k, cibles[k], VERSION_CLES, ancienne);
        fait = true;
        break;
      } catch (e) {
        if (!(e instanceof n.CardError) || e.sw !== 0x911e) throw e;
        s = await reauth();
      }
    }
    if (!fait) throw new EncodageError(`clé ${k} : valeur actuelle inconnue`);
  }
  log("clés 1 à 4 installées");
  if (!cle0.equals(cibles[0])) {
    await n.changeKey(s, 0, cibles[0], VERSION_CLES);
    log("clé 0 installée");
  }

  // 6. Contrôle final, comme un téléphone puis comme le serveur.
  const url = await lireUrl(t);
  if (!url.startsWith(baseUrl.replace(/\/$/, "") + "/c?")) throw new EncodageError(`URL inattendue sur la carte : ${url}`);
  const sun = verifierUrl(url, cles);
  if (sun.uid !== uid.toString("hex").toUpperCase()) throw new EncodageError("la signature ne correspond pas à cette puce");
  log(`signature vérifiée (compteur ${sun.compteur})`);
  return { uid: sun.uid, compteur: sun.compteur, reparation, url, puce: "NTAG 424 DNA", simple: false };
}
