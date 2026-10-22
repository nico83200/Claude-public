import { get } from "@/lib/db";
import { rateLimit } from "@/lib/ratelimit";
import { recordScan, visitorContext } from "@/lib/scans";
import type { Card } from "@/lib/types";

/** Clics sur les liens d'un profil (envoyés par sendBeacon). */
export async function POST(req: Request) {
  const { ip } = await visitorContext();
  if (!rateLimit(`evt:${ip}`, 60, 60_000)) return new Response(null, { status: 429 });
  const body = await req.json().catch(() => null);
  if (!body || body.action !== "click" || typeof body.cardId !== "string") return new Response(null, { status: 400 });
  const card = get<Card>("SELECT * FROM cards WHERE id = ? AND status = 'active'", body.cardId.slice(0, 20));
  if (!card) return new Response(null, { status: 404 });
  await recordScan(card.id, card.profile_id, "qr", false, "click", typeof body.target === "string" ? body.target : undefined);
  return new Response(null, { status: 204 });
}
