import Link from "next/link";
import { all } from "@/lib/db";
import { adminCreateBasicStock, adminCreateBatch, adminRegisterEncoding } from "../actions";

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
      <details className="panel" open={!sp.lot}>
        <summary style={{ cursor: "pointer", fontWeight: 700 }}>Comment produire une carte ?</summary>
        <ol style={{ paddingLeft: 20, margin: "12px 0 0" }}>
          <li><strong>Commande payée.</strong> Personnalisée : le client valide son BAT depuis sa page de commande (ou vous le validez pour lui dans Commandes). Basic : rien à valider.</li>
          <li><strong>Créer un lot</strong> (section 1 ci-dessous) : regroupe toutes les cartes prêtes à produire. Pour du stock Basic sans commande, utilisez « Créer un lot de stock ».</li>
          <li><strong>Télécharger le CSV du lot</strong> (section 2) et l’envoyer à l’imprimeur avec les BAT : chaque ligne donne l’URL du QR code à imprimer (unique par carte) et le code d’activation de l’encart.</li>
          <li><strong>Encoder chaque carte imprimée</strong> avec l’application mobile (section 3) : choisissez le lot ou scannez le QR code de la carte, puis posez-la au dos du téléphone. Chaque carte est écrite, vérifiée et enregistrée ici automatiquement ; la barre « encodées » du lot avance.</li>
          <li><strong>Expédier</strong> : Commandes → la commande → numéro de suivi → « Marquer expédiée » (possible quand toutes ses cartes sont encodées).</li>
          <li><strong>Le client active</strong> sa carte en la scannant à réception.</li>
        </ol>
      </details>
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
        <h3>Stock de cartes Basic (non attribuées)</h3>
        <p className="muted" style={{ fontSize: ".9rem" }}>
          Pour les salons, revendeurs ou ventes directes : des cartes Basic prêtes à l’emploi, rattachées à personne.
          La personne qui en reçoit une la scanne, crée son compte et l’active avec le code de l’encart.
          BAT générique : <a href="/admin/production/bat-basic?couleur=noir&face=recto" target="_blank">noir recto</a> ·{" "}
          <a href="/admin/production/bat-basic?couleur=noir&face=verso" target="_blank">noir verso</a> ·{" "}
          <a href="/admin/production/bat-basic?couleur=blanc&face=recto" target="_blank">blanc recto</a> ·{" "}
          <a href="/admin/production/bat-basic?couleur=blanc&face=verso" target="_blank">blanc verso</a>
        </p>
        <form action={adminCreateBasicStock} className="row">
          <select name="variant" style={{ maxWidth: 160 }} aria-label="Couleur"><option value="noir">Noir mat</option><option value="blanc">Blanc</option></select>
          <input type="number" name="quantity" min={1} max={1000} defaultValue={50} style={{ maxWidth: 110 }} aria-label="Quantité" />
          <input type="text" name="note" placeholder="Note (salon, revendeur…)" style={{ maxWidth: 260 }} />
          <button className="btn btn-secondary">Créer un lot de stock</button>
        </form>
      </div>

      <div className="panel">
        <h3>2. Lots</h3>
        <p className="muted" style={{ fontSize: ".9rem" }}>Le CSV contient, pour chaque carte : l’identifiant, l’URL à imprimer en QR (données variables), le code d’activation de l’encart et le design. L’imprimeur imprime ; vous encodez ensuite chaque carte avec l’application mobile (section 3).</p>
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
        <h3>3. Encodage des cartes</h3>
        <p style={{ margin: "0 0 14px" }}>
          Encodez avec votre téléphone Android : ouvrez <Link href="/encodage"><strong>la page d’encodage</strong></Link> sur le téléphone,
          installez l’application <strong>Tapikard Encodeur</strong> proposée sur cette page, puis connectez-vous avec le code reçu par email.
        </p>
        <Link href="/encodage" className="btn">Ouvrir la page d’encodage</Link>
        <div style={{ marginTop: 18 }}>
          <strong>Saisie manuelle</strong>
          <p className="muted" style={{ fontSize: ".88rem", margin: "6px 0 0" }}>
            Pour une carte NTAG 424 DNA déjà encodée mais pas enregistrée (ex. encodée hors ligne). Son UID fait 7 octets :
            14 caractères hexadécimaux commençant par 04 (espaces et « : » acceptés).
          </p>
          <form action={adminRegisterEncoding} className="row" style={{ marginTop: 10 }}>
            <input type="text" name="cardId" placeholder="Identifiant carte" required style={{ maxWidth: 200 }} />
            <input type="text" name="uid" placeholder="UID, ex. 04DE5F1EACC040" required style={{ maxWidth: 240 }} />
            <button className="btn btn-secondary">Enregistrer</button>
          </form>
        </div>
      </div>
    </div>
  );
}
