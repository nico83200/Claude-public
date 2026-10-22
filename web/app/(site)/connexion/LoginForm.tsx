"use client";

import { useActionState } from "react";
import { requestLogin, type LoginState } from "./actions";

export function LoginForm({ next }: { next: string }) {
  const [state, action, pending] = useActionState<LoginState, FormData>(requestLogin, {});
  if (state.sent) {
    return (
      <div className="stack">
        <div className="alert alert-ok">Si un compte peut être utilisé avec cette adresse, un lien de connexion vient d’être envoyé. Il est valable 20 minutes.</div>
        {state.devLink && (
          <div className="alert alert-warn" style={{ textAlign: "left" }}>
            <strong>Mode développement</strong> (aucun service d’email configuré) :<br />
            <a href={state.devLink}>Ouvrir le lien de connexion</a>
          </div>
        )}
      </div>
    );
  }
  return (
    <form action={action} style={{ textAlign: "left" }}>
      <input type="hidden" name="next" value={next} />
      <div className="field">
        <label htmlFor="email">Adresse email</label>
        <input id="email" name="email" type="email" required autoComplete="email" autoFocus placeholder="vous@exemple.fr" />
      </div>
      {state.error && <div className="alert alert-danger" style={{ marginBottom: 12 }}>{state.error}</div>}
      <button className="btn btn-block" disabled={pending}>{pending ? "Envoi…" : "Recevoir un lien de connexion"}</button>
    </form>
  );
}
