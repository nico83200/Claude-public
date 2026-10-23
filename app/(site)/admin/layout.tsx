import Link from "next/link";
import { requireAdmin } from "@/lib/auth";

export default async function AdminLayout({ children }: { children: React.ReactNode }) {
  const user = await requireAdmin();
  return (
    <div className="container app-shell">
      <nav className="sidenav" aria-label="Back-office">
        <span className="badge badge-brand" style={{ alignSelf: "flex-start", margin: "0 0 8px 12px" }}>Back-office</span>
        <Link href="/admin">Tableau de bord</Link>
        <Link href="/admin/commandes">Commandes</Link>
        <Link href="/admin/production">Production & encodage</Link>
        <Link href="/admin/stocks">Stocks</Link>
        <Link href="/admin/cartes">Cartes</Link>
        <div className="sep" />
        <Link href="/compte">Mon espace client</Link>
        <form action="/deconnexion" method="post"><button className="btn btn-ghost btn-sm" style={{ paddingLeft: 12 }}>Se déconnecter</button></form>
        <small className="muted" style={{ padding: "4px 12px" }}>{user.email}</small>
      </nav>
      <div>{children}</div>
    </div>
  );
}
