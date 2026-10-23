"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { requireAdmin } from "@/lib/auth";
import { run } from "@/lib/db";
import { STOCK_OPTIONS, stockKey } from "@/lib/catalog";
import { enregistrerRuptures } from "@/lib/stock";
import { emailParId } from "@/lib/emails/catalogue";
import type { Outil } from "@/lib/emails/campagnes";
import { sendMail } from "@/lib/mail";
import { archiverCommande, enregistrerRetour, rembourser, refuserRetour, supprimerCommande, type Motif } from "@/lib/retours";
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
  const err = registerEncodedCard(cardId, String(form.get("uid") ?? ""), 0);
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

export async function adminEnregistrerStocks(form: FormData) {
  await requireAdmin();
  enregistrerRuptures(
    STOCK_OPTIONS.flatMap((g) => g.items.map((i) => {
      const cle = stockKey(g.kind, i.id);
      return { cle, rupture: form.get(`rupture:${cle}`) === "on", message: String(form.get(`message:${cle}`) ?? "") };
    })),
  );
  revalidatePath("/", "layout");
  redirect("/admin/stocks?enregistre=1");
}

export async function adminEnvoyerTestEmail(id: string, outil: Outil) {
  const admin = await requireAdmin();
  const trouve = emailParId(id, outil);
  if (!trouve) redirect("/admin/emails");
  const ok = await sendMail(admin.email, `[Test] ${trouve.email.sujet}`, trouve.email.html);
  redirect(`/admin/emails?voir=${id}&outil=${outil}&${ok ? "envoye=1" : "erreur=" + encodeURIComponent("L’email de test n’a pas pu être envoyé : vérifiez la configuration SMTP.")}`);
}

// --- Retours, remboursements, archivage, suppression ----------------------------------------

const versCommande = (id: string, q: string) => redirect(`/admin/commandes/${id}?${q}`);
const montantCents = (v: FormDataEntryValue | null) => Math.round(Number(String(v ?? "").replace(",", ".").replace(/[^\d.]/g, "")) * 100);

export async function adminRetour(orderId: string, form: FormData) {
  await requireAdmin();
  const mode = form.get("mode");
  const err = await enregistrerRetour({
    orderId,
    motif: String(form.get("motif")) as Motif,
    note: String(form.get("note") ?? "").trim(),
    cartes: form.getAll("cartes").map(String),
    desactiver: form.get("desactiver") === "on",
    montantCents: montantCents(form.get("montant")),
    mode: mode === "stripe" || mode === "manuel" ? mode : null,
  });
  versCommande(orderId, err ? `erreur=${encodeURIComponent(err)}` : "info=" + encodeURIComponent("Retour enregistré."));
}

export async function adminRembourser(orderId: string, retourId: number, form: FormData) {
  await requireAdmin();
  const err = await rembourser(retourId, montantCents(form.get("montant")), form.get("mode") === "stripe" ? "stripe" : "manuel");
  versCommande(orderId, err ? `erreur=${encodeURIComponent(err)}` : "info=" + encodeURIComponent("Remboursement effectué : le client a été prévenu par email."));
}

export async function adminRefuserRetour(orderId: string, retourId: number) {
  await requireAdmin();
  refuserRetour(retourId);
  versCommande(orderId, "info=" + encodeURIComponent("Retour refusé."));
}

export async function adminArchiver(orderId: string, archiver: boolean) {
  await requireAdmin();
  archiverCommande(orderId, archiver);
  versCommande(orderId, "info=" + encodeURIComponent(archiver ? "Commande archivée : elle n’apparaît plus dans la liste par défaut." : "Commande désarchivée."));
}

export async function adminSupprimer(orderId: string, form: FormData) {
  await requireAdmin();
  if (String(form.get("confirmation") ?? "").trim().toUpperCase() !== "SUPPRIMER") {
    versCommande(orderId, "erreur=" + encodeURIComponent("Tapez SUPPRIMER pour confirmer la suppression."));
  }
  const err = supprimerCommande(orderId);
  if (err) versCommande(orderId, `erreur=${encodeURIComponent(err)}`);
  redirect(`/admin/commandes?supprimee=${encodeURIComponent(orderId)}`);
}
