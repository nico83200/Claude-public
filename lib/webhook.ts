import { APP_URL } from "./config";
import type { Lead, Profile } from "./types";

/** Adresse de webhook acceptée : HTTPS public uniquement (pas d'adresse locale ou privée). */
export function validWebhookUrl(raw: string): string | null {
  let u: URL;
  try {
    u = new URL(raw.trim());
  } catch {
    return null;
  }
  const host = u.hostname.toLowerCase();
  const privateHost =
    host === "localhost" || host.endsWith(".local") || host.endsWith(".internal") ||
    /^(127\.|10\.|192\.168\.|169\.254\.|0\.|172\.(1[6-9]|2\d|3[01])\.)/.test(host) || host.startsWith("[");
  return u.protocol === "https:" && !privateHost && raw.length <= 500 ? u.toString() : null;
}

/** Envoie le nouveau contact au CRM du porteur (Zapier, Make, HubSpot, n8n…). Échec silencieux, 5 s max. */
export async function sendLeadWebhook(url: string, lead: Omit<Lead, "status" | "note">, profile: Pick<Profile, "id" | "label" | "display_name">, test = false) {
  const body = {
    event: test ? "test" : "lead.created",
    lead: { id: lead.id, name: lead.name, email: lead.email, phone: lead.phone, company: lead.company, message: lead.message, created_at: lead.created_at, card_id: lead.card_id },
    profile: { id: profile.id, label: profile.label, name: profile.display_name },
    dashboard_url: `${APP_URL}/compte/contacts`,
  };
  try {
    const res = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json", "User-Agent": "Tapikard-Webhook/1.0" },
      body: JSON.stringify(body),
      signal: AbortSignal.timeout(5000),
      redirect: "error",
    });
    return res.ok;
  } catch (e) {
    console.error("Webhook CRM en échec", url, e);
    return false;
  }
}
