import Link from "next/link";
import { notFound } from "next/navigation";
import { CardPreview } from "@/components/CardPreview";
import { OrderBadge } from "@/components/Badges";
import { FINISHES, MATERIALS, euros, sanitizeDesign } from "@/lib/catalog";
import { APP_URL, STRIPE_ENABLED } from "@/lib/config";
import { CodeEntourage } from "@/components/CodeEntourage";
import { codeActif } from "@/lib/promo";
import { getOrder, orderCards } from "@/lib/orders";
import type { OrderStatus, Shipping } from "@/lib/types";
import { approveOrderProof, requestProofChange, simulatePayment } from "./actions";

export const metadata = { title: "Ma commande", robots: { index: false } };

const FLOW: { s: OrderStatus[]; label: string }[] = [
  { s: ["paid", "proof_approved", "in_production", "shipped", "delivered"], label: "Paiement" },
  { s: ["proof_approved", "in_production", "shipped", "delivered"], label: "BAT validé" },
  { s: ["in_production", "shipped", "delivered"], label: "Impression & encodage" },
  { s: ["shipped", "delivered"], label: "Expédiée" },
  { s: ["delivered"], label: "Livrée" },
];

export default async function OrderPage({ params, searchParams }: { params: Promise<{ id: string }>; searchParams: Promise<{ paiement?: string }> }) {
  const { id } = await params;
  const { paiement } = await searchParams;
  const order = getOrder(id);
  if (!order) notFound();
  const design = sanitizeDesign(JSON.parse(order.design));
  const shipping = JSON.parse(order.shipping) as Shipping;
  const cards = orderCards(order.id);
  const qrUrl = `${APP_URL}/q/${cards[0]?.id ?? "xxxxxxxxxx"}`;

  return (
    <div className="container" style={{ paddingTop: 32, maxWidth: 1000 }}>
      <div className="page-head">
        <div>
          <p className="muted" style={{ margin: 0 }}>Commande {order.id}</p>
          <h1>Ma commande</h1>
        </div>
        <OrderBadge status={order.status} />
      </div>

      {paiement === "ok" && order.status === "pending_payment" && <div className="alert" style={{ marginBottom: 16 }}>Paiement reçu, confirmation en cours… Rafraîchissez la page dans quelques secondes.</div>}
      {paiement === "annule" && <div className="alert alert-warn" style={{ marginBottom: 16 }}>Paiement annulé. Vous pouvez réessayer depuis le configurateur.</div>}

      {order.status !== "cancelled" && (
        <div className="timeline" style={{ marginBottom: 28 }}>
          {FLOW.map((f) => (
            <div key={f.label} className={`t-step ${f.s.includes(order.status) ? "done" : ""}`}>
              {order.product === "basic" && f.label === "BAT validé" ? "Commande validée" : f.label}
            </div>
          ))}
        </div>
      )}

      {order.status === "pending_payment" && !STRIPE_ENABLED && (
        <div className="panel" style={{ marginBottom: 20 }}>
          <h3>Paiement (mode démo)</h3>
          <p className="muted">Stripe n’est pas configuré : simulez le paiement pour poursuivre le parcours.</p>
          <form action={simulatePayment.bind(null, order.id)}><button className="btn">Simuler le paiement de {euros(order.amount_cents)}</button></form>
        </div>
      )}

      {order.paid_at && (() => {
        const c = codeActif(order.user_id);
        return c ? <div style={{ marginBottom: 20 }}><CodeEntourage code={c.code} fin={c.fin} /></div> : null;
      })()}

      {order.status === "paid" && (
        <div className="panel" style={{ marginBottom: 20, borderColor: "var(--brand)" }}>
          <h3>Validez votre BAT (bon à tirer)</h3>
          <p className="muted">Vérifiez attentivement l’orthographe, les numéros et les couleurs. La production démarre dès votre validation. Le trait rouge pointillé indique la zone de sécurité : rien d’important ne doit la dépasser.</p>
          {order.proof_comment && <div className="alert alert-warn" style={{ marginBottom: 12 }}>Demande de modification envoyée : « {order.proof_comment} ». Notre équipe vous recontacte.</div>}
          <div className="row">
            <form action={approveOrderProof.bind(null, order.id)}><button className="btn">Je valide le BAT</button></form>
            <a className="btn btn-secondary" href={`/commande/${order.id}/bat?face=recto`} target="_blank">BAT recto (SVG)</a>
            <a className="btn btn-secondary" href={`/commande/${order.id}/bat?face=verso`} target="_blank">BAT verso (SVG)</a>
          </div>
          <details style={{ marginTop: 14 }}>
            <summary style={{ cursor: "pointer", fontWeight: 600 }}>Demander une modification</summary>
            <form action={requestProofChange.bind(null, order.id)} style={{ marginTop: 10 }}>
              <textarea name="comment" required placeholder="Décrivez la modification souhaitée…" maxLength={1000} />
              <button className="btn btn-secondary" style={{ marginTop: 8 }}>Envoyer</button>
            </form>
          </details>
        </div>
      )}

      {order.status === "shipped" && (
        <div className="alert alert-ok" style={{ marginBottom: 20 }}>
          Vos cartes sont en route{order.tracking ? <> — suivi : <strong>{order.tracking}</strong></> : null}. À réception, scannez une carte avec votre téléphone pour l’activer.
        </div>
      )}

      <div className="grid grid-2">
        <div>
          <CardPreview design={design} side="recto" qrUrl={qrUrl} />
          <div style={{ height: 16 }} />
          <CardPreview design={design} side="verso" qrUrl={qrUrl} />
          {cards.length > 1 && (
            <p className="muted" style={{ fontSize: ".9rem", marginTop: 12 }}>
              Aperçu de la 1<sup>re</sup> carte. Chacune de vos {cards.length} cartes a son propre QR code et sa propre puce ; elles ouvrent toutes votre profil.
            </p>
          )}
        </div>
        <div className="panel">
          <h3>Récapitulatif</h3>
          <table>
            <tbody>
              {order.product === "basic" && <tr><td className="muted">Produit</td><td>Carte Basic (prête à l’emploi)</td></tr>}
              <tr><td className="muted">Matière</td><td>{MATERIALS[design.material].label}</td></tr>
              <tr><td className="muted">Finition</td><td>{FINISHES[design.finish].label}</td></tr>
              <tr><td className="muted">Quantité</td><td>{order.quantity}</td></tr>
              <tr><td className="muted">Puce</td><td>NFC NTAG 424 DNA</td></tr>
              {order.remise_cents > 0 && <tr><td className="muted">Code {order.code_promo}</td><td style={{ color: "var(--ok)" }}>−{euros(order.remise_cents)}</td></tr>}
              <tr><td className="muted">Total TTC</td><td><strong>{euros(order.amount_cents)}</strong></td></tr>
              {shipping.name && <tr><td className="muted">Livraison</td><td>{shipping.name}<br />{shipping.address}{shipping.address2 && <><br />{shipping.address2}</>}<br />{shipping.zip} {shipping.city}</td></tr>}
            </tbody>
          </table>
          <p style={{ marginTop: 16 }}>
            Votre profil en ligne est déjà créé. <Link href={`/connexion?next=/compte/profils/${order.profile_id}`}>Connectez-vous</Link> pour ajouter une photo, des liens et le personnaliser.
          </p>
        </div>
      </div>
    </div>
  );
}
