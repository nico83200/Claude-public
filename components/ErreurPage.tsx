"use client";

import { useEffect, useState } from "react";
import { unstable_isUnrecognizedActionError } from "next/navigation";
import { LogoMark } from "./Logo";

/** Erreurs dues à une mise à jour du site pendant que la page était ouverte (formulaire ou fichiers d'une version précédente). */
function vientDUneMiseAJour(e: Error) {
  return (
    e.name === "UnrecognizedActionError" ||
    e.name === "ChunkLoadError" ||
    /Server Action .* was not found|Loading (CSS )?chunk|Failed to fetch dynamically imported module/i.test(e.message) ||
    unstable_isUnrecognizedActionError(e)
  );
}

/** Recharge une seule fois par minute, pour ne jamais boucler. */
function rechargerUneFois(): boolean {
  try {
    const avant = Number(sessionStorage.getItem("tapikard-recharge") ?? 0);
    if (Date.now() - avant < 60_000) return false;
    sessionStorage.setItem("tapikard-recharge", String(Date.now()));
  } catch {
    /* stockage indisponible : on recharge quand même */
  }
  window.location.reload();
  return true;
}

export function ErreurPage({ error, reset }: { error: Error & { digest?: string }; reset: () => void }) {
  const miseAJour = vientDUneMiseAJour(error);
  const [recharge, setRecharge] = useState(false);

  useEffect(() => {
    if (miseAJour) setRecharge(rechargerUneFois());
    else console.error(error);
  }, [error, miseAJour]);

  return (
    <div className="center-page">
      <div className="panel">
        <LogoMark size={44} />
        {miseAJour ? (
          <>
            <h1 style={{ fontSize: "1.5rem", marginTop: 16 }}>Le site vient d’être mis à jour</h1>
            <p className="muted">
              {recharge ? "Rechargement de la page…" : "Rechargez la page"}
              {" "}puis recommencez : la dernière action n’a pas été enregistrée.
            </p>
            {!recharge && <button className="btn" onClick={() => window.location.reload()}>Recharger la page</button>}
          </>
        ) : (
          <>
            <h1 style={{ fontSize: "1.5rem", marginTop: 16 }}>Une erreur est survenue</h1>
            <p className="muted">Réessayez dans un instant. Si le problème persiste, contactez-nous en indiquant la référence ci-dessous.</p>
            <div className="row" style={{ justifyContent: "center" }}>
              <button className="btn" onClick={() => reset()}>Réessayer</button>
              <button className="btn btn-secondary" onClick={() => window.location.reload()}>Recharger la page</button>
            </div>
            {error.digest && <p className="muted" style={{ fontSize: ".8rem", marginTop: 16 }}>Référence : {error.digest}</p>}
          </>
        )}
      </div>
    </div>
  );
}
