import Link from "next/link";
import { notFound } from "next/navigation";
import { LogoMark } from "@/components/Logo";
import { ProfileView } from "@/components/ProfileView";
import { get } from "@/lib/db";
import { recordScan } from "@/lib/scans";
import { isValidScanToken } from "@/lib/scanToken";
import type { Card, Profile } from "@/lib/types";

export const dynamic = "force-dynamic";

export async function generateMetadata({ params }: { params: Promise<{ cardId: string }> }) {
  const { cardId } = await params;
  const p = get<Profile>("SELECT p.* FROM profiles p JOIN cards c ON c.profile_id = p.id WHERE c.id = ? AND c.status = 'active' AND p.is_public = 1", cardId);
  return { title: p ? p.display_name : "Carte Tapikard", robots: { index: false, follow: false } };
}

function Neutral({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="center-page">
      <div className="panel">
        <LogoMark size={44} />
        <h1 style={{ fontSize: "1.5rem", marginTop: 16 }}>{title}</h1>
        {children}
      </div>
    </div>
  );
}

// Profil d'une carte : atteint par le QR code (statique) ou après redirection d'un scan NFC vérifié (/c).
export default async function CardProfilePage({ params, searchParams }: { params: Promise<{ cardId: string }>; searchParams: Promise<{ t?: string; rejeu?: string }> }) {
  const { cardId } = await params;
  const { t, rejeu } = await searchParams;
  if (!/^[A-Za-z0-9]{6,20}$/.test(cardId)) notFound();
  const card = get<Card>("SELECT * FROM cards WHERE id = ?", cardId);
  if (!card) notFound();

  if (card.status === "disabled" || card.status === "lost") {
    return <Neutral title="Carte désactivée"><p className="muted">Cette carte n’est plus active.</p></Neutral>;
  }
  if (card.status !== "active") {
    return (
      <Neutral title="Carte pas encore activée">
        <p className="muted">Vous venez de recevoir cette carte ? Activez-la en quelques secondes pour la relier à votre profil.</p>
        <Link className="btn" href={`/activer?carte=${card.id}`}>Activer ma carte</Link>
      </Neutral>
    );
  }
  const profile = card.profile_id ? get<Profile>("SELECT * FROM profiles WHERE id = ?", card.profile_id) : undefined;
  if (!profile || !profile.is_public) {
    return <Neutral title="Profil indisponible"><p className="muted">Le porteur de cette carte n’a pas encore publié son profil.</p></Neutral>;
  }

  const verified = isValidScanToken(card.id, t);
  // Les scans NFC sont comptés dans /c ; ici on ne compte que les ouvertures par QR code.
  if (!verified && !rejeu) await recordScan(card.id, profile.id, "qr", false);
  return <ProfileView profile={profile} cardId={card.id} verification={verified ? "verified" : rejeu ? "replay" : null} />;
}
