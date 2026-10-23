import Link from "next/link";
import { requireUser } from "@/lib/auth";
import { BarChart } from "@/components/BarChart";
import { CardBadge } from "@/components/Badges";
import { all } from "@/lib/db";
import { userStats } from "@/lib/stats";
import type { Card, Lead } from "@/lib/types";

export const metadata = { title: "Mon espace" };

export default async function ComptePage() {
  const user = await requireUser();
  const stats = userStats(user.id, 30);
  const cards = all<Card & { profile_name: string | null }>(
    "SELECT c.*, p.display_name profile_name FROM cards c LEFT JOIN profiles p ON p.id = c.profile_id WHERE c.user_id = ? ORDER BY c.created_at DESC LIMIT 5", user.id);
  const leads = all<Lead>("SELECT l.* FROM leads l JOIN profiles p ON p.id = l.profile_id WHERE p.user_id = ? ORDER BY l.created_at DESC LIMIT 4", user.id);
  const toActivate = cards.filter((c) => c.status === "shipped" || c.status === "encoded");

  return (
    <div className="stack">
      <div className="page-head">
        <h1>Bonjour {user.name?.split(" ")[0] ?? ""} 👋</h1>
        <Link href="/compte/profils" className="btn btn-secondary">Modifier mon profil</Link>
      </div>
      {toActivate.length > 0 && (
        <div className="alert">
          {toActivate.length} carte{toActivate.length > 1 ? "s" : ""} en attente d’activation. <Link href="/compte/cartes">Activer maintenant</Link>
        </div>
      )}
      <div className="grid grid-4">
        {[["Vues (30 j)", stats.views], ["Ajouts aux contacts", stats.vcf], ["Clics sur liens", stats.clicks], ["Contacts reçus", stats.leads]].map(([l, v]) => (
          <div className="panel" key={l}><div className="kpi-label">{l}</div><div className="kpi">{v}</div></div>
        ))}
      </div>
      <div className="panel">
        <div className="row between"><h3>Vues de profil — 30 derniers jours</h3><Link href="/compte/statistiques">Détails</Link></div>
        <BarChart data={stats.daily} />
      </div>
      <div className="grid grid-2">
        <div className="panel">
          <div className="row between"><h3>Mes cartes</h3><Link href="/compte/cartes">Gérer</Link></div>
          {cards.length === 0 ? <p className="muted">Aucune carte pour l’instant. <Link href="/carte-basic">Commander</Link></p> : (
            <table><tbody>
              {cards.map((c) => <tr key={c.id}><td>{c.label ?? c.id}<br /><small className="muted">{c.profile_name ?? "Sans profil"}</small></td><td style={{ textAlign: "right" }}><CardBadge status={c.status} /></td></tr>)}
            </tbody></table>
          )}
        </div>
        <div className="panel">
          <div className="row between"><h3>Derniers contacts reçus</h3><Link href="/compte/contacts">Tout voir</Link></div>
          {leads.length === 0 ? <p className="muted">Les personnes qui scannent votre carte pourront vous laisser leurs coordonnées.</p> : (
            <table><tbody>
              {leads.map((l) => <tr key={l.id}><td><strong>{l.name}</strong>{l.company && <> · {l.company}</>}<br /><small className="muted">{l.email ?? l.phone}</small></td><td style={{ textAlign: "right" }}><small className="muted">{new Date(l.created_at + "Z").toLocaleDateString("fr-FR")}</small></td></tr>)}
            </tbody></table>
          )}
        </div>
      </div>
    </div>
  );
}
