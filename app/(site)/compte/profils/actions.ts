"use server";

import { revalidatePath } from "next/cache";
import { redirect } from "next/navigation";
import { requireUser } from "@/lib/auth";
import { get, run } from "@/lib/db";
import { randomId } from "@/lib/ids";
import { RESEAUX } from "@/lib/reseaux";
import type { Profile, ProfileLink } from "@/lib/types";

export type SaveState = { ok?: boolean; error?: string };

const s = (v: FormDataEntryValue | null, max: number) => (typeof v === "string" ? v.trim().slice(0, max) : "") || null;

async function ownedProfile(id: string) {
  const user = await requireUser();
  const p = get<Profile>("SELECT * FROM profiles WHERE id = ? AND user_id = ?", id, user.id);
  if (!p) throw new Error("Profil introuvable");
  return { user, p };
}

export async function saveProfile(id: string, _prev: SaveState, form: FormData): Promise<SaveState> {
  await ownedProfile(id);
  const name = s(form.get("display_name"), 60);
  if (!name) return { error: "Le nom est obligatoire." };
  const accent = String(form.get("accent") ?? "");
  let links: ProfileLink[] = [];
  try {
    links = (JSON.parse(String(form.get("links") ?? "[]")) as ProfileLink[])
      .filter((l) => l && typeof l.label === "string" && typeof l.url === "string" && l.label.trim() && /^https?:\/\/\S+$/.test(l.url.trim()))
      .filter((l) => !RESEAUX.some((r) => r.modele === l.url.trim())) // modèle d’adresse non complété
      .slice(0, 10)
      .map((l) => ({ label: l.label.trim().slice(0, 40), url: l.url.trim().slice(0, 300) }));
  } catch {
    return { error: "Liens invalides." };
  }
  const avatar = String(form.get("avatar") ?? "");
  if (avatar && !/^data:image\/(png|jpeg);base64,/.test(avatar)) return { error: "Photo invalide." };
  if (avatar.length > 400_000) return { error: "Photo trop lourde." };
  const logo = String(form.get("logo") ?? "");
  if (logo && !/^data:image\/(png|jpeg);base64,/.test(logo)) return { error: "Logo invalide (PNG ou JPEG)." };
  if (logo.length > 400_000) return { error: "Logo trop lourd." };
  const logoFond = ["clair", "couleur", "sombre"].includes(String(form.get("logo_fond"))) ? String(form.get("logo_fond")) : "clair";
  const email = s(form.get("email"), 120);
  if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return { error: "Email invalide." };

  run(
    `UPDATE profiles SET label = ?, display_name = ?, title = ?, company = ?, bio = ?, phone = ?, mobile = ?, email = ?, website = ?, address = ?,
       avatar = ?, logo = ?, logo_fond = ?, links = ?, accent = ?, dark = ?, lead_capture = ?, is_public = ?, updated_at = datetime('now') WHERE id = ?`,
    s(form.get("label"), 30) ?? "Profil", name, s(form.get("title"), 60), s(form.get("company"), 60), s(form.get("bio"), 300),
    s(form.get("phone"), 30), s(form.get("mobile"), 30), email, s(form.get("website"), 120), s(form.get("address"), 160),
    avatar || null, logo || null, logoFond, JSON.stringify(links), /^#[0-9a-fA-F]{6}$/.test(accent) ? accent : "#5b3df5",
    form.get("dark") === "on", form.get("lead_capture") === "on", form.get("is_public") === "on", id,
  );
  revalidatePath("/compte/profils");
  return { ok: true };
}

export async function createProfile() {
  const user = await requireUser();
  const id = randomId(12);
  run("INSERT INTO profiles (id, user_id, label, display_name, email) VALUES (?, ?, 'Nouveau profil', ?, ?)", id, user.id, user.name ?? "", user.email);
  redirect(`/compte/profils/${id}`);
}

export async function deleteProfile(id: string) {
  const { user } = await ownedProfile(id);
  const used = get<{ n: number }>("SELECT COUNT(*) n FROM cards WHERE profile_id = ? AND status IN ('active','shipped','encoded')", id)!.n;
  const count = get<{ n: number }>("SELECT COUNT(*) n FROM profiles WHERE user_id = ?", user.id)!.n;
  if (used > 0 || count <= 1) redirect(`/compte/profils/${id}?erreur=suppression`);
  run("DELETE FROM profiles WHERE id = ?", id);
  redirect("/compte/profils");
}
