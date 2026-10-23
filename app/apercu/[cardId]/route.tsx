import { ImageResponse } from "next/og";
import { BRAND_VIOLET } from "@/lib/brand";
import { get } from "@/lib/db";
import type { Profile } from "@/lib/types";

/** Image d’aperçu (1200 × 630) de la fiche d’une carte : affichée quand le lien est partagé (SMS, WhatsApp, LinkedIn…). */
export async function GET(_req: Request, { params }: { params: Promise<{ cardId: string }> }) {
  const { cardId } = await params;
  const p = get<Profile>("SELECT p.* FROM profiles p JOIN cards c ON c.profile_id = p.id WHERE c.id = ? AND c.status = 'active' AND p.is_public = 1", cardId);
  const nom = p?.display_name || "Carte Tapikard";
  const sousTitre = p ? [p.title, p.company].filter(Boolean).join(" · ") : "Carte de visite NFC";
  const initiales = nom.split(/\s+/).map((w) => w[0]).slice(0, 2).join("").toUpperCase();
  const accent = p?.accent ?? BRAND_VIOLET;
  return new ImageResponse(
    (
      <div style={{ width: "100%", height: "100%", display: "flex", flexDirection: "column", background: "#f6f6f9", fontFamily: "sans-serif" }}>
        <div style={{ height: 210, display: "flex", alignItems: "center", justifyContent: "flex-end", padding: "0 70px", background: p?.logo ? "#f6f6f9" : accent }}>
          {p?.logo && <img src={p.logo} style={{ maxHeight: 150, maxWidth: 560, objectFit: "contain" }} />}
        </div>
        <div style={{ display: "flex", alignItems: "center", padding: "0 70px", marginTop: -110, gap: 48 }}>
          {p?.avatar
            ? <img src={p.avatar} width={260} height={260} style={{ borderRadius: 999, border: "10px solid #f6f6f9", objectFit: "cover" }} />
            : <div style={{ width: 260, height: 260, borderRadius: 999, border: "10px solid #f6f6f9", background: "#fff", display: "flex", alignItems: "center", justifyContent: "center", fontSize: 96, fontWeight: 800, color: accent }}>{initiales}</div>}
          <div style={{ display: "flex", flexDirection: "column", marginTop: 110, maxWidth: 720 }}>
            <div style={{ fontSize: 68, fontWeight: 800, color: "#14121f", lineHeight: 1.05 }}>{nom}</div>
            {sousTitre && <div style={{ fontSize: 36, color: "#666277", marginTop: 14 }}>{sousTitre}</div>}
          </div>
        </div>
        <div style={{ marginTop: "auto", display: "flex", alignItems: "center", justifyContent: "space-between", padding: "0 70px 46px" }}>
          <div style={{ display: "flex", padding: "16px 34px", borderRadius: 999, background: accent, color: "#fff", fontSize: 30, fontWeight: 700 }}>Ajouter aux contacts</div>
          <div style={{ fontSize: 26, color: "#8c889a" }}>Carte de visite NFC Tapikard</div>
        </div>
      </div>
    ),
    { width: 1200, height: 630, headers: { "Cache-Control": "public, max-age=600" } },
  );
}
