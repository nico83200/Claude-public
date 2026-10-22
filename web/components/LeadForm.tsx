"use client";

import { useState } from "react";

/** « Échanger mes coordonnées » : le visiteur laisse ses coordonnées au porteur de la carte. */
export function LeadForm({ cardId, ownerFirstName, preview = false }: { cardId?: string; ownerFirstName: string; preview?: boolean }) {
  const [open, setOpen] = useState(false);
  const [status, setStatus] = useState<"idle" | "sending" | "ok" | "error">("idle");
  const [error, setError] = useState("");

  if (status === "ok") return <div className="p-item" style={{ justifyContent: "center" }}>✓ Merci ! {ownerFirstName} a bien reçu vos coordonnées.</div>;
  if (!open) return <button type="button" className="p-item" style={{ width: "100%", justifyContent: "center", fontWeight: 700, cursor: "pointer", font: "inherit" }} onClick={() => setOpen(true)}>Échanger mes coordonnées avec {ownerFirstName}</button>;

  return (
    <form
      className="p-form"
      style={{ background: "var(--p-surface)", border: "1px solid var(--p-line)", borderRadius: 14, padding: 16 }}
      onSubmit={async (e) => {
        e.preventDefault();
        if (preview || !cardId) return;
        setStatus("sending");
        const data = Object.fromEntries(new FormData(e.currentTarget));
        const res = await fetch("/api/leads", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ ...data, cardId, consent: data.consent === "on" }) });
        if (res.ok) setStatus("ok");
        else {
          setStatus("error");
          setError((await res.json().catch(() => ({}))).error ?? "Envoi impossible.");
        }
      }}
    >
      <div className="field"><label htmlFor="l-name">Nom *</label><input id="l-name" name="name" type="text" required maxLength={80} autoComplete="name" /></div>
      <div className="field"><label htmlFor="l-email">Email</label><input id="l-email" name="email" type="email" maxLength={120} autoComplete="email" /></div>
      <div className="field"><label htmlFor="l-phone">Téléphone</label><input id="l-phone" name="phone" type="tel" maxLength={30} autoComplete="tel" /></div>
      <div className="field"><label htmlFor="l-company">Entreprise</label><input id="l-company" name="company" type="text" maxLength={80} autoComplete="organization" /></div>
      <div className="field"><label htmlFor="l-msg">Message</label><textarea id="l-msg" name="message" maxLength={1000} /></div>
      <label className="check" style={{ fontSize: ".85rem", marginBottom: 12 }}>
        <input type="checkbox" name="consent" required />
        <span>J’accepte de transmettre ces informations à {ownerFirstName}, qui pourra me recontacter.</span>
      </label>
      {status === "error" && <p style={{ color: "#e5484d", fontSize: ".9rem" }}>{error}</p>}
      <button className="p-save" disabled={status === "sending" || preview}>{preview ? "Aperçu" : status === "sending" ? "Envoi…" : "Envoyer"}</button>
    </form>
  );
}
