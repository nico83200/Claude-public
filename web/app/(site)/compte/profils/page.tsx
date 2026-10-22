import Link from "next/link";
import { requireUser } from "@/lib/auth";
import { all } from "@/lib/db";
import type { Profile } from "@/lib/types";
import { createProfile } from "./actions";

export const metadata = { title: "Mes profils" };

export default async function ProfilsPage() {
  const user = await requireUser();
  const profiles = all<Profile & { cards: number }>(
    "SELECT p.*, (SELECT COUNT(*) FROM cards c WHERE c.profile_id = p.id) cards FROM profiles p WHERE p.user_id = ? ORDER BY p.created_at", user.id);
  return (
    <div>
      <div className="page-head">
        <div>
          <h1>Mes profils</h1>
          <p className="muted" style={{ margin: 0 }}>Un profil = la page affichée quand on scanne votre carte. Vous pouvez en avoir plusieurs (pro, perso…) et choisir lequel chaque carte affiche.</p>
        </div>
        <form action={createProfile}><button className="btn">Nouveau profil</button></form>
      </div>
      <div className="grid grid-2">
        {profiles.map((p) => (
          <Link key={p.id} href={`/compte/profils/${p.id}`} className="panel" style={{ color: "inherit", textDecoration: "none", display: "flex", gap: 16, alignItems: "center" }}>
            {p.avatar ? <img src={p.avatar} alt="" style={{ width: 56, height: 56, borderRadius: "50%", objectFit: "cover" }} /> :
              <span style={{ width: 56, height: 56, borderRadius: "50%", background: p.accent, color: "#fff", display: "grid", placeItems: "center", fontWeight: 800, flexShrink: 0 }}>{p.display_name.slice(0, 1) || "?"}</span>}
            <span style={{ flex: 1 }}>
              <span className="badge">{p.label}</span>
              <strong style={{ display: "block", marginTop: 4 }}>{p.display_name || "Sans nom"}</strong>
              <small className="muted">{[p.title, p.company].filter(Boolean).join(" · ") || "—"} · {p.cards} carte{p.cards > 1 ? "s" : ""}</small>
            </span>
            {!p.is_public && <span className="badge badge-warn">Masqué</span>}
          </Link>
        ))}
      </div>
    </div>
  );
}
