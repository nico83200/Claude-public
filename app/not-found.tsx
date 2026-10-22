import Link from "next/link";
import { LogoMark } from "@/components/Logo";

export default function NotFound() {
  return (
    <div className="center-page">
      <div className="panel">
        <LogoMark size={44} />
        <h1 style={{ fontSize: "1.5rem", marginTop: 16 }}>Page introuvable</h1>
        <p className="muted">Ce lien n’existe pas ou plus.</p>
        <Link href="/" className="btn btn-secondary">Retour à l’accueil</Link>
      </div>
    </div>
  );
}
