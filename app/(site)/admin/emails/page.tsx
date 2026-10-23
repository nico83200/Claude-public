import Link from "next/link";
import { requireAdmin } from "@/lib/auth";
import { EMAIL_ENABLED } from "@/lib/config";
import { OUTILS, type Outil } from "@/lib/emails/campagnes";
import { CAMPAGNES, EMAILS_AUTO, emailParId } from "@/lib/emails/catalogue";
import { adminEnvoyerTestEmail } from "../actions";

export const metadata = { title: "Emails" };

export default async function AdminEmails({ searchParams }: { searchParams: Promise<{ voir?: string; outil?: string; largeur?: string; envoye?: string; erreur?: string }> }) {
  const sp = await searchParams;
  const admin = await requireAdmin();
  const voir = sp.voir && emailParId(sp.voir) ? sp.voir : EMAILS_AUTO[0].id;
  const outil = (sp.outil && sp.outil in OUTILS ? sp.outil : "brevo") as Outil;
  const mobile = sp.largeur === "mobile";
  const courant = emailParId(voir, outil)!;
  const url = (o: Record<string, string | undefined>) =>
    "/admin/emails?" + new URLSearchParams(Object.entries({ voir, outil, largeur: mobile ? "mobile" : undefined, ...o }).filter(([, v]) => v) as [string, string][]).toString();

  const Ligne = ({ id, nom, detail }: { id: string; nom: string; detail: string }) => (
    <Link href={url({ voir: id })} className="panel panel-flat" style={{ display: "block", padding: "12px 14px", color: "inherit", textDecoration: "none", borderColor: id === voir ? "var(--ink)" : undefined, boxShadow: id === voir ? "0 0 0 1px var(--ink)" : undefined }}>
      <strong style={{ fontSize: ".93rem" }}>{nom}</strong><br /><small className="muted">{detail}</small>
    </Link>
  );

  return (
    <div className="stack">
      <div className="page-head">
        <div>
          <h1>Emails</h1>
          <p className="muted" style={{ margin: 0 }}>Les emails automatiques du site et des campagnes prêtes à l’emploi, à la charte Tapikard. Aperçus avec des données d’exemple.</p>
        </div>
      </div>
      {sp.envoye && <div className="alert alert-ok">Email de test envoyé à {admin.email}.</div>}
      {sp.erreur && <div className="alert alert-danger">{sp.erreur}</div>}
      {!EMAIL_ENABLED && <div className="alert alert-warn">L’envoi d’emails n’est pas configuré (variables SMTP_HOST, SMTP_USER, SMTP_PASS) : les tests ne partiront pas.</div>}

      <div style={{ display: "grid", gridTemplateColumns: "minmax(240px, 300px) minmax(0, 1fr)", gap: 20, alignItems: "start" }} className="emails-grid">
        <div className="stack" style={{ gap: 8 }}>
          <small className="eyebrow">Automatiques</small>
          {EMAILS_AUTO.map((e) => <Ligne key={e.id} id={e.id} nom={e.nom} detail={e.quand} />)}
          <small className="eyebrow" style={{ marginTop: 18 }}>Campagnes</small>
          {CAMPAGNES.map((c) => <Ligne key={c.id} id={c.id} nom={c.nom} detail={c.cible} />)}
        </div>

        <div className="panel" style={{ padding: 18 }}>
          <div className="row between" style={{ marginBottom: 12 }}>
            <div style={{ minWidth: 0 }}>
              <small className="muted">Objet</small>
              <div style={{ fontWeight: 600 }}>{courant.email.sujet}</div>
            </div>
            <div className="row" style={{ gap: 6 }}>
              <Link className={`btn btn-sm ${mobile ? "btn-secondary" : ""}`} href={url({ largeur: undefined })}>Ordinateur</Link>
              <Link className={`btn btn-sm ${mobile ? "" : "btn-secondary"}`} href={url({ largeur: "mobile" })}>Mobile</Link>
            </div>
          </div>
          <div style={{ background: "var(--surface-2)", borderRadius: 14, padding: mobile ? "18px 0" : 0, display: "flex", justifyContent: "center" }}>
            <iframe
              title="Aperçu de l’email"
              src={`/admin/emails/apercu/${voir}?outil=${outil}`}
              style={{ width: mobile ? 375 : "100%", height: 900, border: 0, borderRadius: mobile ? 24 : 12, background: "#f7f5f0", boxShadow: mobile ? "0 0 0 10px #121117" : undefined }}
            />
          </div>
          <div className="row" style={{ marginTop: 14 }}>
            <form action={adminEnvoyerTestEmail.bind(null, voir, outil)}><button className="btn">M’envoyer un test</button></form>
            {courant.campagne && (
              <>
                <form className="row" style={{ gap: 6 }} action="/admin/emails" method="get">
                  <input type="hidden" name="voir" value={voir} />
                  <select name="outil" defaultValue={outil} aria-label="Outil d’emailing" style={{ width: "auto" }}>
                    {(Object.keys(OUTILS) as Outil[]).map((o) => <option key={o} value={o}>{OUTILS[o].label}</option>)}
                  </select>
                  <button className="btn btn-secondary btn-sm">Appliquer</button>
                </form>
                <a className="btn btn-secondary" href={`/admin/emails/apercu/${voir}?outil=${outil}&telecharger=1`}>Télécharger le HTML</a>
              </>
            )}
          </div>
          {courant.campagne && (
            <p className="muted" style={{ fontSize: ".86rem", marginTop: 12, marginBottom: 0 }}>
              Importez ce fichier dans votre outil d’emailing ({OUTILS[outil].label} : lien de désinscription <code>{OUTILS[outil].balise}</code> déjà inséré).
              Envoyez uniquement à des contacts qui l’ont accepté, ou à des professionnels pour une offre liée à leur activité, avec désinscription en un clic.
            </p>
          )}
        </div>
      </div>
    </div>
  );
}
