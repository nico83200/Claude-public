import Link from "next/link";
import { requireUser } from "@/lib/auth";
import { all } from "@/lib/db";
import { LEAD_STATUS_LABEL, type Lead, type LeadStatus } from "@/lib/types";
import { deleteLead, saveWebhook, testWebhook, updateLead } from "./actions";

export const metadata = { title: "Contacts reçus" };

const CRM_MSG: Record<string, [string, string]> = {
  enregistre: ["alert-ok", "Adresse de synchronisation enregistrée. Chaque nouveau contact y sera envoyé."],
  supprime: ["alert-ok", "Synchronisation désactivée."],
  invalide: ["alert-danger", "Adresse invalide : une URL https publique est nécessaire (fournie par Zapier, Make, HubSpot, n8n…)."],
  "test-ok": ["alert-ok", "Test envoyé avec succès : vérifiez la réception dans votre outil."],
  "test-ko": ["alert-danger", "Le test a échoué : l’adresse n’a pas répondu correctement."],
};

const TONE: Record<LeadStatus, string> = { nouveau: "badge-brand", a_relancer: "badge-warn", en_cours: "badge-brand", client: "badge-ok", sans_suite: "" };

export default async function ContactsPage({ searchParams }: { searchParams: Promise<{ statut?: string; crm?: string }> }) {
  const { statut, crm } = await searchParams;
  const user = await requireUser("/compte/contacts");
  const filter = statut && statut in LEAD_STATUS_LABEL ? statut : null;
  const leads = all<Lead & { profile_label: string }>(
    `SELECT l.*, p.label profile_label FROM leads l JOIN profiles p ON p.id = l.profile_id
     WHERE p.user_id = ? AND (? IS NULL OR l.status = ?) ORDER BY l.created_at DESC`, user.id, filter, filter);
  const counts = Object.fromEntries(
    all<{ status: string; n: number }>("SELECT l.status, COUNT(*) n FROM leads l JOIN profiles p ON p.id = l.profile_id WHERE p.user_id = ? GROUP BY l.status", user.id).map((r) => [r.status, r.n]),
  );
  const total = Object.values(counts).reduce((a, b) => a + Number(b), 0);

  return (
    <div className="stack">
      <div className="page-head">
        <div>
          <h1>Contacts reçus</h1>
          <p className="muted" style={{ margin: 0 }}>Les personnes qui ont scanné votre carte et vous ont laissé leurs coordonnées. Vous êtes prévenu par email à chaque nouveau contact, avec sa fiche à ajouter à votre carnet d’adresses.</p>
        </div>
        {total > 0 && <a className="btn btn-secondary" href="/compte/contacts/export">Exporter (CSV / Excel)</a>}
      </div>

      {total > 0 && (
        <div className="row" style={{ gap: 6 }}>
          <Link className={`btn btn-sm ${!filter ? "" : "btn-secondary"}`} href="/compte/contacts">Tous ({total})</Link>
          {(Object.keys(LEAD_STATUS_LABEL) as LeadStatus[]).map((s) => (
            <Link key={s} className={`btn btn-sm ${filter === s ? "" : "btn-secondary"}`} href={`/compte/contacts?statut=${s}`}>{LEAD_STATUS_LABEL[s]} ({counts[s] ?? 0})</Link>
          ))}
        </div>
      )}

      {leads.length === 0 ? (
        <div className="panel"><p className="muted" style={{ margin: 0 }}>Aucun contact {filter ? "dans cette catégorie" : "pour le moment"}. Vérifiez que l’option « Proposer aux visiteurs de me laisser leurs coordonnées » est activée sur votre profil.</p></div>
      ) : (
        leads.map((l) => (
          <div className="panel" key={l.id}>
            <div className="row between" style={{ alignItems: "flex-start" }}>
              <div>
                <strong style={{ fontSize: "1.05rem" }}>{l.name}</strong>{l.company && <span className="muted"> · {l.company}</span>}{" "}
                <span className={`badge ${TONE[l.status]}`}>{LEAD_STATUS_LABEL[l.status]}</span>
                <div className="muted" style={{ fontSize: ".9rem", marginTop: 4 }}>
                  {l.email && <a href={`mailto:${l.email}`}>{l.email}</a>}{l.email && l.phone && " · "}{l.phone && <a href={`tel:${l.phone}`}>{l.phone}</a>}
                  {" · "}{new Date(l.created_at + "Z").toLocaleDateString("fr-FR")} via « {l.profile_label} »
                </div>
                {l.message && <p style={{ margin: "8px 0 0" }}>« {l.message} »</p>}
              </div>
              <a className="btn btn-secondary btn-sm" href={`/compte/contacts/${l.id}/vcard`}>Ajouter à mes contacts</a>
            </div>
            <form action={updateLead.bind(null, l.id)} className="row" style={{ marginTop: 12 }}>
              <select name="status" defaultValue={l.status} aria-label="Statut" style={{ maxWidth: 170 }}>
                {(Object.keys(LEAD_STATUS_LABEL) as LeadStatus[]).map((s) => <option key={s} value={s}>{LEAD_STATUS_LABEL[s]}</option>)}
              </select>
              <input type="text" name="note" defaultValue={l.note ?? ""} placeholder="Note (rappeler mardi, devis envoyé…)" aria-label="Note" style={{ flex: 1, minWidth: 200 }} />
              <button className="btn btn-secondary btn-sm">Enregistrer</button>
              <button formAction={deleteLead.bind(null, l.id)} className="btn btn-ghost btn-sm" style={{ color: "var(--danger)" }}>Supprimer</button>
            </form>
          </div>
        ))
      )}

      <div className="panel">
        <h3>Synchronisation avec votre CRM</h3>
        <p className="muted" style={{ fontSize: ".92rem" }}>
          Envoyez automatiquement chaque nouveau contact vers HubSpot, Pipedrive, Salesforce, Google Sheets… via <strong>Zapier</strong>, <strong>Make</strong> ou <strong>n8n</strong> :
          créez un déclencheur « Webhook » (ex. Zapier « Catch Hook »), puis collez ici l’adresse qu’il vous donne.
        </p>
        {crm && CRM_MSG[crm] && <div className={`alert ${CRM_MSG[crm][0]}`} style={{ marginBottom: 12 }}>{CRM_MSG[crm][1]}</div>}
        <form action={saveWebhook} className="row">
          <input type="url" name="webhook_url" defaultValue={user.webhook_url ?? ""} placeholder="https://hooks.zapier.com/hooks/catch/…" aria-label="Adresse du webhook" style={{ flex: 1, minWidth: 260 }} />
          <button className="btn btn-secondary">Enregistrer</button>
          {user.webhook_url && <button formAction={testWebhook} className="btn btn-ghost">Envoyer un test</button>}
        </form>
      </div>
    </div>
  );
}
