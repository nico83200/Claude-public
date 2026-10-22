import Link from "next/link";
import { all } from "@/lib/db";
import { adminCreateBatch, adminRegisterEncoding } from "../actions";

export const metadata = { title: "Production & encodage" };

export default async function Production({ searchParams }: { searchParams: Promise<{ lot?: string; vide?: string; erreur?: string; encodee?: string }> }) {
  const sp = await searchParams;
  const waiting = all<{ order_id: string; n: number; email: string; material: string }>(
    `SELECT c.order_id, COUNT(*) n, u.email, o.material FROM cards c JOIN orders o ON o.id = c.order_id JOIN users u ON u.id = o.user_id
     WHERE c.batch_id IS NULL AND c.status = 'provisioned' AND o.status = 'proof_approved' GROUP BY c.order_id ORDER BY o.proof_approved_at`);
  const batches = all<{ id: string; created_at: string; note: string | null; total: number; encoded: number }>(
    `SELECT b.*, COUNT(c.id) total, SUM(c.status <> 'provisioned') encoded FROM batches b LEFT JOIN cards c ON c.batch_id = b.id GROUP BY b.id ORDER BY b.created_at DESC LIMIT 50`);

  return (
    <div className="stack">
      <div className="page-head"><h1>Production & encodage</h1></div>
      {sp.lot && <div className="alert alert-ok">Lot {sp.lot} créé. Téléchargez le CSV pour l’imprimeur et le poste d’encodage.</div>}
      {sp.vide && <div className="alert alert-warn">Aucune carte à mettre en production.</div>}
      {sp.erreur && <div className="alert alert-danger">{sp.erreur}</div>}
      {sp.encodee && <div className="alert alert-ok">Carte {sp.encodee} enregistrée comme encodée.</div>}

      <div className="panel">
        <h3>1. Cartes à produire (BAT validé)</h3>
        {waiting.length === 0 ? <p className="muted">Rien en attente.</p> : (
          <>
            <table><tbody>
              {waiting.map((w) => <tr key={w.order_id}><td><Link href={`/admin/commandes/${w.order_id}`}><code>{w.order_id}</code></Link></td><td>{w.email}</td><td>{w.material}</td><td style={{ textAlign: "right" }}><strong>{w.n}</strong> carte{w.n > 1 ? "s" : ""}</td></tr>)}
            </tbody></table>
            <form action={adminCreateBatch} className="row" style={{ marginTop: 14 }}>
              <input type="text" name="note" placeholder="Note (imprimeur, date d’envoi…)" style={{ maxWidth: 320 }} />
              <button className="btn">Créer un lot avec ces {waiting.reduce((a, w) => a + w.n, 0)} cartes</button>
            </form>
          </>
        )}
      </div>

      <div className="panel">
        <h3>2. Lots</h3>
        <p className="muted" style={{ fontSize: ".9rem" }}>Le CSV contient, pour chaque carte : l’identifiant, l’URL à imprimer en QR (données variables), le code d’activation de l’encart et le design. L’imprimeur imprime ; vous encodez ensuite chaque carte avec le poste NTAG 424 (voir <code>docs/encodage-ntag424.md</code>).</p>
        <div className="table-wrap">
          <table>
            <thead><tr><th>Lot</th><th>Créé le</th><th>Note</th><th>Encodage</th><th /></tr></thead>
            <tbody>
              {batches.map((b) => (
                <tr key={b.id}>
                  <td><code>{b.id}</code></td>
                  <td>{new Date(b.created_at + "Z").toLocaleString("fr-FR")}</td>
                  <td>{b.note ?? "—"}</td>
                  <td>
                    <div style={{ height: 6, width: 120, background: "var(--surface-2)", borderRadius: 99 }}><div style={{ height: 6, width: `${(100 * (b.encoded ?? 0)) / Math.max(1, b.total)}%`, background: "var(--ok)", borderRadius: 99 }} /></div>
                    <small className="muted">{b.encoded ?? 0}/{b.total} encodées</small>
                  </td>
                  <td><a className="btn btn-secondary btn-sm" href={`/admin/production/${b.id}/csv`}>CSV</a></td>
                </tr>
              ))}
              {batches.length === 0 && <tr><td colSpan={5} className="muted">Aucun lot.</td></tr>}
            </tbody>
          </table>
        </div>
      </div>

      <div className="panel">
        <h3>3. Poste d’encodage</h3>
        <p className="muted" style={{ fontSize: ".9rem" }}>
          Le script du poste appelle <code>POST /api/encodage</code> avec l’en-tête <code>Authorization: Bearer ENCODING_API_KEY</code> et
          le corps <code>{`{"cardId":"…","uid":"04…","counter":1}`}</code> après avoir écrit, configuré SUN, changé les clés et vérifié la carte.
        </p>
        <details>
          <summary style={{ cursor: "pointer", fontWeight: 600 }}>Saisie manuelle (dépannage)</summary>
          <form action={adminRegisterEncoding} className="row" style={{ marginTop: 10 }}>
            <input type="text" name="cardId" placeholder="Identifiant carte" required style={{ maxWidth: 200 }} />
            <input type="text" name="uid" placeholder="UID (14 caractères hex)" required pattern="[0-9A-Fa-f]{14}" style={{ maxWidth: 240 }} />
            <button className="btn btn-secondary">Enregistrer</button>
          </form>
        </details>
      </div>
    </div>
  );
}
