"use server";

import { redirect } from "next/navigation";
import { requireUser } from "@/lib/auth";
import { get, run } from "@/lib/db";
import { randomId } from "@/lib/ids";
import { rateLimit } from "@/lib/ratelimit";
import type { Card } from "@/lib/types";

export type ActivateState = { error?: string };

export async function activateCard(_prev: ActivateState, form: FormData): Promise<ActivateState> {
  const user = await requireUser("/activer");
  const cardId = String(form.get("cardId") ?? "").trim();
  const code = String(form.get("code") ?? "").trim().toUpperCase().replace(/[^A-Z0-9]/g, "");
  if (!rateLimit(`activate:${user.id}`, 10, 15 * 60_000)) return { error: "Trop de tentatives, réessayez plus tard." };
  const card = get<Card>("SELECT * FROM cards WHERE id = ?", cardId);
  if (!card) return { error: "Carte introuvable." };
  if (card.status === "active") return { error: "Cette carte est déjà active." };
  if (card.status === "lost") return { error: "Cette carte a été déclarée perdue." };
  if (card.status === "provisioned") return { error: "Cette carte n’a pas encore été produite." };

  // Le propriétaire de la commande active sans code ; sinon le code de l'encart est exigé.
  const isOwner = card.user_id === user.id;
  if (!isOwner && card.activation_code.replace(/-/g, "") !== code) return { error: "Code d’activation incorrect." };

  let profileId = card.profile_id;
  if (!isOwner || !profileId) {
    profileId = get<{ id: string }>("SELECT id FROM profiles WHERE user_id = ? ORDER BY created_at LIMIT 1", user.id)?.id ?? null;
  }
  // Carte Basic reçue sans compte préalable : on crée le profil à compléter.
  const newProfile = !profileId;
  if (!profileId) {
    profileId = randomId(12);
    run("INSERT INTO profiles (id, user_id, display_name, email) VALUES (?, ?, ?, ?)", profileId, user.id, user.name ?? "", user.email);
  }
  run("UPDATE cards SET status = 'active', user_id = ?, profile_id = ?, activated_at = datetime('now') WHERE id = ?", user.id, profileId, card.id);
  if (card.order_id) run("UPDATE orders SET status = 'delivered' WHERE id = ? AND status = 'shipped'", card.order_id);
  redirect(newProfile ? `/compte/profils/${profileId}?bienvenue=1` : `/compte/cartes?activee=${card.id}`);
}
