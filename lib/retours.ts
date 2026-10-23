// Retours produit, remboursements, archivage et suppression des commandes (back-office).
import { STRIPE_ENABLED } from "./config";
import { all, get, run, tx } from "./db";
import { emailRemboursement } from "./emails/messages";
import { sendMail } from "./mail";
import { rembourserStripe } from "./stripe";
import type { Order, User } from "./types";

export const MOTIFS = {
  defaut: "Carte défectueuse (puce, impression)",
  erreur: "Erreur de commande ou d’impression",
  retractation: "Rétractation (14 jours)",
  non_recu: "Colis non reçu ou abîmé",
  autre: "Autre",
} as const;
export type Motif = keyof typeof MOTIFS;

export const STATUT_RETOUR = { ouvert: "À traiter", rembourse: "Remboursé", refuse: "Refusé" } as const;

export type Retour = {
  id: number; order_id: string; motif: Motif; note: string | null; cartes: string; statut: keyof typeof STATUT_RETOUR;
  montant_cents: number; mode: "stripe" | "manuel" | null; stripe_refund_id: string | null; created_at: string; rembourse_at: string | null;
};

export const retoursDe = (orderId: string) => all<Retour>("SELECT * FROM retours WHERE order_id = ? ORDER BY created_at DESC, id DESC", orderId);
export const resteARembourser = (o: Order) => Math.max(0, o.amount_cents - (o.refunded_cents ?? 0));
export const stripePossible = (o: Order) => STRIPE_ENABLED && Boolean(o.stripe_session_id);

/** Enregistre un retour ; rembourse tout de suite si un montant et un mode sont donnés. */
export async function enregistrerRetour(r: {
  orderId: string; motif: Motif; note: string; cartes: string[]; desactiver: boolean; montantCents: number; mode: "stripe" | "manuel" | null;
}): Promise<string | null> {
  const o = get<Order>("SELECT * FROM orders WHERE id = ?", r.orderId);
  if (!o) return "Commande introuvable.";
  if (!(r.motif in MOTIFS)) return "Motif invalide.";
  const valides = new Set(all<{ id: string }>("SELECT id FROM cards WHERE order_id = ?", r.orderId).map((c) => c.id));
  const cartes = r.cartes.filter((c) => valides.has(c));
  const id = tx(() => {
    if (r.desactiver && cartes.length) {
      run(`UPDATE cards SET status = 'disabled' WHERE order_id = ? AND id IN (${cartes.map(() => "?").join(",")})`, r.orderId, ...cartes);
    }
    return Number(run("INSERT INTO retours (order_id, motif, note, cartes) VALUES (?, ?, ?, ?)", r.orderId, r.motif, r.note.slice(0, 1000) || null, JSON.stringify(cartes)).lastInsertRowid);
  });
  if (r.mode && r.montantCents > 0) return rembourser(id, r.montantCents, r.mode);
  return null;
}

/** Rembourse un retour (Stripe ou manuel, ex. virement) et prévient le client. */
export async function rembourser(retourId: number, montantCents: number, mode: "stripe" | "manuel"): Promise<string | null> {
  const r = get<Retour>("SELECT * FROM retours WHERE id = ?", retourId);
  if (!r) return "Retour introuvable.";
  if (r.statut !== "ouvert") return "Ce retour est déjà traité.";
  const o = get<Order>("SELECT * FROM orders WHERE id = ?", r.order_id)!;
  const reste = resteARembourser(o);
  if (!(montantCents > 0)) return "Montant à rembourser invalide.";
  if (montantCents > reste) return `Le montant dépasse ce qui reste à rembourser (${(reste / 100).toFixed(2).replace(".", ",")} €).`;
  let refundId: string | null = null;
  if (mode === "stripe") {
    if (!stripePossible(o)) return "Cette commande n’a pas été payée par Stripe : choisissez un remboursement manuel (virement).";
    try {
      refundId = await rembourserStripe(o.stripe_session_id!, montantCents);
    } catch (e) {
      return e instanceof Error ? e.message : "Remboursement Stripe impossible.";
    }
  }
  const total = montantCents === reste;
  tx(() => {
    run("UPDATE retours SET statut = 'rembourse', montant_cents = ?, mode = ?, stripe_refund_id = ?, rembourse_at = datetime('now') WHERE id = ?", montantCents, mode, refundId, retourId);
    run(`UPDATE orders SET refunded_cents = refunded_cents + ?, status = CASE WHEN ? THEN 'refunded' ELSE status END WHERE id = ?`, montantCents, total ? 1 : 0, o.id);
  });
  const u = get<User>("SELECT * FROM users WHERE id = ?", o.user_id);
  if (u) {
    const e = emailRemboursement(o, montantCents, total, u.name);
    void sendMail(u.email, e.sujet, e.html);
  }
  return null;
}

export function refuserRetour(retourId: number) {
  run("UPDATE retours SET statut = 'refuse' WHERE id = ? AND statut = 'ouvert'", retourId);
}

export function archiverCommande(orderId: string, archiver: boolean) {
  run("UPDATE orders SET archived_at = CASE WHEN ? THEN datetime('now') ELSE NULL END WHERE id = ?", archiver ? 1 : 0, orderId);
}

/** Suppression définitive : commande, cartes, scans et retours. Refusée si une carte est utilisée par un client. */
export function supprimerCommande(orderId: string): string | null {
  const actives = all<{ id: string }>("SELECT id FROM cards WHERE order_id = ? AND status = 'active'", orderId);
  if (actives.length) {
    return `Impossible : ${actives.length > 1 ? "des cartes de cette commande sont actives" : `la carte ${actives[0].id} est active`} chez le client. Enregistrez un retour en désactivant les cartes, puis supprimez.`;
  }
  tx(() => {
    run("DELETE FROM cards WHERE order_id = ?", orderId); // scans supprimés en cascade
    run("DELETE FROM retours WHERE order_id = ?", orderId);
    run("DELETE FROM orders WHERE id = ?", orderId);
  });
  return null;
}
