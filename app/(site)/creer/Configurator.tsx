"use client";

import { useActionState, useMemo, useState } from "react";
import { CardPreview } from "@/components/CardPreview";
import {
  DEFAULT_DESIGN, FINISHES, FONTS, MATERIALS, QUANTITIES, TEMPLATES, euros, priceCents,
  type CardDesign, type FinishId, type FontId, type MaterialId, type TemplateId,
} from "@/lib/catalog";
import { createOrder, type CreateOrderState } from "./actions";

const STEPS = ["Matière", "Modèle", "Informations", "Commande"] as const;
const PALETTE = ["#5b3df5", "#0f766e", "#0369a1", "#be123c", "#c2410c", "#111827", "#a16207", "#7c3aed"];

async function resizeImage(file: File, max = 600): Promise<string> {
  const url = URL.createObjectURL(file);
  const img = new Image();
  await new Promise((ok, ko) => { img.onload = ok; img.onerror = ko; img.src = url; });
  const scale = Math.min(1, max / Math.max(img.width, img.height));
  const c = document.createElement("canvas");
  c.width = Math.round(img.width * scale);
  c.height = Math.round(img.height * scale);
  c.getContext("2d")!.drawImage(img, 0, 0, c.width, c.height);
  URL.revokeObjectURL(url);
  return c.toDataURL("image/png");
}

export function Configurator({ stripe, initialMaterial }: { stripe: boolean; initialMaterial?: MaterialId }) {
  const [design, setDesign] = useState<CardDesign>(() => {
    const m = initialMaterial ?? DEFAULT_DESIGN.material;
    return { ...DEFAULT_DESIGN, material: m, background: MATERIALS[m].base, ink: MATERIALS[m].ink };
  });
  const [step, setStep] = useState(0);
  const [side, setSide] = useState<"recto" | "verso">("recto");
  const [quantity, setQuantity] = useState(2);
  const [email, setEmail] = useState("");
  const [shipping, setShipping] = useState({ name: "", address: "", zip: "", city: "" });
  const [consent, setConsent] = useState(false);
  const [logoError, setLogoError] = useState<string | null>(null);
  const [state, action, pending] = useActionState<CreateOrderState, FormData>(createOrder, {});

  const set = <K extends keyof CardDesign>(k: K, v: CardDesign[K]) => setDesign((d) => ({ ...d, [k]: v }));
  const setMaterial = (m: MaterialId) =>
    setDesign((d) => ({
      ...d,
      material: m,
      finish: FINISHES[d.finish].materials.includes(m) ? d.finish : "mat",
      background: MATERIALS[m].base,
      ink: MATERIALS[m].ink,
    }));
  const price = useMemo(() => priceCents(design.material, design.finish, quantity), [design.material, design.finish, quantity]);
  const printable = MATERIALS[design.material].printable;
  const payload = JSON.stringify({ design, quantity, email, shipping, consent });

  return (
    <div className="configurator" style={{ marginTop: 20 }}>
      <div>
        <div className="steps-nav" role="tablist">
          {STEPS.map((s, i) => (
            <button key={s} type="button" role="tab" aria-selected={i === step} className={i === step ? "active" : ""} onClick={() => setStep(i)}>
              {i + 1}. {s}
            </button>
          ))}
        </div>

        <div className="panel">
          {step === 0 && (
            <div className="stack">
              <div>
                <h3>Matière</h3>
                <div className="choices">
                  {(Object.keys(MATERIALS) as MaterialId[]).map((m) => (
                    <label className="choice" key={m}>
                      <input type="radio" name="material" checked={design.material === m} onChange={() => setMaterial(m)} />
                      <strong><span className="swatch" style={{ background: MATERIALS[m].base }} />{MATERIALS[m].label}</strong>
                      <span>{MATERIALS[m].description}</span>
                      <span style={{ display: "block", marginTop: 4, color: "var(--ink)", fontWeight: 600 }}>{euros(MATERIALS[m].unitCents)}</span>
                    </label>
                  ))}
                </div>
              </div>
              <div>
                <h3>Finition</h3>
                <div className="choices">
                  {(Object.keys(FINISHES) as FinishId[]).map((f) => (
                    <label className="choice" key={f}>
                      <input type="radio" name="finish" checked={design.finish === f} disabled={!FINISHES[f].materials.includes(design.material)} onChange={() => set("finish", f)} />
                      <strong>{FINISHES[f].label}</strong>
                      <span>{FINISHES[f].extraCents ? `+ ${euros(FINISHES[f].extraCents)} / carte` : "Inclus"}</span>
                    </label>
                  ))}
                </div>
              </div>
              <p className="muted" style={{ margin: 0, fontSize: ".9rem" }}>Toutes les cartes intègrent une puce NFC sécurisée NTAG 424 DNA et un QR code de secours.</p>
            </div>
          )}

          {step === 1 && (
            <div className="stack">
              <div>
                <h3>Modèle</h3>
                <div className="choices">
                  {(Object.keys(TEMPLATES) as TemplateId[]).map((t) => (
                    <label className="choice" key={t}>
                      <input type="radio" name="template" checked={design.template === t} onChange={() => set("template", t)} />
                      <strong>{TEMPLATES[t].label}</strong>
                    </label>
                  ))}
                </div>
              </div>
              <div>
                <h3>Police</h3>
                <div className="choices">
                  {(Object.keys(FONTS) as FontId[]).map((f) => (
                    <label className="choice" key={f}>
                      <input type="radio" name="font" checked={design.font === f} onChange={() => set("font", f)} />
                      <strong style={{ fontFamily: FONTS[f].css }}>{FONTS[f].label}</strong>
                    </label>
                  ))}
                </div>
              </div>
              <div className="grid grid-3">
                {printable && (
                  <div className="field">
                    <label>Couleur principale</label>
                    <div className="row" style={{ gap: 6 }}>
                      {PALETTE.map((c) => (
                        <button key={c} type="button" aria-label={c} onClick={() => set("accent", c)}
                          style={{ width: 28, height: 28, borderRadius: 8, border: design.accent === c ? "3px solid var(--ink)" : "1px solid var(--line)", background: c, cursor: "pointer" }} />
                      ))}
                      <input type="color" value={design.accent} onChange={(e) => set("accent", e.target.value)} aria-label="Couleur personnalisée" />
                    </div>
                  </div>
                )}
                {printable && (
                  <div className="field">
                    <label htmlFor="bg">Fond</label>
                    <input id="bg" type="color" value={design.background} onChange={(e) => set("background", e.target.value)} />
                  </div>
                )}
                <div className="field">
                  <label htmlFor="ink">{printable ? "Texte" : "Gravure"}</label>
                  {printable ? (
                    <input id="ink" type="color" value={design.ink} onChange={(e) => set("ink", e.target.value)} />
                  ) : (
                    <select id="ink" value={design.ink} onChange={(e) => set("ink", e.target.value)}>
                      {design.material === "bambou"
                        ? [["#3b2614", "Gravure brune"], ["#111111", "Gravure noire"]].map(([v, l]) => <option key={v} value={v}>{l}</option>)
                        : [["#d4d4d8", "Argent"], ["#d6b25e", "Or"]].map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                    </select>
                  )}
                </div>
              </div>
              <div className="field">
                <label htmlFor="logo">Logo (PNG, JPG ou WebP)</label>
                <input id="logo" type="file" accept="image/png,image/jpeg,image/webp" onChange={async (e) => {
                  const f = e.target.files?.[0];
                  setLogoError(null);
                  if (!f) return;
                  if (f.size > 5_000_000) return setLogoError("Fichier trop lourd (5 Mo max).");
                  try { set("logo", await resizeImage(f)); } catch { setLogoError("Image illisible."); }
                }} />
                {logoError && <small style={{ color: "var(--danger)" }}>{logoError}</small>}
                {design.logo && <button type="button" className="btn btn-ghost btn-sm" onClick={() => set("logo", null)}>Retirer le logo</button>}
              </div>
            </div>
          )}

          {step === 2 && (
            <div>
              <h3>Informations imprimées</h3>
              <p className="muted" style={{ fontSize: ".9rem" }}>Elles pré-remplissent aussi votre profil en ligne, que vous pourrez enrichir (photo, liens…) et modifier à vie.</p>
              <div className="grid grid-2" style={{ gap: "0 16px" }}>
                {([
                  ["name", "Nom et prénom *", "text"], ["title", "Fonction", "text"], ["company", "Entreprise", "text"],
                  ["phone", "Téléphone", "tel"], ["email", "Email affiché", "email"], ["website", "Site web", "text"],
                ] as const).map(([k, l, type]) => (
                  <div className="field" key={k}>
                    <label htmlFor={k}>{l}</label>
                    <input id={k} type={type} value={design[k]} onChange={(e) => set(k, e.target.value)} maxLength={k === "email" || k === "website" ? 80 : 60} />
                  </div>
                ))}
              </div>
            </div>
          )}

          {step === 3 && (
            <form action={action}>
              <input type="hidden" name="payload" value={payload} />
              <h3>Quantité</h3>
              <div className="choices" style={{ marginBottom: 18 }}>
                {QUANTITIES.map((q) => {
                  const p = priceCents(design.material, design.finish, q.qty);
                  return (
                    <label className="choice" key={q.qty}>
                      <input type="radio" name="qty" checked={quantity === q.qty} onChange={() => setQuantity(q.qty)} />
                      <strong>{q.qty} carte{q.qty > 1 ? "s" : ""}</strong>
                      <span>{euros(p.unit)} / carte{q.discount ? ` (−${q.discount * 100} %)` : ""}</span>
                    </label>
                  );
                })}
              </div>
              <div className="field">
                <label htmlFor="acc-email">Votre email (accès à votre espace) *</label>
                <input id="acc-email" type="email" required value={email} onChange={(e) => setEmail(e.target.value)} autoComplete="email" />
              </div>
              {!stripe && (
                <>
                  <h3>Livraison</h3>
                  <div className="field"><label htmlFor="s-name">Nom *</label><input id="s-name" type="text" value={shipping.name} onChange={(e) => setShipping({ ...shipping, name: e.target.value })} autoComplete="name" /></div>
                  <div className="field"><label htmlFor="s-addr">Adresse *</label><input id="s-addr" type="text" value={shipping.address} onChange={(e) => setShipping({ ...shipping, address: e.target.value })} autoComplete="street-address" /></div>
                  <div className="grid" style={{ gridTemplateColumns: "1fr 2fr", gap: 12 }}>
                    <div className="field"><label htmlFor="s-zip">Code postal *</label><input id="s-zip" type="text" inputMode="numeric" value={shipping.zip} onChange={(e) => setShipping({ ...shipping, zip: e.target.value })} autoComplete="postal-code" /></div>
                    <div className="field"><label htmlFor="s-city">Ville *</label><input id="s-city" type="text" value={shipping.city} onChange={(e) => setShipping({ ...shipping, city: e.target.value })} autoComplete="address-level2" /></div>
                  </div>
                </>
              )}
              <label className="check" style={{ margin: "8px 0 18px" }}>
                <input type="checkbox" checked={consent} onChange={(e) => setConsent(e.target.checked)} />
                <span>J’accepte que les informations saisies soient publiées sur mon profil Tapikard (modifiables à tout moment) et j’ai lu la <a href="/confidentialite" target="_blank">politique de confidentialité</a>.</span>
              </label>
              {state.error && <div className="alert alert-danger" style={{ marginBottom: 14 }}>{state.error}</div>}
              <button className="btn btn-lg btn-block" disabled={pending}>
                {pending ? "Un instant…" : stripe ? `Payer ${euros(price.total)}` : `Commander — ${euros(price.total)}`}
              </button>
              {!stripe && <p className="muted" style={{ fontSize: ".85rem", marginTop: 10 }}>Mode démo : le paiement sera simulé à l’étape suivante.</p>}
            </form>
          )}

          <div className="row between" style={{ marginTop: 20 }}>
            <button type="button" className="btn btn-secondary" disabled={step === 0} onClick={() => setStep(step - 1)}>Retour</button>
            {step < STEPS.length - 1 && <button type="button" className="btn" onClick={() => setStep(step + 1)}>Continuer</button>}
          </div>
        </div>
      </div>

      <aside className="config-preview">
        <CardPreview design={design} side={side} qrUrl="https://tapikard.com/q/apercu00" />
        <div className="flip">
          <button type="button" className={`btn btn-sm ${side === "recto" ? "" : "btn-secondary"}`} onClick={() => setSide("recto")}>Recto</button>
          <button type="button" className={`btn btn-sm ${side === "verso" ? "" : "btn-secondary"}`} onClick={() => setSide("verso")}>Verso (NFC + QR)</button>
        </div>
        <div className="panel panel-flat" style={{ marginTop: 18 }}>
          <div className="row between"><span>{MATERIALS[design.material].label} · {FINISHES[design.finish].label}</span><span>{euros(price.unit)} × {quantity}</span></div>
          {price.discount > 0 && <div className="row between muted" style={{ fontSize: ".9rem" }}><span>Remise quantité</span><span>−{price.discount * 100} %</span></div>}
          <div className="row between muted" style={{ fontSize: ".9rem" }}><span>Livraison</span><span>Offerte</span></div>
          <hr style={{ border: 0, borderTop: "1px solid var(--line)", margin: "12px 0" }} />
          <div className="row between" style={{ fontWeight: 800, fontSize: "1.2rem" }}><span>Total TTC</span><span>{euros(price.total)}</span></div>
        </div>
      </aside>
    </div>
  );
}
