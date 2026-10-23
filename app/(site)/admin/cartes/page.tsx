import Link from "next/link";
import { CardBadge } from "@/components/Badges";
import { all } from "@/lib/db";
import { CARD_STATUS_LABEL, type Card, type CardStatus } from "@/lib/types";

export const metadata = { title: "Cartes" };

export default async function AdminCards({ searchParams }: { searchParams: Promise<{ q?: string; statut?: string }> }) {
  const { q, statut } = await searchParams;
  const s = q?.trim() ? `%${q.trim()}%` : null;
  const st = statut && statut in CARD_STATUS_LABEL ? statut : null;
  const cards = all<Card & { email: string | null; last_scan: string | null }>(
    `SELECT c.*, u.email, (SELECT MAX(at) FROM scan_events e WHERE e.card_id = c.id) last_scan
     FROM cards c LEFT JOIN users u ON u.id = c.user_id
     WHERE (? IS NULL OR c.id LIKE ? OR c.uid LIKE ? OR u.email LIKE ?) AND (? IS NULL OR c.status = ?)
     ORDER BY c.created_at DESC LIMIT 300`, s, s, s, s, st, st);
  return (
    <div>
      <div className="page-head"><h1>Cartes</h1></div>
      <form className="row" style={{ marginBottom: 16 }}>
        <input type="search" name="q" defaultValue={q} placeholder="Identifiant, UID ou email" style={{ maxWidth: 300 }} />
        <select name="statut" defaultValue={st ?? ""} style={{ maxWidth: 220 }}>
          <option value="">Tous les statuts</option>
          {(Object.keys(CARD_STATUS_LABEL) as CardStatus[]).map((k) => <option key={k} value={k}>{CARD_STATUS_LABEL[k]}</option>)}
        </select>
        <button className="btn btn-secondary">Rechercher</button>
      </form>
      <div className="table-wrap">
        <table>
          <thead><tr><th>Carte</th><th>Client</th><th>UID</th><th>Compteur SUN</th><th>Lot</th><th>Dernier scan</th><th>Statut</th></tr></thead>
          <tbody>
            {cards.map((c) => (
              <tr key={c.id}>
                <td><code>{c.id}</code></td>
                <td>{c.email ?? "—"}{c.order_id && <><br /><Link href={`/admin/commandes/${c.order_id}`}><small>commande</small></Link></>}</td>
                <td><small>{c.uid ?? "—"}</small>{c.puce === "desfire" && <><br /><span className="badge badge-warn" title="MIFARE DESFire programmée avec une URL fixe : copiable, sans badge « Carte authentique »">⚠ NFC simple</span></>}</td>
                <td>{c.puce === "desfire" ? "—" : c.last_counter >= 0 ? c.last_counter : "—"}</td>
                <td><small>{c.batch_id ?? "—"}</small></td>
                <td><small>{c.last_scan ? new Date(c.last_scan + "Z").toLocaleString("fr-FR") : "—"}</small></td>
                <td><CardBadge status={c.status} /></td>
              </tr>
            ))}
            {cards.length === 0 && <tr><td colSpan={7} className="muted">Aucune carte.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}
