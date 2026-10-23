"use client";

import { useActionState, useState } from "react";
import { ProfileView } from "@/components/ProfileView";
import { parseLinks, type Profile, type ProfileLink } from "@/lib/types";
import { deleteProfile, saveProfile, type SaveState } from "../actions";

async function toAvatar(file: File): Promise<string> {
  const url = URL.createObjectURL(file);
  const img = new Image();
  await new Promise((ok, ko) => { img.onload = ok; img.onerror = ko; img.src = url; });
  const size = 320;
  const side = Math.min(img.width, img.height);
  const c = document.createElement("canvas");
  c.width = c.height = size;
  c.getContext("2d")!.drawImage(img, (img.width - side) / 2, (img.height - side) / 2, side, side, 0, 0, size, size);
  URL.revokeObjectURL(url);
  return c.toDataURL("image/jpeg", 0.85);
}

/** Logo redimensionné (600 × 200 px au plus, proportions conservées), en PNG pour garder la transparence. */
async function toLogo(file: File): Promise<string> {
  const url = URL.createObjectURL(file);
  const img = new Image();
  await new Promise((ok, ko) => { img.onload = ok; img.onerror = ko; img.src = url; });
  const k = Math.min(1, 600 / img.width, 200 / img.height);
  const c = document.createElement("canvas");
  c.width = Math.max(1, Math.round(img.width * k));
  c.height = Math.max(1, Math.round(img.height * k));
  c.getContext("2d")!.drawImage(img, 0, 0, c.width, c.height);
  URL.revokeObjectURL(url);
  const png = c.toDataURL("image/png");
  return png.length < 380_000 ? png : c.toDataURL("image/jpeg", 0.9);
}

const FONDS_LOGO = [["clair", "Clair"], ["couleur", "Couleur du profil"], ["sombre", "Sombre"]] as const;

const FIELDS = [
  ["display_name", "Nom affiché *", "text"], ["title", "Fonction", "text"], ["company", "Entreprise", "text"],
  ["mobile", "Mobile", "tel"], ["phone", "Téléphone fixe", "tel"], ["email", "Email", "email"],
  ["website", "Site web", "text"], ["address", "Adresse", "text"],
] as const;

export function ProfileEditor({ profile, publicUrl }: { profile: Profile; publicUrl: string | null }) {
  const [p, setP] = useState<Profile>(profile);
  const [links, setLinks] = useState<ProfileLink[]>(parseLinks(profile));
  const [state, action, pending] = useActionState<SaveState, FormData>(saveProfile.bind(null, profile.id), {});
  const set = <K extends keyof Profile>(k: K, v: Profile[K]) => setP((x) => ({ ...x, [k]: v }));
  const preview = { ...p, links: JSON.stringify(links) };

  return (
    <div className="configurator">
      <form action={action}>
        <div className="page-head">
          <h1>Modifier le profil</h1>
          <div className="row">
            {publicUrl && <a href={publicUrl} target="_blank" className="btn btn-secondary btn-sm">Voir en ligne</a>}
            <button className="btn" disabled={pending}>{pending ? "Enregistrement…" : "Enregistrer"}</button>
          </div>
        </div>
        {state.ok && <div className="alert alert-ok" style={{ marginBottom: 14 }}>Profil enregistré. Vos cartes affichent déjà la nouvelle version.</div>}
        {state.error && <div className="alert alert-danger" style={{ marginBottom: 14 }}>{state.error}</div>}

        <div className="panel stack">
          <div className="row" style={{ alignItems: "flex-end" }}>
            <div className="field" style={{ flex: 1, minWidth: 180, marginBottom: 0 }}>
              <label htmlFor="label">Nom interne du profil</label>
              <input id="label" name="label" type="text" value={p.label} onChange={(e) => set("label", e.target.value)} maxLength={30} />
            </div>
            <div className="field" style={{ marginBottom: 0 }}>
              <label htmlFor="avatar-file">Photo</label>
              <input id="avatar-file" type="file" accept="image/png,image/jpeg" onChange={async (e) => { const f = e.target.files?.[0]; if (f) set("avatar", await toAvatar(f)); }} />
            </div>
            {p.avatar && <button type="button" className="btn btn-ghost btn-sm" onClick={() => set("avatar", null)}>Retirer la photo</button>}
            <input type="hidden" name="avatar" value={p.avatar ?? ""} />
          </div>
          <div className="grid grid-2" style={{ gap: "0 16px" }}>
            {FIELDS.map(([k, l, type]) => (
              <div className="field" key={k}>
                <label htmlFor={k}>{l}</label>
                <input id={k} name={k} type={type} value={(p[k] as string | null) ?? ""} onChange={(e) => set(k, e.target.value)} />
              </div>
            ))}
          </div>
          <div className="field">
            <label htmlFor="bio">Présentation courte</label>
            <textarea id="bio" name="bio" value={p.bio ?? ""} maxLength={300} onChange={(e) => set("bio", e.target.value)} />
          </div>
        </div>

        <div className="panel" style={{ marginTop: 16 }}>
          <h3>Logo</h3>
          <p className="muted" style={{ marginTop: 0, fontSize: ".9rem" }}>
            Votre logo remplace le bandeau coloré en haut de votre fiche, et il est ajouté à la fiche contact enregistrée dans le téléphone.
            PNG avec fond transparent conseillé.
          </p>
          <input type="hidden" name="logo" value={p.logo ?? ""} />
          <input type="hidden" name="logo_fond" value={p.logo_fond ?? "clair"} />
          <div className="row">
            <input type="file" accept="image/png,image/jpeg" aria-label="Logo" onChange={async (e) => { const f = e.target.files?.[0]; if (f) set("logo", await toLogo(f)); }} />
            {p.logo && <button type="button" className="btn btn-ghost btn-sm" onClick={() => set("logo", null)}>Retirer le logo (revenir au bandeau)</button>}
          </div>
          {p.logo && (
            <div className="row" style={{ marginTop: 12 }}>
              <span style={{ fontSize: ".9rem", fontWeight: 600 }}>Fond derrière le logo</span>
              {FONDS_LOGO.map(([v, l]) => (
                <button type="button" key={v} className={`btn btn-sm ${p.logo_fond === v ? "" : "btn-secondary"}`} onClick={() => set("logo_fond", v)}>{l}</button>
              ))}
            </div>
          )}
        </div>

        <div className="panel" style={{ marginTop: 16 }}>
          <h3>Liens</h3>
          <input type="hidden" name="links" value={JSON.stringify(links)} />
          {links.map((l, i) => (
            <div className="row" key={i} style={{ marginBottom: 8, flexWrap: "nowrap" }}>
              <input type="text" aria-label="Libellé" placeholder="LinkedIn" value={l.label} style={{ flex: 1 }} onChange={(e) => setLinks(links.map((x, j) => (j === i ? { ...x, label: e.target.value } : x)))} />
              <input type="url" aria-label="URL" placeholder="https://…" value={l.url} style={{ flex: 2 }} onChange={(e) => setLinks(links.map((x, j) => (j === i ? { ...x, url: e.target.value } : x)))} />
              <button type="button" className="btn btn-ghost btn-sm" aria-label="Supprimer" onClick={() => setLinks(links.filter((_, j) => j !== i))}>✕</button>
            </div>
          ))}
          {links.length < 10 && <button type="button" className="btn btn-secondary btn-sm" onClick={() => setLinks([...links, { label: "", url: "https://" }])}>+ Ajouter un lien</button>}
        </div>

        <div className="panel" style={{ marginTop: 16 }}>
          <h3>Apparence et options</h3>
          <div className="row" style={{ marginBottom: 14 }}>
            <label htmlFor="accent" style={{ margin: 0 }}>Couleur</label>
            <input id="accent" name="accent" type="color" value={p.accent} onChange={(e) => set("accent", e.target.value)} />
          </div>
          <label className="check"><input type="checkbox" name="dark" checked={!!p.dark} onChange={(e) => set("dark", e.target.checked ? 1 : 0)} /> Thème sombre</label>
          <label className="check"><input type="checkbox" name="lead_capture" checked={!!p.lead_capture} onChange={(e) => set("lead_capture", e.target.checked ? 1 : 0)} /> Proposer aux visiteurs de me laisser leurs coordonnées</label>
          <label className="check"><input type="checkbox" name="is_public" checked={!!p.is_public} onChange={(e) => set("is_public", e.target.checked ? 1 : 0)} /> Profil visible (décochez pour masquer temporairement)</label>
        </div>
        <div className="row between" style={{ marginTop: 16 }}>
          <button className="btn" disabled={pending}>{pending ? "Enregistrement…" : "Enregistrer"}</button>
          <button type="submit" formAction={deleteProfile.bind(null, profile.id)} className="btn btn-ghost btn-sm" style={{ color: "var(--danger)" }}
            onClick={(e) => { if (!confirm("Supprimer ce profil ?")) e.preventDefault(); }}>Supprimer ce profil</button>
        </div>
      </form>

      <aside className="config-preview">
        <p className="muted" style={{ textAlign: "center", fontSize: ".85rem", margin: "0 0 8px" }}>Aperçu sur téléphone</p>
        <div style={{ border: "10px solid #14121f", borderRadius: 36, overflow: "hidden", height: 720, overflowY: "auto", boxShadow: "var(--shadow)" }}>
          <ProfileView profile={preview} preview />
        </div>
      </aside>
    </div>
  );
}
