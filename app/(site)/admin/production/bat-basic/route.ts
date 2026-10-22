import { currentUser } from "@/lib/auth";
import { cardSvg } from "@/lib/cardSvg";
import { basicDesign } from "@/lib/catalog";
import { APP_URL } from "@/lib/config";

/** BAT générique de la carte Basic (le QR code réel de chaque carte est en données variables dans le CSV du lot). */
export async function GET(req: Request) {
  const user = await currentUser();
  if (!user?.is_admin) return new Response("Accès refusé", { status: 403 });
  const sp = new URL(req.url).searchParams;
  const variant = sp.get("couleur") === "blanc" ? "blanc" : "noir";
  const face = sp.get("face") === "verso" ? "verso" : "recto";
  const svg = cardSvg(basicDesign(variant), { side: face, qrUrl: `${APP_URL}/q/xxxxxxxxxx`, proof: true });
  return new Response(svg, { headers: { "Content-Type": "image/svg+xml; charset=utf-8", "Cache-Control": "no-store" } });
}
