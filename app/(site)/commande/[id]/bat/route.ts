import { cardSvg } from "@/lib/cardSvg";
import { sanitizeDesign } from "@/lib/catalog";
import { APP_URL } from "@/lib/config";
import { getOrder, orderCards } from "@/lib/orders";

/** BAT vectoriel (SVG à l'échelle 1:1, fonds perdus 3 mm, traits de coupe, zone de sécurité). */
export async function GET(req: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const order = getOrder(id);
  if (!order) return new Response("Commande introuvable", { status: 404 });
  const face = new URL(req.url).searchParams.get("face") === "verso" ? "verso" : "recto";
  // Verso : QR code de la carte demandée (?carte=…), sinon de la première carte de la commande.
  const cartes = orderCards(id);
  const demandee = new URL(req.url).searchParams.get("carte");
  const card = cartes.find((c) => c.id === demandee) ?? cartes[0];
  const svg = cardSvg(sanitizeDesign(JSON.parse(order.design)), { side: face, qrUrl: `${APP_URL}/q/${card?.id ?? "xxxxxxxxxx"}`, proof: true });
  return new Response(svg, {
    headers: { "Content-Type": "image/svg+xml; charset=utf-8", "Content-Disposition": `inline; filename="BAT-${card && face === "verso" ? card.id : id}-${face}.svg"`, "Cache-Control": "no-store" },
  });
}
