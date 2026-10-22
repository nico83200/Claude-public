"use server";

import { normalizeEmail, sendLoginLink } from "@/lib/auth";
import { rateLimit } from "@/lib/ratelimit";
import { visitorContext } from "@/lib/scans";

export type LoginState = { sent?: boolean; devLink?: string | null; error?: string };

export async function requestLogin(_prev: LoginState, form: FormData): Promise<LoginState> {
  const email = normalizeEmail(String(form.get("email") ?? ""));
  if (!email) return { error: "Adresse email invalide." };
  const { ip } = await visitorContext();
  if (!rateLimit(`login:${ip}`, 10, 15 * 60_000) || !rateLimit(`login:${email}`, 5, 15 * 60_000)) {
    return { error: "Trop de tentatives. Réessayez dans quelques minutes." };
  }
  const devLink = await sendLoginLink(email, String(form.get("next") ?? "/compte"));
  return { sent: true, devLink };
}
