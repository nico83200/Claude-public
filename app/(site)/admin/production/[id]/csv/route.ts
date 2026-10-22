import { currentUser } from "@/lib/auth";
import { APP_URL } from "@/lib/config";
import { toCsv } from "@/lib/csv";
import { all } from "@/lib/db";

export async function GET(_req: Request, { params }: { params: Promise<{ id: string }> }) {
  const user = await currentUser();
  if (!user?.is_admin) return new Response("Accès refusé", { status: 403 });
  const { id } = await params;
  const rows = all<{ id: string; activation_code: string; order_id: string; design: string; finish: string; status: string; uid: string | null; name: string | null }>(
    `SELECT c.id, c.activation_code, c.order_id, o.design, o.finish, c.status, c.uid, json_extract(o.design, '$.name') name
     FROM cards c JOIN orders o ON o.id = c.order_id WHERE c.batch_id = ? ORDER BY c.order_id, c.id`, id);
  if (rows.length === 0) return new Response("Lot introuvable", { status: 404 });
  const csv = toCsv([
    ["card_id", "qr_url", "activation_code", "order_id", "nom_imprime", "matiere", "modele", "finition", "bat_recto", "bat_verso", "statut", "uid"],
    ...rows.map((r) => {
      const d = JSON.parse(r.design);
      return [r.id, `${APP_URL}/q/${r.id}`, r.activation_code, r.order_id, r.name, d.material, d.template, r.finish,
        `${APP_URL}/commande/${r.order_id}/bat?face=recto`, `${APP_URL}/commande/${r.order_id}/bat?face=verso`, r.status, r.uid];
    }),
  ]);
  return new Response(csv, { headers: { "Content-Type": "text/csv; charset=utf-8", "Content-Disposition": `attachment; filename="${id}.csv"` } });
}
