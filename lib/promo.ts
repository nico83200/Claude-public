// Code « entourage » : chaque client reçoit un code personnel qui offre 25 % supplémentaires à ses proches.
import { randomInt } from "node:crypto";
import { all, get, run } from "./db";
import type { User } from "./types";

export const REMISE_ENTOURAGE = 0.25;
/** Durée de validité d'un code, à compter de sa création (première commande payée du client). */
export const VALIDITE_SQL = "+1 month";

export type CodeValide = { code: string; fin: string };

/** Date de fin lisible : « 24 novembre 2026 ». */
export const finLisible = (fin: string) => new Date(fin + "T12:00:00Z").toLocaleDateString("fr-FR", { day: "numeric", month: "long", year: "numeric" });

/** Code encore valable du client, s'il en a un. */
export function codeActif(userId: string): CodeValide | null {
  return get<CodeValide>(
    `SELECT code, date(created_at, '${VALIDITE_SQL}') fin FROM codes_promo
     WHERE user_id = ? AND actif = 1 AND datetime(created_at, '${VALIDITE_SQL}') > datetime('now') ORDER BY created_at DESC LIMIT 1`, userId,
  ) ?? null;
}

export type CodePromo = { code: string; user_id: string; remise: number; actif: number; created_at: string };

export const normaliserCode = (c: string) => c.trim().toUpperCase().replace(/[^A-Z0-9-]/g, "").slice(0, 24);

/** Code du client, valable un mois : propre à lui, mais aléatoire (rien ne permet de le deviner ni de remonter
 *  au client), ex. K7QM-2XRA. Alphabet sans 0/O/1/I. Un nouveau code est créé à une commande payée après expiration. */
export function codeDe(user: Pick<User, "id" | "name" | "email">): CodeValide {
  const existant = codeActif(user.id);
  if (existant) return existant;
  const A = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
  for (;;) {
    const c = Array.from({ length: 8 }, () => A[randomInt(A.length)]).join("");
    const code = `${c.slice(0, 4)}-${c.slice(4)}`;
    if (!get("SELECT 1 FROM codes_promo WHERE code = ?", code)) {
      run("INSERT INTO codes_promo (code, user_id, remise, aleatoire) VALUES (?, ?, ?, 1)", code, user.id, REMISE_ENTOURAGE);
      return codeActif(user.id)!;
    }
  }
}

/** Vérifie un code pour une commande passée avec cet email. Un client ne peut pas utiliser son propre code. */
export function verifierCode(saisi: string, email: string | null): { ok: true; code: CodePromo } | { ok: false; erreur: string } {
  const code = get<CodePromo & { valide: number; fin: string }>(
    `SELECT *, datetime(created_at, '${VALIDITE_SQL}') > datetime('now') valide, date(created_at, '${VALIDITE_SQL}') fin FROM codes_promo WHERE code = ? AND actif = 1`,
    normaliserCode(saisi));
  if (!code) return { ok: false, erreur: "Code promo inconnu." };
  if (!code.valide) return { ok: false, erreur: `Ce code a expiré le ${finLisible(code.fin)} (il est valable un mois).` };
  if (email) {
    const titulaire = get<{ email: string }>("SELECT email FROM users WHERE id = ?", code.user_id);
    if (titulaire?.email === email) return { ok: false, erreur: "Ce code est réservé à vos proches : partagez-le !" };
  }
  return { ok: true, code };
}

export const appliquerRemise = (totalCents: number, remise: number) => {
  const remiseCents = Math.round(totalCents * remise);
  return { total: totalCents - remiseCents, remiseCents };
};

/** Commandes payées passées avec le code du client (pour son espace). */
export function utilisationsDe(userId: string): number {
  return all<{ n: number }>(
    "SELECT COUNT(*) n FROM orders o JOIN codes_promo c ON c.code = o.code_promo WHERE c.user_id = ? AND o.paid_at IS NOT NULL", userId,
  )[0]?.n ?? 0;
}
