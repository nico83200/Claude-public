import { EMAIL_ENABLED } from "./config";

const FROM = () => process.env.EMAIL_FROM ?? "Tapikard <bonjour@tapikard.com>";

/** Envoie un email via SMTP (ex. boîte mail Hostinger) ou Resend. Sans configuration, l'email est seulement journalisé. */
export async function sendMail(to: string, subject: string, html: string): Promise<boolean> {
  if (!EMAIL_ENABLED) {
    console.info(`[email non envoyé — ni SMTP_HOST ni RESEND_API_KEY] à ${to} : ${subject}`);
    return false;
  }
  try {
    if (process.env.SMTP_HOST) {
      const nodemailer = await import("nodemailer");
      const port = Number(process.env.SMTP_PORT ?? 465);
      const transport = nodemailer.createTransport({
        host: process.env.SMTP_HOST,
        port,
        secure: port === 465,
        auth: { user: process.env.SMTP_USER, pass: process.env.SMTP_PASS },
      });
      await transport.sendMail({ from: FROM(), to, subject, html });
      return true;
    }
    const res = await fetch("https://api.resend.com/emails", {
      method: "POST",
      headers: { Authorization: `Bearer ${process.env.RESEND_API_KEY}`, "Content-Type": "application/json" },
      body: JSON.stringify({ from: FROM(), to, subject, html }),
    });
    if (!res.ok) console.error("Échec envoi email", res.status, await res.text());
    return res.ok;
  } catch (e) {
    console.error("Échec envoi email", e);
    return false;
  }
}
