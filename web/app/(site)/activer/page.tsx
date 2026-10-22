import { requireUser } from "@/lib/auth";
import { get } from "@/lib/db";
import type { Card } from "@/lib/types";
import { ActivateForm } from "./ActivateForm";

export const metadata = { title: "Activer ma carte" };

export default async function ActiverPage({ searchParams }: { searchParams: Promise<{ carte?: string }> }) {
  const { carte = "" } = await searchParams;
  const user = await requireUser(`/activer?carte=${encodeURIComponent(carte)}`);
  const card = carte ? get<Card>("SELECT * FROM cards WHERE id = ?", carte) : undefined;
  const needsCode = !card || card.user_id !== user.id;
  return (
    <div className="center-page">
      <div className="panel">
        <h1 style={{ fontSize: "1.6rem" }}>Activer ma carte</h1>
        <p className="muted">
          {needsCode ? "Saisissez le code d’activation pour relier cette carte à votre compte." : "Cette carte fait partie de votre commande : un clic suffit."}
        </p>
        <ActivateForm cardId={carte} needsCode={needsCode} />
      </div>
    </div>
  );
}
