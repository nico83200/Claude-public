import Link from "next/link";
import { notFound } from "next/navigation";
import { CardBadge, OrderBadge } from "@/components/Badges";
import { CardPreview } from "@/components/CardPreview";
import { FINISHES, MATERIALS, euros, sanitizeDesign } from "@/lib/catalog";
import { APP_URL } from "@/lib/config";
import { get } from "@/lib/db";
import { getOrder, orderCards } from "@/lib/orders";
import type { Shipping, User } from "@/lib/types";
import { colissimoConfigure } from "@/lib/colissimo";
import { MOTIFS, STATUT_RETOUR, resteARembourser, retoursDe, stripePossible } from "@/lib/retours";
import { adminApproveProof, adminArchiver, adminEtiquette, adminCancel, adminMarkPaid, adminRefuserRetour, adminRembourser, adminRetour, adminShip, adminSupprimer } from "../../actions";

export const metadata = { title: "Commande" };

export default async function AdminOrder({ params, searchParams }: { params: Promise<{ id: string }>; searchParams: Promise<{ erreur?: string; info?: string }> }) {
  const { id } = await params;
  const { erreur, info } = await searchParams;
  const order = getOrder(id);
  if (!order) notFound();
  const user = get<User>("SELECT * FROM users WHERE id = ?", order.user_id)!;
  const design = sanitizeDesign(JSON.parse(order.design));
  const shipping = JSON.parse(order.shipping) as Shipping;
  const cards = orderCards(id);
  const qrUrl = `${APP_URL}/q/${cards[0]?.id ?? "xxxxxxxxxx"}`;
  const encoded = cards.filter((c) => c.status !== "provisioned").length;
  const retours = retoursDe(id);
  const reste = resteARembourser(order);
  const payee = Boolean(order.paid_at);
  const eur = (c: number) => (c / 100).toFixed(2).replace(".", ",");
  const modes = (
    <select name="mode" defaultValue={stripePossible(order) ? "stripe" : "manuel"} style={{ maxWidth: 260 }} aria-label="Mode de remboursement">
      {stripePossible(order) && <option value="stripe">Rembourser via Stripe (carte bancaire)</option>}
      <option value="manuel">Remboursement manuel (virement déjà fait)</option>
    </select>
  );

  return (
    <div className="stack">
      <div className="page-head">
        <div><p className="muted" style={{ margin: 0 }}><Link href="/admin/commandes">← Commandes</Link></p><h1><code style={{ fontSize: "1.1rem" }}>{order.id}</code></h1></div>
        <div className="row">
          {order.archived_at && <span className="badge">Archivée</span>}
          <OrderBadge status={order.status} />
        </div>
      </div>
      {erreur && <div className="alert alert-danger">{erreur}</div>}
      {info && <div className="alert alert-ok">{info}</div>}
      {order.proof_comment && <div className="alert alert-warn"><strong>Modification demandée par le client :</strong> {order.proof_comment}</div>}

      <div className="panel">
        <h3>Actions</h3>
        <div className="row">
          {order.status === "pending_payment" && <form action={adminMarkPaid.bind(null, id)}><button className="btn">Marquer comme payée (virement reçu)</button></form>}
          {order.status === "paid" && <form action={adminApproveProof.bind(null, id)}><button className="btn">Valider le BAT pour le client</button></form>}
          {order.status === "proof_approved" && <Link className="btn" href="/admin/production">Mettre en production (créer un lot)</Link>}
          {order.status === "in_production" && (
            <form action={adminShip.bind(null, id)} className="row">
              <input type="text" name="tracking" placeholder="N° de suivi Colissimo" defaultValue={order.tracking ?? ""} style={{ maxWidth: 240 }} />
              <button className="btn" disabled={encoded < cards.length}>Marquer expédiée</button>
              <small className="muted">{encoded}/{cards.length} cartes encodées</small>
            </form>
          )}
          <a className="btn btn-secondary" href={`/commande/${id}/bat?face=recto`} target="_blank">BAT recto</a>
          <a className="btn btn-secondary" href={`/commande/${id}/bat?face=verso`} target="_blank">BAT verso</a>
          {["pending_payment", "paid", "proof_approved"].includes(order.status) && (
            <form action={adminCancel.bind(null, id)}><button className="btn btn-ghost" style={{ color: "var(--danger)" }}>Annuler</button></form>
          )}
        </div>
      </div>

      {order.paid_at && (
        <div className="panel">
          <h3>Expédition</h3>
          <div className="row">
            {colissimoConfigure() && !order.etiquette_pdf && (
              <form action={adminEtiquette.bind(null, id, `/admin/commandes/${id}`)}><button className="btn">Créer l’étiquette Colissimo</button></form>
            )}
            <a className={`btn ${order.etiquette_pdf ? "" : "btn-secondary"}`} href={`/admin/commandes/${id}/etiquette`} target="_blank">
              {order.etiquette_pdf ? `Imprimer l’étiquette Colissimo (${order.tracking})` : "Étiquette d’adresse à imprimer"}
            </a>
            <a className="btn btn-secondary" href={`/impression/courriers?commande=${id}`} target="_blank">Courrier d’accompagnement</a>
          </div>
          {!colissimoConfigure() && (
            <p className="muted" style={{ fontSize: ".86rem", marginBottom: 0 }}>
              Pour des étiquettes Colissimo pré-remplies avec suivi automatique, ajoutez votre contrat Colissimo dans les variables d’environnement (voir LISEZ-MOI).
            </p>
          )}
        </div>
      )}

      <div className="grid grid-2">
        <div>
          <CardPreview design={design} side="recto" qrUrl={qrUrl} />
          <div style={{ height: 12 }} />
          <CardPreview design={design} side="verso" qrUrl={qrUrl} />
        </div>
        <div className="panel">
          <table><tbody>
            <tr><td className="muted">Client</td><td>{user.name ?? "—"}<br /><a href={`mailto:${user.email}`}>{user.email}</a></td></tr>
            <tr><td className="muted">Produit</td><td>{order.product === "basic" ? <span className="badge badge-brand">Basic</span> : <span className="badge">Personnalisée</span>} {order.quantity} × {MATERIALS[design.material].label}, {FINISHES[design.finish].label}, modèle {design.template}</td></tr>
            <tr><td className="muted">Montant</td><td>{euros(order.amount_cents)}{order.refunded_cents > 0 && <> · <span style={{ color: "var(--danger)" }}>remboursé {euros(order.refunded_cents)}</span></>}{order.stripe_session_id && <><br /><small className="muted">Stripe {order.stripe_session_id.slice(0, 18)}…</small></>}</td></tr>
            <tr><td className="muted">Livraison</td><td>{shipping.name}<br />{shipping.address}{shipping.address2 && <><br />{shipping.address2}</>}<br />{shipping.zip} {shipping.city}{shipping.phone && <><br /><small className="muted">Tél. {shipping.phone}</small></>}</td></tr>
            {order.code_promo && <tr><td className="muted">Code promo</td><td><code>{order.code_promo}</code> · −{euros(order.remise_cents)}</td></tr>}
            <tr><td className="muted">Dates</td><td><small>Créée {order.created_at}<br />Payée {order.paid_at ?? "—"}<br />BAT {order.proof_approved_at ?? "—"}<br />Expédiée {order.shipped_at ?? "—"}{order.tracking && ` (${order.tracking})`}</small></td></tr>
          </tbody></table>
        </div>
      </div>

      {cards.length > 1 && (
        <div className="panel">
          <h3>Les {cards.length} cartes de la commande</h3>
          <p className="muted" style={{ marginTop: 0, fontSize: ".9rem" }}>
            Même recto pour toutes ; au verso, chaque carte a son propre QR code (et sa propre puce), relié à la même commande.
            L’imprimeur trouve l’URL de chaque QR code dans le CSV du lot.
          </p>
          <div className="cartes-commande">
            {cards.map((c, i) => (
              <figure key={c.id}>
                <CardPreview design={design} side="verso" qrUrl={`${APP_URL}/q/${c.id}`} />
                <figcaption>
                  <strong>Carte {i + 1}</strong> · <code>{c.id}</code><br />
                  <small className="muted">Code d’activation <code>{c.activation_code}</code></small><br />
                  <a href={`/commande/${id}/bat?face=verso&carte=${c.id}`} target="_blank"><small>BAT verso de cette carte</small></a>
                </figcaption>
              </figure>
            ))}
          </div>
        </div>
      )}

      <div className="table-wrap">
        <table>
          <thead><tr><th>Carte</th><th>URL du QR</th><th>Code d’activation</th><th>Lot</th><th>UID puce</th><th>Statut</th></tr></thead>
          <tbody>
            {cards.map((c) => (
              <tr key={c.id}><td><code>{c.id}</code></td><td><small>{APP_URL}/q/{c.id}</small></td><td><code>{c.activation_code}</code></td><td>{c.batch_id ?? "—"}</td><td>{c.uid ?? "—"}{c.puce && <> <span className="badge badge-warn" title="Puce de dépannage (DESFire ou NTAG21x) programmée avec une URL fixe : copiable, sans badge « Carte authentique »">⚠ NFC simple</span></>}</td><td><CardBadge status={c.status} /></td></tr>
            ))}
            {cards.length === 0 && <tr><td colSpan={6} className="muted">Les cartes sont créées au paiement.</td></tr>}
          </tbody>
        </table>
      </div>

      <div className="panel" id="retours">
        <h3>Retours et remboursements</h3>
        {retours.length > 0 && (
          <div className="table-wrap" style={{ marginBottom: 16 }}>
            <table>
              <thead><tr><th>Date</th><th>Motif</th><th>Cartes</th><th>Statut</th><th /></tr></thead>
              <tbody>
                {retours.map((r) => {
                  const cs = JSON.parse(r.cartes) as string[];
                  return (
                    <tr key={r.id}>
                      <td><small>{new Date(r.created_at + "Z").toLocaleDateString("fr-FR")}</small></td>
                      <td>{MOTIFS[r.motif] ?? r.motif}{r.note && <><br /><small className="muted">{r.note}</small></>}</td>
                      <td><small>{cs.length ? cs.join(", ") : "—"}</small></td>
                      <td>
                        <span className={`badge ${r.statut === "rembourse" ? "badge-ok" : r.statut === "refuse" ? "badge-danger" : "badge-warn"}`}>{STATUT_RETOUR[r.statut]}</span>
                        {r.statut === "rembourse" && <><br /><small>{euros(r.montant_cents)} · {r.mode === "stripe" ? "Stripe" : "manuel"}</small></>}
                      </td>
                      <td>
                        {r.statut === "ouvert" && reste > 0 && (
                          <form action={adminRembourser.bind(null, id, r.id)} className="row" style={{ gap: 6 }}>
                            <input type="text" inputMode="decimal" name="montant" defaultValue={eur(reste)} style={{ maxWidth: 90 }} aria-label="Montant en euros" />
                            {modes}
                            <button className="btn btn-sm">Rembourser</button>
                          </form>
                        )}
                        {r.statut === "ouvert" && <form action={adminRefuserRetour.bind(null, id, r.id)}><button className="btn btn-ghost btn-sm">Refuser le retour</button></form>}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
        <details>
          <summary style={{ cursor: "pointer", fontWeight: 600 }}>Enregistrer un retour produit / un remboursement</summary>
          <form action={adminRetour.bind(null, id)} className="stack" style={{ marginTop: 12, gap: 10 }}>
            <label>Motif
              <select name="motif" defaultValue="defaut">{Object.entries(MOTIFS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select>
            </label>
            {cards.length > 0 && (
              <fieldset style={{ border: 0, padding: 0, margin: 0 }}>
                <legend style={{ fontWeight: 600, fontSize: ".9rem" }}>Cartes retournées</legend>
                <div className="row">{cards.map((c) => <label key={c.id} className="check" style={{ margin: 0 }}><input type="checkbox" name="cartes" value={c.id} /> <code>{c.id}</code></label>)}</div>
                <label className="check" style={{ marginTop: 6 }}><input type="checkbox" name="desactiver" defaultChecked /> Désactiver les cartes retournées (le scan affichera « carte désactivée »)</label>
              </fieldset>
            )}
            <label>Note interne<input type="text" name="note" maxLength={1000} placeholder="Ex. puce illisible, colis revenu abîmé…" /></label>
            <div className="row">
              <label style={{ margin: 0 }}>Rembourser maintenant (€)
                <input type="text" inputMode="decimal" name="montant" placeholder={`0 à ${eur(reste)}`} style={{ maxWidth: 140 }} disabled={!payee || reste === 0} />
              </label>
              {payee && reste > 0 && modes}
            </div>
            <small className="muted">
              {payee ? `Reste remboursable : ${euros(reste)}. Laissez le montant vide pour enregistrer le retour et rembourser plus tard (ou refuser).` : "Commande non payée : rien à rembourser."}
              {" "}Le client reçoit un email à chaque remboursement.
            </small>
            <div><button className="btn">Enregistrer le retour</button></div>
          </form>
        </details>
      </div>

      <div className="panel" style={{ borderColor: "var(--danger-soft)" }}>
        <h3>Archiver ou supprimer</h3>
        <div className="row" style={{ alignItems: "flex-start" }}>
          <form action={adminArchiver.bind(null, id, !order.archived_at)}>
            <button className="btn btn-secondary">{order.archived_at ? "Désarchiver" : "Archiver la commande"}</button>
          </form>
          <small className="muted" style={{ flex: 1, minWidth: 220 }}>
            L’archivage masque la commande de la liste (filtre « Archivées ») sans rien effacer : recommandé pour les commandes payées,
            à conserver 10 ans pour la comptabilité.
          </small>
        </div>
        <details style={{ marginTop: 14 }}>
          <summary style={{ cursor: "pointer", fontWeight: 600, color: "var(--danger)" }}>Supprimer définitivement</summary>
          <p className="muted" style={{ fontSize: ".88rem" }}>
            Efface la commande, ses {cards.length} carte{cards.length > 1 ? "s" : ""}, leurs statistiques et ses retours. Irréversible.
            {payee && " Cette commande est payée : préférez l’archivage, sauf s’il s’agit d’un test."}
          </p>
          <form action={adminSupprimer.bind(null, id)} className="row">
            <input type="text" name="confirmation" placeholder="Tapez SUPPRIMER" autoComplete="off" style={{ maxWidth: 200 }} />
            <button className="btn" style={{ background: "var(--danger)" }}>Supprimer la commande</button>
          </form>
        </details>
      </div>
    </div>
  );
}
