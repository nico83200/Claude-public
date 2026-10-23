export const APP_URL = (process.env.APP_URL ?? "http://localhost:3000").replace(/\/$/, "");
export const IS_PROD = process.env.NODE_ENV === "production";
export const STRIPE_ENABLED = Boolean(process.env.STRIPE_SECRET_KEY);
export const EMAIL_ENABLED = Boolean(process.env.SMTP_HOST);
/** Carte personnalisée (configurateur /creer) : masquée tant que OFFRE_PERSONNALISEE n'est pas à 1. */
export const OFFRE_PERSONNALISEE = process.env.OFFRE_PERSONNALISEE === "1";

export function sessionSecret(): string {
  const s = process.env.SESSION_SECRET;
  if (s && s.length >= 32) return s;
  if (IS_PROD) throw new Error("SESSION_SECRET (32+ caractères) est obligatoire en production");
  return "dev-only-secret-change-me-dev-only-secret";
}

export function adminEmails(): string[] {
  return (process.env.ADMIN_EMAILS ?? "admin@tapikard.com").split(",").map((e) => e.trim().toLowerCase()).filter(Boolean);
}
