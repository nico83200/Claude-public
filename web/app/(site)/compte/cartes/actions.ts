"use server";

import { revalidatePath } from "next/cache";
import { requireUser } from "@/lib/auth";
import { get, run } from "@/lib/db";
import type { Card } from "@/lib/types";

async function ownedCard(id: string) {
  const user = await requireUser();
  const card = get<Card>("SELECT * FROM cards WHERE id = ? AND user_id = ?", id, user.id);
  if (!card) throw new Error("Carte introuvable");
  return { user, card };
}

export async function updateCard(id: string, form: FormData) {
  const { user } = await ownedCard(id);
  const profileId = String(form.get("profile_id") ?? "");
  const label = String(form.get("label") ?? "").trim().slice(0, 40) || null;
  if (profileId && !get("SELECT id FROM profiles WHERE id = ? AND user_id = ?", profileId, user.id)) throw new Error("Profil invalide");
  run("UPDATE cards SET profile_id = ?, label = ? WHERE id = ?", profileId || null, label, id);
  revalidatePath("/compte/cartes");
}

export async function setCardStatus(id: string, status: "active" | "disabled" | "lost") {
  const { card } = await ownedCard(id);
  const allowed: Record<string, string[]> = {
    disabled: ["active"],
    active: ["disabled", "shipped", "encoded"],
    lost: ["active", "disabled", "shipped"],
  };
  if (!allowed[status].includes(card.status)) return;
  run(`UPDATE cards SET status = ?, activated_at = COALESCE(activated_at, CASE WHEN ? = 'active' THEN datetime('now') END) WHERE id = ?`, status, status, id);
  if (status === "active" && card.order_id) run("UPDATE orders SET status = 'delivered' WHERE id = ? AND status = 'shipped'", card.order_id);
  revalidatePath("/compte/cartes");
}
