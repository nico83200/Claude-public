"use server";

import { revalidatePath } from "next/cache";
import { approveProof, getOrder, markOrderPaid } from "@/lib/orders";
import { STRIPE_ENABLED } from "@/lib/config";
import { run } from "@/lib/db";

// L'identifiant de commande (cmd_ + 16 caractères aléatoires) sert de jeton d'accès à cette page,
// comme un lien de suivi envoyé par email. Les actions restent limitées au parcours de la commande.

export async function simulatePayment(orderId: string) {
  if (STRIPE_ENABLED) throw new Error("Paiement simulé désactivé quand Stripe est configuré");
  markOrderPaid(orderId);
  revalidatePath(`/commande/${orderId}`);
}

export async function approveOrderProof(orderId: string) {
  approveProof(orderId);
  revalidatePath(`/commande/${orderId}`);
}

export async function requestProofChange(orderId: string, form: FormData) {
  const comment = String(form.get("comment") ?? "").trim().slice(0, 1000);
  const o = getOrder(orderId);
  if (!o || o.status !== "paid" || !comment) return;
  run("UPDATE orders SET proof_comment = ? WHERE id = ?", comment, orderId);
  revalidatePath(`/commande/${orderId}`);
}
