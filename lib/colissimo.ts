// Étiquettes La Poste via l'API Colissimo « SLS » (Shipping Label Service, REST v2.0) : un appel renvoie le
// numéro de suivi et l'étiquette PDF prête à imprimer. Nécessite un contrat Colissimo (espace Colissimo Box /
// Entreprise) : numéro de contrat et mot de passe d'accès aux services web.
// Sans contrat, le back-office propose une étiquette d'adresse à imprimer (lettre suivie, Colissimo au guichet).
import type { Order, Shipping, User } from "./types";

export const COLISSIMO_URL = process.env.COLISSIMO_URL ?? "https://ws.colissimo.fr/sls-ws/SlsServiceWSRest/2.0/generateLabel";

export type Expediteur = { nom: string; adresse: string; cp: string; ville: string; tel?: string; email?: string };

export function expediteur(): Expediteur | null {
  const e = process.env;
  if (!e.EXPEDITEUR_ADRESSE || !e.EXPEDITEUR_CP || !e.EXPEDITEUR_VILLE) return null;
  return { nom: e.EXPEDITEUR_NOM || "Tapikard", adresse: e.EXPEDITEUR_ADRESSE, cp: e.EXPEDITEUR_CP, ville: e.EXPEDITEUR_VILLE, tel: e.EXPEDITEUR_TEL, email: e.EXPEDITEUR_EMAIL };
}

export const colissimoConfigure = () => Boolean(process.env.COLISSIMO_CONTRAT && process.env.COLISSIMO_MOT_DE_PASSE && expediteur());

/** Sépare « Prénom Nom » (le dernier mot est le nom). */
function nomPrenom(nom: string): { firstName: string; lastName: string } {
  const p = nom.trim().split(/\s+/);
  const lastName = p.length > 1 ? p.pop()! : p[0] ?? "";
  return { firstName: p.length ? p.join(" ") : "", lastName };
}

export function requeteEtiquette(o: Order, s: Shipping, client: Pick<User, "email">, date = new Date()) {
  const exp = expediteur()!;
  const dest = nomPrenom(s.name);
  return {
    contractNumber: process.env.COLISSIMO_CONTRAT,
    password: process.env.COLISSIMO_MOT_DE_PASSE,
    outputFormat: { x: 0, y: 0, outputPrintingType: process.env.COLISSIMO_FORMAT ?? "PDF_10x15_300dpi" },
    letter: {
      service: {
        productCode: process.env.COLISSIMO_PRODUIT ?? "DOM", // DOM : Colissimo Domicile sans signature
        depositDate: date.toISOString().slice(0, 10),
        orderNumber: o.id,
        commercialName: exp.nom,
      },
      parcel: { weight: Number(process.env.COLISSIMO_POIDS_KG ?? 0.1) },
      sender: {
        senderParcelRef: o.id,
        address: { companyName: exp.nom, line2: exp.adresse, countryCode: "FR", city: exp.ville, zipCode: exp.cp, phoneNumber: exp.tel, email: exp.email },
      },
      addressee: {
        addresseeParcelRef: o.id,
        address: {
          lastName: dest.lastName, firstName: dest.firstName,
          line2: s.address, line3: s.address2 || undefined,
          countryCode: s.country || "FR", city: s.city, zipCode: s.zip,
          email: client.email, mobileNumber: s.phone?.replace(/[^\d+]/g, "") || undefined,
        },
      },
    },
  };
}

/** Réponse multipart (MTOM) : une partie JSON (numéro de colis ou erreurs) et une partie binaire (PDF). */
export function lireReponse(contentType: string, corps: Buffer): { numero: string; pdf: Buffer } {
  const limite = contentType.match(/boundary="?([^";]+)"?/)?.[1];
  const parties: { entetes: string; contenu: Buffer }[] = [];
  if (limite) {
    const sep = Buffer.from(`--${limite}`);
    let i = corps.indexOf(sep);
    while (i >= 0) {
      const debut = i + sep.length;
      const fin = corps.indexOf(sep, debut);
      if (fin < 0) break;
      const bloc = corps.subarray(debut, fin);
      const k = bloc.indexOf("\r\n\r\n");
      if (k >= 0) {
        let contenu = bloc.subarray(k + 4);
        if (contenu.subarray(-2).toString() === "\r\n") contenu = contenu.subarray(0, -2);
        parties.push({ entetes: bloc.subarray(0, k).toString(), contenu });
      }
      i = fin;
    }
  } else {
    parties.push({ entetes: "content-type: application/json", contenu: corps });
  }
  const json = parties.find((p) => /json/i.test(p.entetes));
  const info = json ? JSON.parse(json.contenu.toString("utf8")) : null;
  const erreurs = (info?.messages ?? []).filter((m: { type: string }) => m.type === "ERROR");
  if (erreurs.length) throw new Error(`Colissimo : ${erreurs.map((m: { id: string; messageContent: string }) => `${m.messageContent} (${m.id})`).join(" ; ")}`);
  const numero = info?.labelV2Response?.parcelNumber;
  const pdf = parties.find((p) => p !== json && p.contenu.subarray(0, 4).toString() === "%PDF")?.contenu;
  if (!numero || !pdf) throw new Error("Colissimo : réponse inattendue (pas de numéro de colis ou pas d'étiquette)");
  return { numero, pdf };
}

/** Demande l'étiquette à Colissimo : numéro de suivi et PDF. */
export async function genererEtiquette(o: Order, client: Pick<User, "email">): Promise<{ numero: string; pdf: Buffer }> {
  if (!colissimoConfigure()) throw new Error("Colissimo n'est pas configuré (COLISSIMO_CONTRAT, COLISSIMO_MOT_DE_PASSE, adresse d'expédition).");
  const s = JSON.parse(o.shipping) as Shipping;
  if (!s.address || !s.zip || !s.city) throw new Error("Adresse de livraison incomplète sur la commande.");
  const res = await fetch(COLISSIMO_URL, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(requeteEtiquette(o, s, client)),
    signal: AbortSignal.timeout(30_000),
  });
  const corps = Buffer.from(await res.arrayBuffer());
  return lireReponse(res.headers.get("content-type") ?? "", corps);
}
