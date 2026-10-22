import { get } from "@/lib/db";
import { recordScan } from "@/lib/scans";
import { buildVCard } from "@/lib/vcard";
import type { Card, Profile } from "@/lib/types";

export async function GET(_req: Request, { params }: { params: Promise<{ cardId: string }> }) {
  const { cardId } = await params;
  const card = get<Card>("SELECT * FROM cards WHERE id = ? AND status = 'active'", cardId);
  const profile = card?.profile_id ? get<Profile>("SELECT * FROM profiles WHERE id = ? AND is_public = 1", card.profile_id) : undefined;
  if (!card || !profile) return new Response("Introuvable", { status: 404 });
  await recordScan(card.id, profile.id, "qr", false, "vcf");
  const filename = (profile.display_name || "contact").normalize("NFD").replace(/[^\w]+/g, "-").replace(/^-|-$/g, "") || "contact";
  return new Response(buildVCard(profile), {
    headers: {
      "Content-Type": "text/vcard; charset=utf-8",
      "Content-Disposition": `attachment; filename="${filename}.vcf"`,
      "Cache-Control": "no-store",
    },
  });
}
