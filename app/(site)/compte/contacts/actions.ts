"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { requireUser } from "@/lib/auth";
import { get, run } from "@/lib/db";
import { sendLeadWebhook, validWebhookUrl } from "@/lib/webhook";
import { LEAD_STATUS_LABEL, type Lead, type LeadStatus } from "@/lib/types";

export async function updateLead(id: number, form: FormData) {
  const user = await requireUser("/compte/contacts");
  const lead = get<Lead>("SELECT l.* FROM leads l JOIN profiles p ON p.id = l.profile_id WHERE l.id = ? AND p.user_id = ?", id, user.id);
  if (!lead) return;
  const status = String(form.get("status") ?? "");
  run("UPDATE leads SET status = ?, note = ? WHERE id = ?",
    status in LEAD_STATUS_LABEL ? (status as LeadStatus) : lead.status, String(form.get("note") ?? "").trim().slice(0, 1000) || null, id);
  revalidatePath("/compte/contacts");
}

export async function deleteLead(id: number) {
  const user = await requireUser("/compte/contacts");
  run("DELETE FROM leads WHERE id = ? AND profile_id IN (SELECT id FROM profiles WHERE user_id = ?)", id, user.id);
  revalidatePath("/compte/contacts");
}

export async function saveWebhook(form: FormData) {
  const user = await requireUser("/compte/contacts");
  const raw = String(form.get("webhook_url") ?? "").trim();
  if (!raw) {
    run("UPDATE users SET webhook_url = NULL WHERE id = ?", user.id);
    redirect("/compte/contacts?crm=supprime");
  }
  const url = validWebhookUrl(raw);
  if (!url) redirect("/compte/contacts?crm=invalide");
  run("UPDATE users SET webhook_url = ? WHERE id = ?", url, user.id);
  redirect("/compte/contacts?crm=enregistre");
}

export async function testWebhook() {
  const user = await requireUser("/compte/contacts");
  if (!user.webhook_url) redirect("/compte/contacts?crm=invalide");
  const ok = await sendLeadWebhook(
    user.webhook_url,
    { id: 0, profile_id: "test", card_id: "test", name: "Contact de test", email: "test@exemple.fr", phone: "+33 6 00 00 00 00", company: "Tapikard", message: "Ceci est un test de connexion.", created_at: new Date().toISOString() },
    { id: "test", label: "Test", display_name: user.name ?? user.email },
    true,
  );
  redirect(`/compte/contacts?crm=${ok ? "test-ok" : "test-ko"}`);
}
