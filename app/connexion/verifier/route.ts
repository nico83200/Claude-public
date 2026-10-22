import { NextResponse } from "next/server";
import { consumeLoginToken, startSession } from "@/lib/auth";

export async function GET(req: Request) {
  const url = new URL(req.url);
  const userId = consumeLoginToken(url.searchParams.get("token") ?? "");
  if (!userId) return NextResponse.redirect(new URL("/connexion?erreur=lien", req.url));
  await startSession(userId);
  const next = url.searchParams.get("next") ?? "/compte";
  return NextResponse.redirect(new URL(next.startsWith("/") && !next.startsWith("//") ? next : "/compte", req.url));
}
