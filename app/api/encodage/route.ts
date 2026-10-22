import { timingSafeEqual } from "node:crypto";
import { NextResponse } from "next/server";
import { get } from "@/lib/db";
import { registerEncodedCard } from "@/lib/orders";
import type { Card } from "@/lib/types";

function authorized(req: Request): boolean {
  const key = process.env.ENCODING_API_KEY;
  const given = req.headers.get("authorization")?.replace(/^Bearer\s+/i, "") ?? "";
  if (!key || key.length < 24) return false;
  const a = Buffer.from(key), b = Buffer.from(given);
  return a.length === b.length && timingSafeEqual(a, b);
}

/**
 * Appelé par le poste d'encodage après écriture, configuration SUN, changement de clés et vérification d'une carte.
 * POST /api/encodage  Authorization: Bearer <ENCODING_API_KEY>
 * { "cardId": "Ab3dE5gH7j", "uid": "04DE5F1EACC040", "counter": 1 }
 */
export async function POST(req: Request) {
  if (!authorized(req)) return NextResponse.json({ error: "Non autorisé" }, { status: 401 });
  const body = await req.json().catch(() => null);
  const cardId = typeof body?.cardId === "string" ? body.cardId : "";
  const uid = typeof body?.uid === "string" ? body.uid.toUpperCase() : "";
  const counter = Number.isInteger(body?.counter) ? body.counter : 0;
  const error = registerEncodedCard(cardId, uid, counter);
  if (error) return NextResponse.json({ error }, { status: 409 });
  return NextResponse.json({ ok: true, card: get<Card>("SELECT id, uid, status, encoded_at FROM cards WHERE id = ?", cardId) });
}

/** GET /api/encodage?cardId=… : contrôle avant encodage (la carte existe et attend une puce). */
export async function GET(req: Request) {
  if (!authorized(req)) return NextResponse.json({ error: "Non autorisé" }, { status: 401 });
  const card = get<Card>("SELECT id, status, uid, batch_id FROM cards WHERE id = ?", new URL(req.url).searchParams.get("cardId") ?? "");
  if (!card) return NextResponse.json({ error: "Carte inconnue" }, { status: 404 });
  return NextResponse.json({ card, url: `/c?e=…&m=… (SUN)`, qr: `/q/${card.id}` });
}
