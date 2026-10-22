import { NextResponse } from "next/server";
import { get, run } from "@/lib/db";
import { sendMail } from "@/lib/mail";
import { rateLimit } from "@/lib/ratelimit";
import { recordScan, visitorContext } from "@/lib/scans";
import { APP_URL } from "@/lib/config";
import type { Card, Profile, User } from "@/lib/types";

const s = (v: unknown, max: number) => (typeof v === "string" ? v.trim().slice(0, max) : "");

export async function POST(req: Request) {
  const { ip } = await visitorContext();
  if (!rateLimit(`lead:${ip}`, 5, 10 * 60_000)) return NextResponse.json({ error: "Trop d’envois, réessayez plus tard." }, { status: 429 });
  const body = await req.json().catch(() => null);
  if (!body) return NextResponse.json({ error: "Requête invalide." }, { status: 400 });
  const name = s(body.name, 80);
  const email = s(body.email, 120);
  const phone = s(body.phone, 30);
  if (!name || (!email && !phone)) return NextResponse.json({ error: "Indiquez votre nom et un email ou un téléphone." }, { status: 400 });
  if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return NextResponse.json({ error: "Email invalide." }, { status: 400 });
  if (body.consent !== true) return NextResponse.json({ error: "Consentement requis." }, { status: 400 });

  const card = get<Card>("SELECT * FROM cards WHERE id = ? AND status = 'active'", s(body.cardId, 20));
  const profile = card?.profile_id ? get<Profile>("SELECT * FROM profiles WHERE id = ? AND lead_capture = 1 AND is_public = 1", card.profile_id) : undefined;
  if (!card || !profile) return NextResponse.json({ error: "Carte introuvable." }, { status: 404 });

  run("INSERT INTO leads (profile_id, card_id, name, email, phone, company, message) VALUES (?, ?, ?, ?, ?, ?, ?)",
    profile.id, card.id, name, email || null, phone || null, s(body.company, 80) || null, s(body.message, 1000) || null);
  await recordScan(card.id, profile.id, "qr", false, "lead");
  const owner = get<User>("SELECT * FROM users WHERE id = ?", profile.user_id);
  if (owner) void sendMail(owner.email, `Nouveau contact : ${name}`, `<p>${name} vous a laissé ses coordonnées via votre carte Tapikard.</p><p><a href="${APP_URL}/compte/contacts">Voir mes contacts</a></p>`);
  return NextResponse.json({ ok: true });
}
