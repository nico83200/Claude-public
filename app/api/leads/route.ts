import { NextResponse } from "next/server";
import { get, run } from "@/lib/db";
import { sendMail } from "@/lib/mail";
import { rateLimit } from "@/lib/ratelimit";
import { recordScan, visitorContext } from "@/lib/scans";
import { buildLeadVCard, buildVCard } from "@/lib/vcard";
import { emailFicheContact, emailNouveauContact } from "@/lib/emails/messages";
import { sendLeadWebhook } from "@/lib/webhook";
import type { Card, Lead, Profile, User } from "@/lib/types";

const s = (v: unknown, max: number) => (typeof v === "string" ? v.trim().slice(0, max) : "");
const fileName = (name: string) => name.normalize("NFD").replace(/[^\w]+/g, "-").replace(/^-|-$/g, "") || "contact";

/**
 * « Échanger mes coordonnées » : la personne qui scanne la carte laisse ses coordonnées au porteur.
 * Le porteur est prévenu par email (fiche contact .vcf jointe) et, s'il l'a configuré, son CRM reçoit le contact.
 * Le visiteur peut recevoir la fiche contact du porteur par email.
 */
export async function POST(req: Request) {
  const { ip } = await visitorContext();
  if (!rateLimit(`lead:${ip}`, 5, 10 * 60_000)) return NextResponse.json({ error: "Trop d’envois, réessayez plus tard." }, { status: 429 });
  const body = await req.json().catch(() => null);
  if (!body) return NextResponse.json({ error: "Requête invalide." }, { status: 400 });
  const name = s(body.name, 80);
  const email = s(body.email, 120);
  const phone = s(body.phone, 30);
  const company = s(body.company, 80);
  const message = s(body.message, 1000);
  if (!name || (!email && !phone)) return NextResponse.json({ error: "Indiquez votre nom et un email ou un téléphone." }, { status: 400 });
  if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return NextResponse.json({ error: "Email invalide." }, { status: 400 });
  if (body.consent !== true) return NextResponse.json({ error: "Consentement requis." }, { status: 400 });

  const card = get<Card>("SELECT * FROM cards WHERE id = ? AND status = 'active'", s(body.cardId, 20));
  const profile = card?.profile_id ? get<Profile>("SELECT * FROM profiles WHERE id = ? AND lead_capture = 1 AND is_public = 1", card.profile_id) : undefined;
  if (!card || !profile) return NextResponse.json({ error: "Carte introuvable." }, { status: 404 });

  const id = Number(run(
    "INSERT INTO leads (profile_id, card_id, name, email, phone, company, message) VALUES (?, ?, ?, ?, ?, ?, ?)",
    profile.id, card.id, name, email || null, phone || null, company || null, message || null,
  ).lastInsertRowid);
  const lead = get<Lead>("SELECT * FROM leads WHERE id = ?", id)!;
  await recordScan(card.id, profile.id, "qr", false, "lead");

  const owner = get<User>("SELECT * FROM users WHERE id = ?", profile.user_id);
  const tasks: Promise<unknown>[] = [];
  if (owner) {
    const e = emailNouveauContact(lead, profile);
    tasks.push(sendMail(owner.email, e.sujet, e.html,
      [{ filename: `${fileName(name)}.vcf`, content: buildLeadVCard(lead), contentType: "text/vcard; charset=utf-8" }],
    ));
    if (owner.webhook_url) tasks.push(sendLeadWebhook(owner.webhook_url, lead, profile));
  }
  if (email && body.sendCopy === true) {
    const e = emailFicheContact(name, profile, card.id);
    tasks.push(sendMail(email, e.sujet, e.html,
      [{ filename: `${fileName(profile.display_name)}.vcf`, content: buildVCard(profile), contentType: "text/vcard; charset=utf-8" }],
    ));
  }
  await Promise.allSettled(tasks);
  return NextResponse.json({ ok: true });
}
