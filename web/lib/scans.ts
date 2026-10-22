import { headers } from "next/headers";
import { run } from "./db";

export type ScanAction = "view" | "vcf" | "click" | "lead";

/** Pays (fourni par le CDN) et famille d'appareil. Aucune adresse IP n'est stockée. */
export async function visitorContext() {
  const h = await headers();
  const ua = h.get("user-agent") ?? "";
  const device = /iPhone|iPad/.test(ua) ? "iOS" : /Android/.test(ua) ? "Android" : /Mobile/.test(ua) ? "Mobile" : "Ordinateur";
  const country = h.get("cf-ipcountry") ?? h.get("x-vercel-ip-country") ?? null;
  const ip = h.get("cf-connecting-ip") ?? h.get("x-forwarded-for")?.split(",")[0]?.trim() ?? "local";
  return { device, country, ip };
}

export async function recordScan(cardId: string, profileId: string | null, channel: "nfc" | "qr", verified: boolean, action: ScanAction = "view", target?: string) {
  const { device, country } = await visitorContext();
  run(
    "INSERT INTO scan_events (card_id, profile_id, channel, verified, action, target, country, device) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
    cardId, profileId, channel, verified, action, target?.slice(0, 200), country, device,
  );
}
