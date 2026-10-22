import { EMAIL_ENABLED } from "./config";

/** Envoie un email via Resend. Sans RESEND_API_KEY, l'email est seulement journalisé. */
export async function sendMail(to: string, subject: string, html: string): Promise<boolean> {
  if (!EMAIL_ENABLED) {
    console.info(`[email non envoyé — RESEND_API_KEY absent] à ${to} : ${subject}`);
    return false;
  }
  const res = await fetch("https://api.resend.com/emails", {
    method: "POST",
    headers: { Authorization: `Bearer ${process.env.RESEND_API_KEY}`, "Content-Type": "application/json" },
    body: JSON.stringify({ from: process.env.EMAIL_FROM ?? "Tapikard <bonjour@tapikard.com>", to, subject, html }),
  });
  if (!res.ok) console.error("Échec envoi email", res.status, await res.text());
  return res.ok;
}
