"use client";

import { useActionState } from "react";
import { activateCard, type ActivateState } from "./actions";

export function ActivateForm({ cardId, needsCode }: { cardId: string; needsCode: boolean }) {
  const [state, action, pending] = useActionState<ActivateState, FormData>(activateCard, {});
  return (
    <form action={action} style={{ textAlign: "left" }}>
      <div className="field">
        <label htmlFor="cardId">Identifiant de la carte</label>
        <input id="cardId" name="cardId" type="text" defaultValue={cardId} required />
      </div>
      {needsCode && (
        <div className="field">
          <label htmlFor="code">Code d’activation</label>
          <input id="code" name="code" type="text" placeholder="XXXX-XXXX" autoComplete="one-time-code" required />
          <small className="muted">Imprimé sur l’encart livré avec vos cartes.</small>
        </div>
      )}
      {state.error && <div className="alert alert-danger" style={{ marginBottom: 12 }}>{state.error}</div>}
      <button className="btn btn-block" disabled={pending}>{pending ? "Activation…" : "Activer la carte"}</button>
    </form>
  );
}
