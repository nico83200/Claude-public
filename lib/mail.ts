import { EMAIL_ENABLED } from "./config";

/** Envoie un email par SMTP (ex. boîte mail Hostinger). Sans SMTP_HOST, l'email est seulement journalisé. */
export type MailAttachment = { filename: string; content: string; contentType: string };

export async function sendMail(to: string, subject: string, html: string, attachments: MailAttachment[] = []): Promise<boolean> {
  if (!EMAIL_ENABLED) {
    console.info(`[email non envoyé — SMTP_HOST absent] à ${to} : ${subject}`);
    return false;
  }
  try {
    const nodemailer = await import("nodemailer");
    const port = Number(process.env.SMTP_PORT ?? 465);
    const transport = nodemailer.createTransport({
      host: process.env.SMTP_HOST,
      port,
      secure: port === 465,
      auth: { user: process.env.SMTP_USER, pass: process.env.SMTP_PASS },
    });
    await transport.sendMail({ from: process.env.EMAIL_FROM ?? process.env.SMTP_USER, to, subject, html, attachments });
    return true;
  } catch (e) {
    console.error("Échec envoi email", e);
    return false;
  }
}
