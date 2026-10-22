import Link from "next/link";
import { OrderBadge } from "@/components/Badges";
import { MATERIALS, euros, type MaterialId } from "@/lib/catalog";
import { all } from "@/lib/db";
import { ORDER_STATUS_LABEL, type Order, type OrderStatus } from "@/lib/types";

export const metadata = { title: "Commandes" };

export default async function AdminOrders({ searchParams }: { searchParams: Promise<{ statut?: string; q?: string }> }) {
  const { statut, q } = await searchParams;
  const filter = statut && statut in ORDER_STATUS_LABEL ? statut : null;
  const search = q?.trim() ? `%${q.trim()}%` : null;
  const orders = all<Order & { email: string }>(
    `SELECT o.*, u.email FROM orders o JOIN users u ON u.id = o.user_id
     WHERE (? IS NULL OR o.status = ?) AND (? IS NULL OR o.id LIKE ? OR u.email LIKE ?)
     ORDER BY o.created_at DESC LIMIT 200`, filter, filter, search, search, search);
  return (
    <div>
      <div className="page-head"><h1>Commandes</h1></div>
      <form className="row" style={{ marginBottom: 16 }}>
        <input type="search" name="q" defaultValue={q} placeholder="N° de commande ou email" style={{ maxWidth: 300 }} />
        <select name="statut" defaultValue={filter ?? ""} style={{ maxWidth: 240 }}>
          <option value="">Tous les statuts</option>
          {(Object.keys(ORDER_STATUS_LABEL) as OrderStatus[]).map((s) => <option key={s} value={s}>{ORDER_STATUS_LABEL[s]}</option>)}
        </select>
        <button className="btn btn-secondary">Filtrer</button>
      </form>
      <div className="table-wrap">
        <table>
          <thead><tr><th>Date</th><th>Commande</th><th>Client</th><th>Produit</th><th>Montant</th><th>Statut</th></tr></thead>
          <tbody>
            {orders.map((o) => (
              <tr key={o.id}>
                <td>{new Date(o.created_at + "Z").toLocaleDateString("fr-FR")}</td>
                <td><Link href={`/admin/commandes/${o.id}`}><code>{o.id}</code></Link></td>
                <td>{o.email}</td>
                <td>{o.quantity} × {MATERIALS[o.material as MaterialId]?.label ?? o.material}</td>
                <td>{euros(o.amount_cents)}</td>
                <td><OrderBadge status={o.status} /></td>
              </tr>
            ))}
            {orders.length === 0 && <tr><td colSpan={6} className="muted">Aucune commande.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}
