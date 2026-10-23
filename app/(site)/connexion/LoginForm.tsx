"use client";

import { useActionState } from "react";
import { loginWithCode, requestLogin, type CodeState, type LoginState } from "./actions";

function CodeForm({ email, next }: { email: string; next: string }) {
  const [state, action, pending] = useActionState<CodeState, FormData>(loginWithCode, {});
  return (
    <form action={action} style={{ textAlign: "left", marginTop: 18 }}>
      <input type="hidden" name="email" value={email} />
      <input type="hidden" name="next" value={next} />
      <div className="field">
        <label htmlFor="code">Ou saisissez le code reçu</label>
        <input id="code" name="code" type="text" inputMode="numeric" autoComplete="one-time-code" pattern="[0-9 ]{6,7}" maxLength={7} placeholder="123 456" required
          style={{ fontSize: "1.4rem", letterSpacing: ".3em", textAlign: "center" }} />
      </div>
      {state.error && <div className="alert alert-danger" style={{ marginBottom: 12 }}>{state.error}</div>}
      <button className="btn btn-block" disabled={pending}>{pending ? "Vérification…" : "Me connecter"}</button>
    </form>
  );
}

export function LoginForm({ next }: { next: string }) {
  const [state, action, pending] = useActionState<LoginState, FormData>(requestLogin, {});
  if (state.sent && state.email) {
    return (
      <div className="stack">
        <div className="alert alert-ok">Si un compte peut être utilisé avec cette adresse, un email vient d’être envoyé avec un lien et un code de connexion, valables 20 minutes.</div>
        {state.dev && (
          <div className="alert alert-warn" style={{ textAlign: "left" }}>
            <strong>Mode développement</strong> (aucun service d’email configuré) :<br />
            <a href={state.dev.lien}>Ouvrir le lien de connexion</a> · code <strong>{state.dev.code}</strong>
          </div>
        )}
        <CodeForm email={state.email} next={next} />
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
