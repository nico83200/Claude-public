import { all, get, run, tx } from "./db";
import { activationCode, randomId } from "./ids";
import { sendMail } from "./mail";
import { APP_URL } from "./config";
import type { Card, Order, User } from "./types";

export function getOrder(id: string) {
  return get<Order>("SELECT * FROM orders WHERE id = ?", id);
}

export function orderCards(orderId: string) {
  return all<Card>("SELECT * FROM cards WHERE order_id = ? ORDER BY created_at, id", orderId);
}

/** Paiement confirmé : crée les cartes (une URL/QR unique chacune), toutes liées au profil de la commande. */
export function markOrderPaid(orderId: string): boolean {
  const done = tx(() => {
    const o = getOrder(orderId);
    if (!o || o.status !== "pending_payment") return false;
    // Une carte Basic (design fixe) n'a pas de BAT à valider : elle part directement en production.
    if (o.product === "basic") {
      run("UPDATE orders SET status = 'proof_approved', paid_at = datetime('now'), proof_approved_at = datetime('now') WHERE id = ?", orderId);
    } else {
      run("UPDATE orders SET status = 'paid', paid_at = datetime('now') WHERE id = ?", orderId);
    }
    for (let i = 0; i < o.quantity; i++) {
      run(
        "INSERT INTO cards (id, order_id, user_id, profile_id, activation_code, label) VALUES (?, ?, ?, ?, ?, ?)",
        randomId(10), o.id, o.user_id, o.profile_id, activationCode(), o.quantity > 1 ? `Carte ${i + 1}` : "Ma carte",
      );
    }
    return true;
  });
  if (done) {
    const u = get<User>("SELECT u.* FROM users u JOIN orders o ON o.user_id = u.id WHERE o.id = ?", orderId);
    const basic = getOrder(orderId)?.product === "basic";
    if (u) {
      void sendMail(
        u.email,
        basic ? "Commande confirmée" : "Commande confirmée — validez votre BAT",
        basic
          ? `<p>Merci pour votre commande !</p><p>Vos cartes Tapikard sont en préparation. Suivi : <a href="${APP_URL}/commande/${orderId}">voir ma commande</a>.</p>`
          : `<p>Merci pour votre commande !</p><p>Validez le bon à tirer pour lancer la production : <a href="${APP_URL}/commande/${orderId}">voir ma commande</a>.</p>`,
      );
    }
  }
  return done;
}

export function approveProof(orderId: string): boolean {
  return run("UPDATE orders SET status = 'proof_approved', proof_approved_at = datetime('now'), proof_comment = NULL WHERE id = ? AND status = 'paid'", orderId).changes > 0;
}

/** Regroupe toutes les cartes à produire (commandes au BAT validé) dans un nouveau lot. */
export function createBatch(note?: string): { id: string; count: number } | null {
  return tx(() => {
    const cards = all<{ id: string; order_id: string }>(
      `SELECT c.id, c.order_id FROM cards c JOIN orders o ON o.id = c.order_id
       WHERE c.batch_id IS NULL AND c.status = 'provisioned' AND o.status = 'proof_approved'`,
    );
    if (cards.length === 0) return null;
    const id = `LOT-${new Date().toISOString().slice(0, 10).replace(/-/g, "")}-${randomId(4).toUpperCase()}`;
    run("INSERT INTO batches (id, note) VALUES (?, ?)", id, note ?? null);
    for (const c of cards) run("UPDATE cards SET batch_id = ? WHERE id = ?", id, c.id);
    for (const oid of new Set(cards.map((c) => c.order_id))) run("UPDATE orders SET status = 'in_production' WHERE id = ?", oid);
    return { id, count: cards.length };
  });
}

/** Stock de cartes Basic non attribuées (salons, revendeurs) : un lot prêt à imprimer et encoder.
 *  Chaque carte est activée par la personne qui la reçoit, grâce au code de l'encart. */
export function createBasicStock(variant: "noir" | "blanc", quantity: number, note?: string): { id: string; count: number } {
  return tx(() => {
    const id = `STOCK-${new Date().toISOString().slice(0, 10).replace(/-/g, "")}-${randomId(4).toUpperCase()}`;
    run("INSERT INTO batches (id, note) VALUES (?, ?)", id, note ?? `Stock Basic ${variant}`);
    for (let i = 0; i < quantity; i++) {
      run("INSERT INTO cards (id, batch_id, variant, activation_code, label) VALUES (?, ?, ?, ?, 'Carte Basic')", randomId(10), id, variant, activationCode());
    }
    return { id, count: quantity };
  });
}

/** Expédition : uniquement si toutes les cartes de la commande sont encodées et vérifiées. */
export function shipOrder(orderId: string, tracking: string): string | null {
  const cards = orderCards(orderId);
  if (cards.some((c) => c.status === "provisioned")) return "Toutes les cartes doivent être encodées avant l'expédition.";
  tx(() => {
    run("UPDATE orders SET status = 'shipped', shipped_at = datetime('now'), tracking = ? WHERE id = ?", tracking || null, orderId);
    run("UPDATE cards SET status = 'shipped' WHERE order_id = ? AND status = 'encoded'", orderId);
  });
  const u = get<User>("SELECT u.* FROM users u JOIN orders o ON o.user_id = u.id WHERE o.id = ?", orderId);
  if (u) void sendMail(u.email, "Vos cartes Tapikard sont en route", `<p>Vos cartes ont été expédiées${tracking ? ` (suivi : ${tracking})` : ""}.</p><p>À réception, scannez une carte avec votre téléphone pour l'activer.</p>`);
  return null;
}

/** Enregistrement par le poste d'encodage : liaison cardId ↔ UID de la puce. */
export function registerEncodedCard(cardId: string, uid: string, counter: number): string | null {
  const card = get<Card>("SELECT * FROM cards WHERE id = ?", cardId);
  if (!card) return "Carte inconnue";
  if (!/^[0-9A-F]{14}$/.test(uid)) return "UID invalide (7 octets hex attendus)";
  if (card.status !== "provisioned" && card.uid !== uid) return `Carte déjà encodée (statut ${card.status})`;
  const other = get<{ id: string }>("SELECT id FROM cards WHERE uid = ? AND id <> ?", uid, cardId);
  if (other) return `Cette puce est déjà associée à la carte ${other.id}`;
  run(
    "UPDATE cards SET uid = ?, last_counter = MAX(last_counter, ?), status = CASE status WHEN 'provisioned' THEN 'encoded' ELSE status END, encoded_at = COALESCE(encoded_at, datetime('now')) WHERE id = ?",
    uid, counter, cardId,
  );
  return null;
}
