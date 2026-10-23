import Link from "next/link";
import { OrderBadge } from "@/components/Badges";
import { colissimoConfigure } from "@/lib/colissimo";
import { all } from "@/lib/db";
import type { Order, Shipping } from "@/lib/types";
import { adminEtiquette, adminEtiquettesLot, adminExpedier } from "../../actions";

export const metadata = { title: "Expédition du lot" };

export default async function ExpeditionLot({ params, searchParams }: { params: Promise<{ lot: string }>; searchParams: Promise<{ info?: string; erreur?: string }> }) {
  const { lot } = await params;
  const { info, erreur } = await searchParams;
  const commandes = all<Order & { email: string; cartes: number; encodees: number }>(
    `SELECT o.*, u.email, COUNT(c.id) cartes, SUM(c.status <> 'provisioned') encodees
     FROM orders o JOIN users u ON u.id = o.user_id JOIN cards c ON c.order_id = o.id
     WHERE c.batch_id = ? GROUP BY o.id ORDER BY o.created_at`, lot);
  const colissimo = colissimoConfigure();
  const retour = `/admin/expedition/${lot}`;
  const sansEtiquette = commandes.filter((o) => !o.etiquette_pdf && o.status !== "shipped").length;

  return (
    <div className="stack">
      <div className="page-head">
        <div>
          <p className="muted" style={{ margin: 0 }}><Link href="/admin/production">← Production</Link></p>
          <h1>Expédition du lot <code style={{ fontSize: "1.1rem" }}>{lot}</code></h1>
        </div>
      </div>
      {info && <div className="alert alert-ok">{info}</div>}
      {erreur && <div className="alert alert-danger">{erreur}</div>}

      <div className="panel">
        <h3>En trois temps</h3>
        <ol style={{ margin: "0 0 14px", paddingLeft: 20 }}>
          <li><strong>Étiquettes</strong> : {colissimo ? "créez-les toutes d’un coup (numéro de suivi enregistré automatiquement), puis imprimez-les." : "imprimez l’étiquette d’adresse de chaque commande (ou configurez Colissimo pour des étiquettes avec suivi)."}</li>
          <li><strong>Courriers</strong> : imprimez les courriers d’accompagnement (code d’activation de chaque carte et mode d’emploi) et glissez chacun avec sa carte.</li>
          <li><strong>Expédiez</strong> : « Marquer expédiée » envoie au client l’email avec son numéro de suivi.</li>
        </ol>
        <div className="row">
          {colissimo && sansEtiquette > 0 && <form action={adminEtiquettesLot.bind(null, lot)}><button className="btn">Créer les {sansEtiquette} étiquette{sansEtiquette > 1 ? "s" : ""} Colissimo</button></form>}
          <a className="btn btn-secondary" href={`/impression/courriers?lot=${lot}`} target="_blank">Imprimer tous les courriers du lot</a>
        </div>
      </div>

      <div className="table-wrap">
        <table>
          <thead><tr><th>Commande</th><th>Livraison</th><th>Cartes</th><th>Étiquette</th><th>Statut</th><th /></tr></thead>
          <tbody>
            {commandes.map((o) => {
              const s = JSON.parse(o.shipping) as Shipping;
              return (
                <tr key={o.id}>
                  <td><Link href={`/admin/commandes/${o.id}`}><code>{o.id}</code></Link><br /><small className="muted">{o.email}</small></td>
                  <td><small>{s.name}<br />{s.address}{s.address2 ? `, ${s.address2}` : ""}<br />{s.zip} {s.city}</small></td>
                  <td>{o.encodees}/{o.cartes} encodée{o.cartes > 1 ? "s" : ""}</td>
                  <td>
                    {o.etiquette_pdf
                      ? <a href={`/admin/commandes/${o.id}/etiquette`} target="_blank">Colissimo {o.tracking}</a>
                      : colissimo
                        ? <form action={adminEtiquette.bind(null, o.id, retour)}><button className="btn btn-sm btn-secondary">Créer</button></form>
                        : <a href={`/admin/commandes/${o.id}/etiquette`} target="_blank">Étiquette d’adresse</a>}
                    <br /><a href={`/impression/courriers?commande=${o.id}`} target="_blank"><small>Courrier</small></a>
                  </td>
                  <td><OrderBadge status={o.status} /></td>
                  <td>
                    {o.status !== "shipped" && (
                      <form action={adminExpedier.bind(null, o.id, retour)}>
                        <button className="btn btn-sm" disabled={o.encodees < o.cartes}>Marquer expédiée</button>
                      </form>
                    )}
                  </td>
                </tr>
              );
            })}
            {commandes.length === 0 && <tr><td colSpan={6} className="muted">Ce lot ne contient que des cartes de stock (sans commande) : imprimez leurs courriers ci-dessus.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}
