import Link from "next/link";
import { ProfileView } from "@/components/ProfileView";
import { DEMO_PROFILE } from "@/lib/demoProfile";

export const metadata = { title: "Profil d’exemple", robots: { index: false } };

// Ce que voit la personne qui scanne une carte Tapikard (profil fictif, rien n'est enregistré).
export default function ExemplePage() {
  return (
    <>
      <div style={{ position: "sticky", top: 0, zIndex: 10, background: "#14121f", color: "#fff", padding: "10px 16px", fontSize: ".9rem", textAlign: "center" }}>
        Profil d’exemple : voici ce qui s’affiche quand on scanne une carte.{" "}
        <Link href="/creer" style={{ color: "#c7bcff", fontWeight: 700 }}>Créer le mien →</Link>
      </div>
      <ProfileView profile={DEMO_PROFILE} vcardHref="/exemple/vcard" preview />
    </>
  );
}
