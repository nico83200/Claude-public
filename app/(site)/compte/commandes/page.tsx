import Link from "next/link";
import { requireUser } from "@/lib/auth";
import { OrderBadge } from "@/components/Badges";
import { MATERIALS, euros, type MaterialId } from "@/lib/catalog";
import { all } from "@/lib/db";
import type { Order } from "@/lib/types";

export const metadata = { title: "Mes commandes" };

export default async function CommandesPage() {
  const user = await requireUser("/compte/commandes");
  const orders = all<Order>("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC", user.id);
  return (
    <div>
      <div className="page-head"><h1>Mes commandes</h1><Link href="/carte-basic" className="btn">Nouvelle commande</Link></div>
      {orders.length === 0 ? <div className="panel"><p className="muted" style={{ margin: 0 }}>Aucune commande.</p></div> : (
        <div className="table-wrap">
          <table>
            <thead><tr><th>Date</th><th>Commande</th><th>Produit</th><th>Montant</th><th>Statut</th><th /></tr></thead>
            <tbody>
              {orders.map((o) => (
                <tr key={o.id}>
                  <td>{new Date(o.created_at + "Z").toLocaleDateString("fr-FR")}</td>
                  <td><code>{o.id.slice(0, 12)}…</code></td>
                  <td>{o.quantity} × {MATERIALS[o.material as MaterialId]?.label ?? o.material}</td>
                  <td>{euros(o.amount_cents)}</td>
                  <td><OrderBadge status={o.status} /></td>
                  <td><Link href={`/commande/${o.id}`}>{o.status === "paid" ? "Valider le BAT" : "Détails"}</Link></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
