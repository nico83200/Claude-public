import { APP_URL } from "@/lib/config";
import { REMISE_ENTOURAGE, finLisible } from "@/lib/promo";

/** Encadré « code entourage » : le client partage son code, ses proches ont 25 % de remise supplémentaire. */
export function CodeEntourage({ code, fin, utilisations }: { code: string; fin: string; utilisations?: number }) {
  const lien = `${APP_URL}/carte-basic?code=${encodeURIComponent(code)}`;
  const texte = `J’utilise Tapikard pour ma carte de visite NFC. Avec mon code ${code}, tu as ${REMISE_ENTOURAGE * 100} % de remise supplémentaire jusqu’au ${finLisible(fin)} : ${lien}`;
  return (
    <div className="panel code-entourage">
      <span className="eyebrow">Pour votre entourage</span>
      <h3 style={{ margin: "10px 0 6px" }}>−{REMISE_ENTOURAGE * 100} % supplémentaires pour vos proches</h3>
      <p className="muted" style={{ marginTop: 0 }}>Partagez votre code personnel : il s’ajoute à l’offre en cours. Valable jusqu’au <strong>{finLisible(fin)}</strong>.</p>
      <div className="code-entourage-code">{code}</div>
      <div className="row" style={{ marginTop: 12 }}>
        <a className="btn btn-sm" href={`mailto:?subject=${encodeURIComponent("Une carte de visite NFC à prix doux")}&body=${encodeURIComponent(texte)}`}>Envoyer par email</a>
        <a className="btn btn-sm btn-secondary" href={`sms:?&body=${encodeURIComponent(texte)}`}>Par SMS</a>
        <a className="btn btn-sm btn-secondary" href={`https://wa.me/?text=${encodeURIComponent(texte)}`} target="_blank" rel="noopener noreferrer">WhatsApp</a>
      </div>
      {utilisations !== undefined && utilisations > 0 && (
        <p style={{ margin: "12px 0 0", fontWeight: 600, color: "var(--ok)" }}>{utilisations} proche{utilisations > 1 ? "s ont" : " a"} déjà utilisé votre code.</p>
      )}
    </div>
  );
}
