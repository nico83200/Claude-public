"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { requireAdmin } from "@/lib/auth";
import { run } from "@/lib/db";
import { approveProof, createBasicStock, createBatch, markOrderPaid, registerEncodedCard, shipOrder } from "@/lib/orders";

export async function adminMarkPaid(orderId: string) {
  await requireAdmin();
  markOrderPaid(orderId);
  revalidatePath(`/admin/commandes/${orderId}`);
}

export async function adminApproveProof(orderId: string) {
  await requireAdmin();
  approveProof(orderId);
  revalidatePath(`/admin/commandes/${orderId}`);
}

export async function adminShip(orderId: string, form: FormData) {
  await requireAdmin();
  const err = shipOrder(orderId, String(form.get("tracking") ?? "").trim().slice(0, 80));
  redirect(`/admin/commandes/${orderId}${err ? `?erreur=${encodeURIComponent(err)}` : ""}`);
}

export async function adminCancel(orderId: string) {
  await requireAdmin();
  run("UPDATE orders SET status = 'cancelled' WHERE id = ? AND status IN ('pending_payment', 'paid', 'proof_approved')", orderId);
  run("DELETE FROM cards WHERE order_id = ? AND status = 'provisioned' AND batch_id IS NULL", orderId);
  revalidatePath(`/admin/commandes/${orderId}`);
}

export async function adminCreateBatch(form: FormData) {
  await requireAdmin();
  const b = createBatch(String(form.get("note") ?? "").trim().slice(0, 200) || undefined);
  redirect(`/admin/production${b ? `?lot=${b.id}` : "?vide=1"}`);
}

/** Saisie manuelle (dépannage) : équivalent de l'appel API du poste d'encodage. */
export async function adminRegisterEncoding(form: FormData) {
  await requireAdmin();
  const cardId = String(form.get("cardId") ?? "").trim();
  const err = registerEncodedCard(cardId, String(form.get("uid") ?? "").trim().toUpperCase(), 0);
  redirect(`/admin/production?${err ? `erreur=${encodeURIComponent(err)}` : `encodee=${cardId}`}`);
}

export async function adminCreateBasicStock(form: FormData) {
  await requireAdmin();
  const variant = form.get("variant") === "blanc" ? "blanc" : "noir";
  const qty = Math.floor(Number(form.get("quantity")));
  if (!(qty >= 1 && qty <= 1000)) redirect("/admin/production?erreur=" + encodeURIComponent("Quantité entre 1 et 1000."));
  const b = createBasicStock(variant, qty, String(form.get("note") ?? "").trim().slice(0, 200) || undefined);
  redirect(`/admin/production?lot=${b.id}`);
}
