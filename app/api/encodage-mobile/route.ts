import { NextResponse } from "next/server";
import { currentUser } from "@/lib/auth";
import { APP_URL } from "@/lib/config";
import { get } from "@/lib/db";
import { ConfirmationRequise, EncodageError, clesServeur, encoderCarte, lireCarte, verifierUrl } from "@/lib/encodeurServeur";
import { problemeUid, registerEncodedCard } from "@/lib/orders";
import { Relais, autreProcessus, fermer, ouvrir, trouver, type Etape } from "@/lib/relaisNfc";
import { CARD_STATUS_LABEL, type Card } from "@/lib/types";

/**
 * Encodage depuis l'application Android : le téléphone relaie les APDU entre la carte et ce serveur,
 * qui fait toute la cryptographie (les clés ne quittent jamais le serveur).
 *   { action: "demarrer", cardId, uid, nonSecurisee? } → { session, apdu } | { erreur }
 *     (carte DESFire sans nonSecurisee : { termine, resultat: { confirmer: nom de la puce } })
 *   { action: "verifier", uid }           → idem, lecture et contrôle de la signature
 *   { action: "reponse", session, reponse }→ { apdu } | { termine, resultat } | { erreur }
 *   { action: "annuler", session }
 */
async function repondre<R>(relais: Relais<R>, etape: Etape<R>) {
  if (etape.type === "apdu") return NextResponse.json({ session: relais.id, apdu: etape.apdu.toString("hex").toUpperCase() });
  fermer(relais.id);
  if (etape.type === "fin") return NextResponse.json({ termine: true, resultat: etape.resultat, journal: relais.journal });
  return NextResponse.json({ erreur: etape.message, journal: relais.journal });
}

export async function POST(req: Request) {
  const user = await currentUser();
  if (!user?.is_admin) return NextResponse.json({ erreur: "Réservé aux administrateurs : connectez-vous au back-office." }, { status: 401 });
  const body = await req.json().catch(() => null);
  if (!body || typeof body.action !== "string") return NextResponse.json({ erreur: "Requête invalide." }, { status: 400 });

  if (body.action === "reponse" || body.action === "annuler") {
    const id = String(body.session ?? "");
    const relais = trouver(id, user.id);
    if (!relais) {
      const erreur = autreProcessus(id)
        ? "Le serveur a changé de processus pendant l’encodage (redémarrage, ou hébergement réparti sur plusieurs processus) : reposez la carte. Si cela se répète, l’encodage mobile n’est pas possible sur cet hébergement : utilisez le poste avec lecteur USB."
        : "Session expirée : reposez la carte.";
      return NextResponse.json({ erreur }, { status: 410 });
    }
    if (body.action === "annuler") {
      relais.abandonner();
      fermer(relais.id);
      return NextResponse.json({ ok: true });
    }
    const hex = String(body.reponse ?? "");
    relais.fournir(/^([0-9A-Fa-f]{2})+$/.test(hex) ? Buffer.from(hex, "hex") : new Error(`communication avec la carte interrompue${hex ? ` (${hex.slice(0, 80)})` : ""}`));
    return repondre(relais, await relais.prochaineEtape());
  }

  const cles = clesServeur();
  if (!cles) return NextResponse.json({ erreur: "Encodage mobile indisponible : SUN_K_META, SUN_MASTER_K1 et SUN_MASTER_K0 doivent être configurées sur le serveur." }, { status: 503 });
  const uidHex = String(body.uid ?? "").replace(/[\s:]/g, "").toUpperCase();
  const probleme = problemeUid(uidHex);
  if (probleme) return NextResponse.json({ erreur: probleme }, { status: 422 });
  const uid = Buffer.from(uidHex, "hex");

  if (body.action === "verifier") {
    const relais = new Relais(user.id, async (t) => {
      const lu = await lireCarte(t, body.techno === "nfca" ? "nfca" : "isodep");
      // DESFire en NFC simple : pas de signature à vérifier, seulement l'URL fixe.
      const v = lu.simple ? { uid: uidHex, compteur: 0 } : verifierUrl(lu.url, cles);
      const carte = get<Card>("SELECT id, status FROM cards WHERE uid = ?", v.uid);
      return { url: lu.url, simple: lu.simple, puce: lu.puce, ...v, carte: carte ? { id: carte.id, statut: CARD_STATUS_LABEL[carte.status].toLowerCase() } : null };
    });
    ouvrir(relais);
    return repondre(relais, await relais.prochaineEtape());
  }

  if (body.action === "demarrer") {
    const cardId = String(body.cardId ?? "");
    const carte = get<Card>("SELECT * FROM cards WHERE id = ?", cardId);
    if (!carte) return NextResponse.json({ erreur: "Carte inconnue." }, { status: 404 });
    if (carte.status !== "provisioned" && carte.uid !== uidHex) {
      return NextResponse.json({ erreur: `La carte ${cardId} est déjà encodée avec une autre puce (${carte.uid ?? "?"}).` }, { status: 409 });
    }
    const autre = get<{ id: string }>("SELECT id FROM cards WHERE uid = ? AND id <> ?", uidHex, cardId);
    if (autre) return NextResponse.json({ erreur: `Cette puce est déjà associée à la carte ${autre.id}.` }, { status: 409 });
    const relais = new Relais(user.id, async (t, log) => {
      let r;
      try {
        r = await encoderCarte(t, APP_URL, cles, uid, log, { cardId, accepterNonSecurisee: body.nonSecurisee === true, techno: body.techno === "nfca" ? "nfca" : "isodep" });
      } catch (e) {
        if (e instanceof ConfirmationRequise) return { confirmer: e.puce };
        throw e;
      }
      const err = registerEncodedCard(cardId, r.uid, r.compteur, r.simple ? (r.puce.startsWith("NTAG2") ? "ntag21x" : "desfire") : null);
      if (err) throw new EncodageError(err);
      return { cardId, uid: r.uid, compteur: r.compteur, reparation: r.reparation, puce: r.puce, simple: r.simple, verrouillee: r.verrouillee };
    });
    ouvrir(relais);
    return repondre(relais, await relais.prochaineEtape());
  }
  return NextResponse.json({ erreur: "Action inconnue." }, { status: 400 });
}
