import { currentUser } from "@/lib/auth";
import { basicDesign } from "@/lib/catalog";
import { APP_URL } from "@/lib/config";
import { toCsv } from "@/lib/csv";
import { all } from "@/lib/db";

export async function GET(_req: Request, { params }: { params: Promise<{ id: string }> }) {
  const user = await currentUser();
  if (!user?.is_admin) return new Response("Accès refusé", { status: 403 });
  const { id } = await params;
  // Cartes de commandes (design de la commande) et cartes Basic de stock (sans commande, design fixe).
  const rows = all<{ id: string; activation_code: string; order_id: string | null; design: string | null; variant: string | null; status: string; uid: string | null }>(
    `SELECT c.id, c.activation_code, c.order_id, o.design, c.variant, c.status, c.uid
     FROM cards c LEFT JOIN orders o ON o.id = c.order_id WHERE c.batch_id = ? ORDER BY c.order_id, c.id`, id);
  if (rows.length === 0) return new Response("Lot introuvable", { status: 404 });
  const csv = toCsv([
    ["card_id", "qr_url", "activation_code", "order_id", "nom_imprime", "matiere", "modele", "finition", "bat_recto", "bat_verso", "statut", "uid"],
    ...rows.map((r) => {
      const variant = r.variant === "blanc" ? "blanc" : "noir";
      const d = r.design ? JSON.parse(r.design) : basicDesign(variant);
      const bat = (face: string) =>
        r.order_id ? `${APP_URL}/commande/${r.order_id}/bat?face=${face}` : `${APP_URL}/admin/production/bat-basic?couleur=${variant}&face=${face}`;
      return [r.id, `${APP_URL}/q/${r.id}`, r.activation_code, r.order_id ?? "STOCK", d.name || "", d.material, d.template, d.finish, bat("recto"), bat("verso"), r.status, r.uid];
    }),
  ]);
  return new Response(csv, { headers: { "Content-Type": "text/csv; charset=utf-8", "Content-Disposition": `attachment; filename="${id}.csv"` } });
}
