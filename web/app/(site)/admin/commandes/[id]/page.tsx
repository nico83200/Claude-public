import Link from "next/link";
import { notFound } from "next/navigation";
import { CardBadge, OrderBadge } from "@/components/Badges";
import { CardPreview } from "@/components/CardPreview";
import { FINISHES, MATERIALS, euros, sanitizeDesign } from "@/lib/catalog";
import { APP_URL } from "@/lib/config";
import { get } from "@/lib/db";
import { getOrder, orderCards } from "@/lib/orders";
import type { Shipping, User } from "@/lib/types";
import { adminApproveProof, adminCancel, adminMarkPaid, adminShip } from "../../actions";

export const metadata = { title: "Commande" };

export default async function AdminOrder({ params, searchParams }: { params: Promise<{ id: string }>; searchParams: Promise<{ erreur?: string }> }) {
  const { id } = await params;
  const { erreur } = await searchParams;
  const order = getOrder(id);
  if (!order) notFound();
  const user = get<User>("SELECT * FROM users WHERE id = ?", order.user_id)!;
  const design = sanitizeDesign(JSON.parse(order.design));
  const shipping = JSON.parse(order.shipping) as Shipping;
  const cards = orderCards(id);
  const qrUrl = `${APP_URL}/q/${cards[0]?.id ?? "xxxxxxxxxx"}`;
  const encoded = cards.filter((c) => c.status !== "provisioned").length;

  return (
    <div className="stack">
      <div className="page-head">
        <div><p className="muted" style={{ margin: 0 }}><Link href="/admin/commandes">← Commandes</Link></p><h1><code style={{ fontSize: "1.1rem" }}>{order.id}</code></h1></div>
        <OrderBadge status={order.status} />
      </div>
      {erreur && <div className="alert alert-danger">{erreur}</div>}
      {order.proof_comment && <div className="alert alert-warn"><strong>Modification demandée par le client :</strong> {order.proof_comment}</div>}

      <div className="panel">
        <h3>Actions</h3>
        <div className="row">
          {order.status === "pending_payment" && <form action={adminMarkPaid.bind(null, id)}><button className="btn">Marquer comme payée (virement reçu)</button></form>}
          {order.status === "paid" && <form action={adminApproveProof.bind(null, id)}><button className="btn">Valider le BAT pour le client</button></form>}
          {order.status === "proof_approved" && <Link className="btn" href="/admin/production">Mettre en production (créer un lot)</Link>}
          {order.status === "in_production" && (
            <form action={adminShip.bind(null, id)} className="row">
              <input type="text" name="tracking" placeholder="N° de suivi Colissimo" style={{ maxWidth: 240 }} />
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

      <div className="grid grid-2">
        <div>
          <CardPreview design={design} side="recto" qrUrl={qrUrl} />
          <div style={{ height: 12 }} />
          <CardPreview design={design} side="verso" qrUrl={qrUrl} />
        </div>
        <div className="panel">
          <table><tbody>
            <tr><td className="muted">Client</td><td>{user.name ?? "—"}<br /><a href={`mailto:${user.email}`}>{user.email}</a></td></tr>
            <tr><td className="muted">Produit</td><td>{order.quantity} × {MATERIALS[design.material].label}, {FINISHES[design.finish].label}, modèle {design.template}</td></tr>
            <tr><td className="muted">Montant</td><td>{euros(order.amount_cents)}{order.stripe_session_id && <><br /><small className="muted">Stripe {order.stripe_session_id.slice(0, 18)}…</small></>}</td></tr>
            <tr><td className="muted">Livraison</td><td>{shipping.name}<br />{shipping.address}<br />{shipping.zip} {shipping.city}</td></tr>
            <tr><td className="muted">Dates</td><td><small>Créée {order.created_at}<br />Payée {order.paid_at ?? "—"}<br />BAT {order.proof_approved_at ?? "—"}<br />Expédiée {order.shipped_at ?? "—"}{order.tracking && ` (${order.tracking})`}</small></td></tr>
          </tbody></table>
        </div>
      </div>

      <div className="table-wrap">
        <table>
          <thead><tr><th>Carte</th><th>URL du QR</th><th>Code d’activation</th><th>Lot</th><th>UID puce</th><th>Statut</th></tr></thead>
          <tbody>
            {cards.map((c) => (
              <tr key={c.id}><td><code>{c.id}</code></td><td><small>{APP_URL}/q/{c.id}</small></td><td><code>{c.activation_code}</code></td><td>{c.batch_id ?? "—"}</td><td>{c.uid ?? "—"}</td><td><CardBadge status={c.status} /></td></tr>
            ))}
            {cards.length === 0 && <tr><td colSpan={6} className="muted">Les cartes sont créées au paiement.</td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}
