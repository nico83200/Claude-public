"use client";

import { useActionState, useState } from "react";
import { CardPreview } from "@/components/CardPreview";
import { BASIC_VARIANTS, QUANTITIES, REMISE_LANCEMENT, basicDesign, basicPriceCents, enRupture, euros, libelleRupture, type BasicVariant, type Ruptures } from "@/lib/catalog";
import { createBasicOrder, type BasicOrderState } from "./actions";

export function BasicOrderForm({ stripe, initialVariant = "noir", initialQuantity = 1, ruptures = {} }: { stripe: boolean; initialVariant?: BasicVariant; initialQuantity?: number; ruptures?: Ruptures }) {
  const disponibles = (Object.keys(BASIC_VARIANTS) as BasicVariant[]).filter((v) => !enRupture(ruptures, "basic", v));
  const [variant, setVariant] = useState<BasicVariant>(disponibles.includes(initialVariant) ? initialVariant : disponibles[0] ?? initialVariant);
  const indisponible = enRupture(ruptures, "basic", variant);
  const [quantity, setQuantity] = useState(initialQuantity);
  const [side, setSide] = useState<"recto" | "verso">("recto");
  const [state, action, pending] = useActionState<BasicOrderState, FormData>(createBasicOrder, {});
  const price = basicPriceCents(quantity);
  const design = basicDesign(variant);

  return (
    <div className="configurator" style={{ marginTop: 20 }}>
      <form action={action} className="panel">
        <input type="hidden" name="variant" value={variant} />
        <input type="hidden" name="quantity" value={quantity} />
        <h3>Couleur</h3>
        <div className="choices" style={{ marginBottom: 18 }}>
          {(Object.keys(BASIC_VARIANTS) as BasicVariant[]).map((v) => (
            <label className="choice" key={v}>
              <input type="radio" name="v" checked={variant === v} disabled={enRupture(ruptures, "basic", v)} onChange={() => setVariant(v)} />
              <strong><span className="swatch" style={{ background: BASIC_VARIANTS[v].background }} />{BASIC_VARIANTS[v].label}</strong>
              {enRupture(ruptures, "basic", v) && <span style={{ display: "block", marginTop: 6, color: "var(--warn)", fontWeight: 600, fontSize: ".78rem" }}>{libelleRupture(ruptures, "basic", v)}</span>}
            </label>
          ))}
        </div>
        <h3>Quantité</h3>
        <div className="choices" style={{ marginBottom: 18 }}>
          {QUANTITIES.map((q) => (
            <label className="choice" key={q.qty}>
              <input type="radio" name="q" checked={quantity === q.qty} onChange={() => setQuantity(q.qty)} />
              <strong>{q.qty} carte{q.qty > 1 ? "s" : ""}</strong>
              <span><s>{euros(basicPriceCents(q.qty).reference)}</s> {euros(basicPriceCents(q.qty).total)}{q.qty > 1 ? ` · ${euros(basicPriceCents(q.qty).unit)} / carte` : ""}</span>
            </label>
          ))}
        </div>
        <div className="field"><label htmlFor="email">Votre email (accès à votre espace) *</label><input id="email" name="email" type="email" required autoComplete="email" /></div>
        <div className="field"><label htmlFor="name">Nom et prénom *</label><input id="name" name="name" type="text" required autoComplete="name" /></div>
        {!stripe && (
          <>
            <div className="field"><label htmlFor="address">Adresse *</label><input id="address" name="address" type="text" autoComplete="street-address" /></div>
            <div className="grid" style={{ gridTemplateColumns: "1fr 2fr", gap: 12 }}>
              <div className="field"><label htmlFor="zip">Code postal *</label><input id="zip" name="zip" type="text" inputMode="numeric" autoComplete="postal-code" /></div>
              <div className="field"><label htmlFor="city">Ville *</label><input id="city" name="city" type="text" autoComplete="address-level2" /></div>
            </div>
          </>
        )}
        <label className="check" style={{ margin: "8px 0 18px" }}>
          <input type="checkbox" name="cgv" />
          <span>J’accepte les conditions de vente et la <a href="/confidentialite" target="_blank">politique de confidentialité</a>.</span>
        </label>
        {state.error && <div className="alert alert-danger" style={{ marginBottom: 14 }}>{state.error}</div>}
        {indisponible && <div className="alert alert-warn" style={{ marginBottom: 14 }}>Ce coloris est momentanément en rupture de stock.</div>}
        <button className="btn btn-lg btn-block" disabled={pending || indisponible}>
          {pending ? "Un instant…" : stripe ? `Payer ${euros(price.total)} en toute sécurité` : `Commander — ${euros(price.total)}`}
        </button>
        <p style={{ textAlign: "center", margin: "10px 0 0", fontWeight: 600, color: "var(--ok)" }}>Vous économisez {euros(price.economie)}</p>
        <ul className="garanties" style={{ justifyContent: "center", marginTop: 12 }}>
          <li>Paiement sécurisé</li>
          <li>Livraison offerte</li>
          <li>Rétractation 14 jours</li>
        </ul>
        {!stripe && <p className="muted" style={{ fontSize: ".85rem", marginTop: 10 }}>Mode démo : le paiement sera simulé à l’étape suivante.</p>}
      </form>

      <aside className="config-preview">
        <CardPreview design={design} side={side} qrUrl="https://tapikard.com/q/apercu00" />
        <div className="flip">
          <button type="button" className={`btn btn-sm ${side === "recto" ? "" : "btn-secondary"}`} onClick={() => setSide("recto")}>Recto</button>
          <button type="button" className={`btn btn-sm ${side === "verso" ? "" : "btn-secondary"}`} onClick={() => setSide("verso")}>Verso (QR code)</button>
        </div>
        <div className="panel panel-flat" style={{ marginTop: 18 }}>
          <div className="row between"><span>Carte Tapikard · {BASIC_VARIANTS[variant].label} × {quantity}</span><span>{euros(price.reference)}</span></div>
          <div className="row between" style={{ fontSize: ".9rem", color: "var(--ok)", fontWeight: 600 }}><span>Offre de lancement −{REMISE_LANCEMENT * 100} %{price.discount > 0 ? ` et remise quantité −${price.discount * 100} %` : ""}</span><span>−{euros(price.economie)}</span></div>
          <div className="row between muted" style={{ fontSize: ".9rem" }}><span>Livraison</span><span>Offerte</span></div>
          <hr style={{ border: 0, borderTop: "1px solid var(--line)", margin: "12px 0" }} />
          <div className="row between" style={{ fontWeight: 700, fontSize: "1.2rem" }}><span>Total TTC</span><span>{euros(price.total)}</span></div>
        </div>
        <ul className="includes" style={{ fontSize: ".92rem", margin: "22px 4px 0" }}>
          <li>Puce NFC sécurisée NTAG 424 DNA + QR code</li>
          <li>Profil en ligne illimité, modifiable à vie</li>
          <li>Échange de coordonnées et statistiques</li>
          <li>Livraison offerte, sans maquette à valider</li>
          <li>Sans abonnement</li>
        </ul>
      </aside>
    </div>
  );
}
