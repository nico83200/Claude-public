import { requireUser } from "@/lib/auth";
import { CardBadge } from "@/components/Badges";
import { all } from "@/lib/db";
import type { Card, Profile } from "@/lib/types";
import { setCardStatus, updateCard } from "./actions";

export const metadata = { title: "Mes cartes" };

export default async function CartesPage({ searchParams }: { searchParams: Promise<{ activee?: string }> }) {
  const { activee } = await searchParams;
  const user = await requireUser("/compte/cartes");
  const cards = all<Card & { last_scan: string | null; scans: number }>(
    `SELECT c.*, (SELECT MAX(at) FROM scan_events s WHERE s.card_id = c.id AND s.action = 'view') last_scan,
            (SELECT COUNT(*) FROM scan_events s WHERE s.card_id = c.id AND s.action = 'view') scans
     FROM cards c WHERE c.user_id = ? ORDER BY c.created_at DESC, c.id`, user.id);
  const profiles = all<Pick<Profile, "id" | "label" | "display_name">>("SELECT id, label, display_name FROM profiles WHERE user_id = ? ORDER BY created_at", user.id);

  return (
    <div>
      <div className="page-head">
        <div>
          <h1>Mes cartes</h1>
          <p className="muted" style={{ margin: 0 }}>Choisissez le profil affiché par chaque carte, désactivez une carte ou déclarez-la perdue.</p>
        </div>
      </div>
      {activee && <div className="alert alert-ok" style={{ marginBottom: 16 }}>Carte activée ! Faites un essai : approchez-la de votre téléphone.</div>}
      {cards.length === 0 && <div className="panel"><p className="muted" style={{ margin: 0 }}>Aucune carte. <a href="/creer">Commander mes premières cartes</a></p></div>}
      <div className="stack">
        {cards.map((c) => (
          <div className="panel" key={c.id}>
            <div className="row between">
              <div>
                <strong>{c.label ?? "Carte"}</strong> <CardBadge status={c.status} />
                <div className="muted" style={{ fontSize: ".85rem" }}>
                  N° {c.id}{c.uid ? ` · puce ${c.uid}` : ""} · {c.scans} vue{c.scans > 1 ? "s" : ""}
                  {c.last_scan ? ` · dernier scan le ${new Date(c.last_scan + "Z").toLocaleDateString("fr-FR")}` : ""}
                </div>
              </div>
              <div className="row">
                {c.status === "active" && <a className="btn btn-secondary btn-sm" href={`/q/${c.id}`} target="_blank">Voir</a>}
                {(c.status === "shipped" || c.status === "encoded") && <form action={setCardStatus.bind(null, c.id, "active")}><button className="btn btn-sm">Activer</button></form>}
                {c.status === "active" && <form action={setCardStatus.bind(null, c.id, "disabled")}><button className="btn btn-secondary btn-sm">Désactiver</button></form>}
                {c.status === "disabled" && <form action={setCardStatus.bind(null, c.id, "active")}><button className="btn btn-sm">Réactiver</button></form>}
                {["active", "disabled", "shipped"].includes(c.status) && (
                  <form action={setCardStatus.bind(null, c.id, "lost")}><button className="btn btn-ghost btn-sm" style={{ color: "var(--danger)" }}>Déclarer perdue</button></form>
                )}
              </div>
            </div>
            {c.status !== "lost" && (
              <form action={updateCard.bind(null, c.id)} className="row" style={{ marginTop: 14 }}>
                <input type="text" name="label" defaultValue={c.label ?? ""} aria-label="Nom de la carte" placeholder="Nom de la carte" style={{ maxWidth: 220 }} />
                <select name="profile_id" defaultValue={c.profile_id ?? ""} aria-label="Profil affiché" style={{ maxWidth: 280 }}>
                  {profiles.map((p) => <option key={p.id} value={p.id}>{p.label} — {p.display_name}</option>)}
                </select>
                <button className="btn btn-secondary btn-sm">Enregistrer</button>
              </form>
            )}
            {c.status === "provisioned" && <p className="muted" style={{ margin: "10px 0 0", fontSize: ".9rem" }}>En cours de fabrication.</p>}
          </div>
        ))}
      </div>
    </div>
  );
}
