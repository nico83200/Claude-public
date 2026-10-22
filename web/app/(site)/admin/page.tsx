import Link from "next/link";
import { OrderBadge } from "@/components/Badges";
import { BarChart } from "@/components/BarChart";
import { euros } from "@/lib/catalog";
import { all, get } from "@/lib/db";
import { CARD_STATUS_LABEL, type CardStatus, type Order } from "@/lib/types";

export const metadata = { title: "Back-office" };

export default async function AdminHome() {
  const k = get<Record<string, number>>(`SELECT
    (SELECT COALESCE(SUM(amount_cents), 0) FROM orders WHERE paid_at IS NOT NULL AND paid_at >= datetime('now', '-30 days')) revenue30,
    (SELECT COUNT(*) FROM orders WHERE status = 'paid') toProof,
    (SELECT COUNT(*) FROM cards c JOIN orders o ON o.id = c.order_id WHERE c.batch_id IS NULL AND c.status = 'provisioned' AND o.status = 'proof_approved') toBatch,
    (SELECT COUNT(*) FROM cards WHERE batch_id IS NOT NULL AND status = 'provisioned') toEncode,
    (SELECT COUNT(*) FROM orders WHERE status = 'in_production') inProd,
    (SELECT COUNT(*) FROM users) users,
    (SELECT COUNT(*) FROM scan_events WHERE action = 'view' AND at >= datetime('now', '-30 days')) scans30`)!;
  const cardsByStatus = all<{ status: CardStatus; n: number }>("SELECT status, COUNT(*) n FROM cards GROUP BY status");
  const recent = all<Order & { email: string }>("SELECT o.*, u.email FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC LIMIT 6");
  const byDay = new Map(all<{ d: string; n: number }>("SELECT date(at) d, COUNT(*) n FROM scan_events WHERE action = 'view' AND at >= datetime('now', '-30 days') GROUP BY d").map((r) => [r.d, r.n]));
  const daily = Array.from({ length: 30 }, (_, i) => {
    const dt = new Date(Date.now() - (29 - i) * 86_400_000);
    return { label: `${dt.getUTCDate()}/${dt.getUTCMonth() + 1}`, value: byDay.get(dt.toISOString().slice(0, 10)) ?? 0 };
  });

  return (
    <div className="stack">
      <div className="page-head"><h1>Tableau de bord</h1></div>
      <div className="grid grid-4">
        <div className="panel"><div className="kpi-label">CA encaissé (30 j)</div><div className="kpi">{euros(k.revenue30)}</div></div>
        <Link href="/admin/commandes?statut=paid" className="panel" style={{ color: "inherit" }}><div className="kpi-label">BAT en attente client</div><div className="kpi">{k.toProof}</div></Link>
        <Link href="/admin/production" className="panel" style={{ color: "inherit" }}><div className="kpi-label">Cartes à mettre en lot</div><div className="kpi">{k.toBatch}</div></Link>
        <Link href="/admin/production" className="panel" style={{ color: "inherit" }}><div className="kpi-label">Cartes à encoder</div><div className="kpi">{k.toEncode}</div></Link>
      </div>
      <div className="grid grid-2">
        <div className="panel">
          <h3>Scans sur la plateforme (30 j) — {k.scans30}</h3>
          <BarChart data={daily} />
        </div>
        <div className="panel">
          <h3>Parc de cartes</h3>
          <table><tbody>
            {cardsByStatus.map((c) => <tr key={c.status}><td>{CARD_STATUS_LABEL[c.status]}</td><td style={{ textAlign: "right" }}><strong>{c.n}</strong></td></tr>)}
            <tr><td className="muted">Comptes clients</td><td style={{ textAlign: "right" }}><strong>{k.users}</strong></td></tr>
          </tbody></table>
        </div>
      </div>
      <div className="panel">
        <div className="row between"><h3>Dernières commandes</h3><Link href="/admin/commandes">Toutes</Link></div>
        <table>
          <tbody>
            {recent.map((o) => (
              <tr key={o.id}>
                <td><Link href={`/admin/commandes/${o.id}`}>{o.id.slice(0, 14)}…</Link><br /><small className="muted">{o.email}</small></td>
                <td>{o.quantity} carte{o.quantity > 1 ? "s" : ""}</td>
                <td>{euros(o.amount_cents)}</td>
                <td style={{ textAlign: "right" }}><OrderBadge status={o.status} /></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
