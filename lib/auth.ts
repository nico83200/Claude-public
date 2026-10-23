import { createHash, createHmac, timingSafeEqual } from "node:crypto";
import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import { get, run } from "./db";
import { adminEmails, APP_URL, IS_PROD, sessionSecret } from "./config";
import { randomId, randomToken } from "./ids";
import { sendMail } from "./mail";
import { emailConnexion } from "./emails/messages";
import type { User } from "./types";

const COOKIE = "tk_session";
const SESSION_DAYS = 30;

export function sign(value: string): string {
  return createHmac("sha256", sessionSecret()).update(value).digest("base64url").slice(0, 32);
}

export function verifySigned(value: string, sig: string): boolean {
  const a = Buffer.from(sign(value));
  const b = Buffer.from(sig);
  return a.length === b.length && timingSafeEqual(a, b);
}

const hash = (t: string) => createHash("sha256").update(t).digest("hex");

export function normalizeEmail(email: string): string | null {
  const e = email.trim().toLowerCase();
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e) && e.length <= 200 ? e : null;
}

export function findOrCreateUser(email: string, name?: string): User {
  const existing = get<User>("SELECT * FROM users WHERE email = ?", email);
  if (existing) return existing;
  const id = randomId(14);
  run("INSERT INTO users (id, email, name, is_admin) VALUES (?, ?, ?, ?)", id, email, name ?? null, adminEmails().includes(email));
  return get<User>("SELECT * FROM users WHERE id = ?", id)!;
}

/** Crée un lien de connexion à usage unique (20 min). Renvoie le lien si l'email n'a pas pu être envoyé (dev). */
export async function sendLoginLink(email: string, next = "/compte"): Promise<string | null> {
  const user = findOrCreateUser(email);
  const token = randomToken();
  const expires = new Date(Date.now() + 20 * 60_000).toISOString();
  run("INSERT INTO login_tokens (token_hash, user_id, expires_at) VALUES (?, ?, ?)", hash(token), user.id, expires);
  const safeNext = next.startsWith("/") && !next.startsWith("//") ? next : "/compte";
  const link = `${APP_URL}/connexion/verifier?token=${token}&next=${encodeURIComponent(safeNext)}`;
  const e = emailConnexion(link, user.name);
  const sent = await sendMail(email, e.sujet, e.html);
  return sent || IS_PROD ? null : link;
}

export function consumeLoginToken(token: string): string | null {
  const row = get<{ user_id: string; expires_at: string; used: number }>(
    "SELECT user_id, expires_at, used FROM login_tokens WHERE token_hash = ?",
    hash(token),
  );
  if (!row || row.used || new Date(row.expires_at).getTime() < Date.now()) return null;
  run("UPDATE login_tokens SET used = 1 WHERE token_hash = ?", hash(token));
  return row.user_id;
}

/** À appeler depuis une route ou une server action. */
export async function startSession(userId: string) {
  const exp = Date.now() + SESSION_DAYS * 86_400_000;
  const value = `${userId}.${exp}`;
  (await cookies()).set(COOKIE, `${value}.${sign(value)}`, {
    httpOnly: true,
    sameSite: "lax",
    secure: IS_PROD,
    path: "/",
    expires: new Date(exp),
  });
}

export async function endSession() {
  (await cookies()).delete(COOKIE);
}

export async function currentUser(): Promise<User | null> {
  const raw = (await cookies()).get(COOKIE)?.value;
  if (!raw) return null;
  const [id, exp, sig] = raw.split(".");
  if (!id || !exp || !sig || !verifySigned(`${id}.${exp}`, sig) || Number(exp) < Date.now()) return null;
  const user = get<User>("SELECT * FROM users WHERE id = ?", id);
  if (user && !user.is_admin && adminEmails().includes(user.email)) user.is_admin = 1;
  return user ?? null;
}

export async function requireUser(next = "/compte"): Promise<User> {
  const u = await currentUser();
  if (!u) redirect(`/connexion?next=${encodeURIComponent(next)}`);
  return u;
}

export async function requireAdmin(): Promise<User> {
  const u = await requireUser("/admin");
  if (!u.is_admin) redirect("/compte");
  return u;
}
