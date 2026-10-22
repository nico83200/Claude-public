import Link from "next/link";
import { LogoMark } from "@/components/Logo";

export const metadata = { title: "Carte non reconnue", robots: { index: false } };

export default function CarteInvalide() {
  return (
    <div className="center-page">
      <div className="panel">
        <LogoMark size={44} />
        <h1 style={{ fontSize: "1.5rem", marginTop: 16 }}>Carte non reconnue</h1>
        <p className="muted">Cette carte n’a pas pu être authentifiée. Il peut s’agir d’une copie, ou d’un lien recopié ou modifié. Rescannez la carte originale.</p>
        <Link href="/" className="btn btn-secondary">Découvrir Tapikard</Link>
      </div>
    </div>
  );
}
