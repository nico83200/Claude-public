import Link from "next/link";
import { currentUser } from "@/lib/auth";
import { BASIC_UNIT_CENTS, euros } from "@/lib/catalog";
import { Logo } from "./Logo";

export async function SiteHeader() {
  const user = await currentUser();
  return (
    <header className="site-header">
      <div className="container">
        <Logo />
        <nav className="nav" aria-label="Navigation principale">
          <Link href="/carte-basic" className="hide-sm">Carte Basic</Link>
          <Link href="/creer" className="hide-sm">Personnalisée</Link>
          <Link href="/#tarifs" className="hide-sm">Tarifs</Link>
          {user ? <Link href={user.is_admin ? "/admin" : "/compte"}>{user.is_admin ? "Back-office" : "Mon espace"}</Link> : <Link href="/connexion">Connexion</Link>}
          <Link href="/carte-basic" className="btn btn-sm">Commander<span className="hide-xs">· {euros(BASIC_UNIT_CENTS).replace(",00", "")}</span></Link>
        </nav>
      </div>
    </header>
  );
}

export function SiteFooter() {
  return (
    <footer className="site-footer">
      <div className="container">
        <div className="footer-grid">
          <div>
            <Logo tone="light" />
            <p style={{ marginTop: 16, maxWidth: 300 }}>La carte de visite qui se partage d’un geste. Imprimée, encodée et vérifiée en France.</p>
          </div>
          <div>
            <h4>Cartes</h4>
            <ul>
              <li><Link href="/carte-basic">Carte Basic</Link></li>
              <li><Link href="/creer">Carte personnalisée</Link></li>
              <li><Link href="/exemple">Voir un profil exemple</Link></li>
              <li><Link href="/#tarifs">Tarifs</Link></li>
            </ul>
          </div>
          <div>
            <h4>Mon compte</h4>
            <ul>
              <li><Link href="/connexion">Connexion</Link></li>
              <li><Link href="/compte">Mon espace</Link></li>
              <li><Link href="/compte/contacts">Contacts reçus</Link></li>
            </ul>
          </div>
          <div>
            <h4>Tapikard</h4>
            <ul>
              <li><a href="mailto:bonjour@tapikard.com">Nous écrire</a></li>
              <li><Link href="/confidentialite">Confidentialité</Link></li>
            </ul>
          </div>
        </div>
        <div className="footer-bottom">
          <span>© {new Date().getFullYear()} Tapikard</span>
          <span>Puce NFC sécurisée NTAG 424 DNA · Données hébergées en Europe</span>
        </div>
      </div>
    </footer>
  );
}
