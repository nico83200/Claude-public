"use client";

import { useActionState, useState } from "react";
import { CardPreview } from "@/components/CardPreview";
import { BASIC_VARIANTS, QUANTITIES, basicDesign, basicPriceCents, euros, type BasicVariant } from "@/lib/catalog";
import { createBasicOrder, type BasicOrderState } from "./actions";

export function BasicOrderForm({ stripe }: { stripe: boolean }) {
  const [variant, setVariant] = useState<BasicVariant>("noir");
  const [quantity, setQuantity] = useState(1);
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
              <input type="radio" name="v" checked={variant === v} onChange={() => setVariant(v)} />
              <strong><span className="swatch" style={{ background: BASIC_VARIANTS[v].background }} />{BASIC_VARIANTS[v].label}</strong>
            </label>
          ))}
        </div>
        <h3>Quantité</h3>
        <div className="choices" style={{ marginBottom: 18 }}>
          {QUANTITIES.map((q) => (
            <label className="choice" key={q.qty}>
              <input type="radio" name="q" checked={quantity === q.qty} onChange={() => setQuantity(q.qty)} />
              <strong>{q.qty} carte{q.qty > 1 ? "s" : ""}</strong>
              <span>{euros(basicPriceCents(q.qty).unit)} / carte{q.discount ? ` (−${q.discount * 100} %)` : ""}</span>
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
        <button className="btn btn-lg btn-block" disabled={pending}>
          {pending ? "Un instant…" : stripe ? `Payer ${euros(price.total)}` : `Commander — ${euros(price.total)}`}
        </button>
        {!stripe && <p className="muted" style={{ fontSize: ".85rem", marginTop: 10 }}>Mode démo : le paiement sera simulé à l’étape suivante.</p>}
      </form>

      <aside className="config-preview">
        <CardPreview design={design} side={side} qrUrl="https://tapikard.com/q/apercu00" />
        <div className="flip">
          <button type="button" className={`btn btn-sm ${side === "recto" ? "" : "btn-secondary"}`} onClick={() => setSide("recto")}>Recto</button>
          <button type="button" className={`btn btn-sm ${side === "verso" ? "" : "btn-secondary"}`} onClick={() => setSide("verso")}>Verso (QR code)</button>
        </div>
        <div className="panel panel-flat" style={{ marginTop: 18 }}>
          <div className="row between"><span>Carte Basic · {BASIC_VARIANTS[variant].label}</span><span>{euros(price.unit)} × {quantity}</span></div>
          {price.discount > 0 && <div className="row between muted" style={{ fontSize: ".9rem" }}><span>Remise quantité</span><span>−{price.discount * 100} %</span></div>}
          <div className="row between muted" style={{ fontSize: ".9rem" }}><span>Livraison</span><span>Offerte</span></div>
          <hr style={{ border: 0, borderTop: "1px solid var(--line)", margin: "12px 0" }} />
          <div className="row between" style={{ fontWeight: 800, fontSize: "1.2rem" }}><span>Total TTC</span><span>{euros(price.total)}</span></div>
        </div>
      </aside>
    </div>
  );
}
