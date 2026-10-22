import { LoginForm } from "./LoginForm";
import { IS_PROD } from "@/lib/config";

export const metadata = { title: "Connexion" };

export default async function ConnexionPage({ searchParams }: { searchParams: Promise<{ next?: string; erreur?: string }> }) {
  const { next = "/compte", erreur } = await searchParams;
  return (
    <div className="center-page">
      <div className="panel">
        <h1 style={{ fontSize: "1.6rem" }}>Connexion</h1>
        <p className="muted">Pas de mot de passe : nous vous envoyons un lien sécurisé par email.</p>
        {erreur && <div className="alert alert-danger" style={{ marginBottom: 14 }}>Ce lien est invalide ou a expiré. Demandez-en un nouveau.</div>}
        <LoginForm next={next} />
        {!IS_PROD && (
          <p className="muted" style={{ fontSize: ".85rem", marginTop: 18 }}>
            Démo : <code>demo@tapikard.com</code> (client) ou <code>admin@tapikard.com</code> (back-office).
          </p>
        )}
      </div>
    </div>
  );
}
