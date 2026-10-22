import Link from "next/link";
import { currentUser } from "@/lib/auth";
import { Logo } from "./Logo";

export async function SiteHeader() {
  const user = await currentUser();
  return (
    <header className="site-header">
      <div className="container">
        <Logo />
        <nav className="nav">
          <Link href="/#fonctionnement" className="hide-sm">Comment ça marche</Link>
          <Link href="/#tarifs" className="hide-sm">Tarifs</Link>
          {user ? <Link href={user.is_admin ? "/admin" : "/compte"}>{user.is_admin ? "Back-office" : "Mon espace"}</Link> : <Link href="/connexion">Connexion</Link>}
          <Link href="/creer" className="btn btn-sm">Créer ma carte</Link>
        </nav>
      </div>
    </header>
  );
}

export function SiteFooter() {
  return (
    <footer className="site-footer">
      <div className="container">
        <span>© {new Date().getFullYear()} Tapikard — Cartes de visite NFC fabriquées et encodées en France</span>
        <span className="row">
          <Link href="/confidentialite">Confidentialité</Link>
          <Link href="/creer">Commander</Link>
          <a href="mailto:bonjour@tapikard.com">Contact</a>
        </span>
      </div>
    </footer>
  );
}
