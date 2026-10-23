"use server";

import { redirect } from "next/navigation";
import { consumeLoginCode, normalizeEmail, sendLoginLink, startSession } from "@/lib/auth";
import { rateLimit } from "@/lib/ratelimit";
import { visitorContext } from "@/lib/scans";

export type LoginState = { sent?: boolean; email?: string; dev?: { lien: string; code: string } | null; error?: string };

export async function requestLogin(_prev: LoginState, form: FormData): Promise<LoginState> {
  const email = normalizeEmail(String(form.get("email") ?? ""));
  if (!email) return { error: "Adresse email invalide." };
  const { ip } = await visitorContext();
  if (!rateLimit(`login:${ip}`, 10, 15 * 60_000) || !rateLimit(`login:${email}`, 5, 15 * 60_000)) {
    return { error: "Trop de tentatives. Réessayez dans quelques minutes." };
  }
  const dev = await sendLoginLink(email, String(form.get("next") ?? "/compte"));
  return { sent: true, email, dev };
}

export type CodeState = { error?: string };

export async function loginWithCode(_prev: CodeState, form: FormData): Promise<CodeState> {
  const email = normalizeEmail(String(form.get("email") ?? ""));
  const code = String(form.get("code") ?? "").replace(/\D/g, "");
  const next = String(form.get("next") ?? "/compte");
  if (!email) return { error: "Adresse email invalide." };
  // 6 essais par quart d'heure et par adresse : un code à 6 chiffres ne se devine pas ainsi.
  if (!rateLimit(`code:${email}`, 6, 15 * 60_000)) return { error: "Trop d’essais. Demandez un nouveau code dans quelques minutes." };
  const userId = consumeLoginCode(email, code);
  if (!userId) return { error: "Code incorrect ou expiré." };
  await startSession(userId);
  redirect(next.startsWith("/") && !next.startsWith("//") ? next : "/compte");
}
