import { requireUser } from "@/lib/auth";
import { all } from "@/lib/db";
import type { Lead } from "@/lib/types";

export const metadata = { title: "Contacts reçus" };

export default async function ContactsPage() {
  const user = await requireUser("/compte/contacts");
  const leads = all<Lead & { profile_label: string }>(
    "SELECT l.*, p.label profile_label FROM leads l JOIN profiles p ON p.id = l.profile_id WHERE p.user_id = ? ORDER BY l.created_at DESC", user.id);
  return (
    <div>
      <div className="page-head">
        <div>
          <h1>Contacts reçus</h1>
          <p className="muted" style={{ margin: 0 }}>Les personnes qui ont scanné votre carte et vous ont laissé leurs coordonnées.</p>
        </div>
        {leads.length > 0 && <a className="btn btn-secondary" href="/compte/contacts/export">Exporter (CSV)</a>}
      </div>
      {leads.length === 0 ? <div className="panel"><p className="muted" style={{ margin: 0 }}>Aucun contact pour le moment. Vérifiez que l’option « Proposer aux visiteurs de me laisser leurs coordonnées » est activée sur votre profil.</p></div> : (
        <div className="table-wrap">
          <table>
            <thead><tr><th>Date</th><th>Nom</th><th>Coordonnées</th><th>Entreprise</th><th>Message</th><th>Profil</th></tr></thead>
            <tbody>
              {leads.map((l) => (
                <tr key={l.id}>
                  <td style={{ whiteSpace: "nowrap" }}>{new Date(l.created_at + "Z").toLocaleDateString("fr-FR")}</td>
                  <td><strong>{l.name}</strong></td>
                  <td>{l.email && <a href={`mailto:${l.email}`}>{l.email}</a>}{l.email && l.phone && <br />}{l.phone && <a href={`tel:${l.phone}`}>{l.phone}</a>}</td>
                  <td>{l.company ?? "—"}</td>
                  <td style={{ maxWidth: 280 }}>{l.message ?? "—"}</td>
                  <td><span className="badge">{l.profile_label}</span></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
