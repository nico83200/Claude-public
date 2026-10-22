import { notFound } from "next/navigation";
import { requireUser } from "@/lib/auth";
import { get } from "@/lib/db";
import type { Profile } from "@/lib/types";
import { ProfileEditor } from "./ProfileEditor";

export const metadata = { title: "Modifier le profil" };

export default async function EditProfilePage({ params, searchParams }: { params: Promise<{ id: string }>; searchParams: Promise<{ erreur?: string; bienvenue?: string }> }) {
  const { id } = await params;
  const { erreur, bienvenue } = await searchParams;
  const user = await requireUser(`/compte/profils/${id}`);
  const profile = get<Profile>("SELECT * FROM profiles WHERE id = ? AND user_id = ?", id, user.id);
  if (!profile) notFound();
  const card = get<{ id: string }>("SELECT id FROM cards WHERE profile_id = ? AND status = 'active' LIMIT 1", id);
  return (
    <div>
      {erreur === "suppression" && <div className="alert alert-danger" style={{ marginBottom: 16 }}>Impossible de supprimer ce profil : il est utilisé par une carte, ou c’est votre seul profil.</div>}
      {bienvenue && <div className="alert alert-ok" style={{ marginBottom: 16 }}>Votre carte est activée ! Complétez votre profil : c’est ce qui s’affichera quand quelqu’un scannera votre carte.</div>}
      <ProfileEditor profile={profile} publicUrl={card ? `/q/${card.id}` : null} />
    </div>
  );
}
