import Link from "next/link";
import { requireUser } from "@/lib/auth";

export default async function CompteLayout({ children }: { children: React.ReactNode }) {
  const user = await requireUser("/compte");
  return (
    <div className="container app-shell">
      <nav className="sidenav" aria-label="Mon espace">
        <Link href="/compte">Aperçu</Link>
        <Link href="/compte/profils">Mes profils</Link>
        <Link href="/compte/cartes">Mes cartes</Link>
        <Link href="/compte/statistiques">Statistiques</Link>
        <Link href="/compte/contacts">Contacts reçus</Link>
        <Link href="/compte/commandes">Commandes</Link>
        <div className="sep" />
        {!!user.is_admin && <Link href="/admin">Back-office</Link>}
        <Link href="/creer">Commander des cartes</Link>
        <form action="/deconnexion" method="post"><button className="btn btn-ghost btn-sm" style={{ paddingLeft: 12 }}>Se déconnecter</button></form>
        <small className="muted" style={{ padding: "4px 12px" }}>{user.email}</small>
      </nav>
      <div>{children}</div>
    </div>
  );
}
