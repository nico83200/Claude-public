import Link from "next/link";
import { Logo } from "@/components/Logo";
import { currentUser } from "@/lib/auth";
import { basicDesign, BASIC_VARIANTS, MATERIALS, TEMPLATES, type MaterialId, type TemplateId } from "@/lib/catalog";
import { all } from "@/lib/db";
import { clesServeur } from "@/lib/encodeurServeur";
import { EncodageMobile, type LotMobile } from "./EncodageMobile";

export const metadata = { title: "Encodage mobile", robots: { index: false } };
export const dynamic = "force-dynamic";

export default async function EncodagePage() {
  const user = await currentUser();
  const entete = (
    <div style={{ padding: "18px 18px 8px", display: "flex", justifyContent: "space-between", alignItems: "center" }}>
      <Logo size={28} href={null} />
      {user && <span className="badge">{user.email}</span>}
    </div>
  );
  if (!user || !user.is_admin) {
    return (
      <main>
        {entete}
        <div className="container" style={{ paddingTop: 40, textAlign: "center" }}>
          <h1 style={{ fontSize: "2rem" }}>Encodage des cartes</h1>
          <p className="muted">{user ? "Cette page est réservée aux administrateurs." : "Connectez-vous avec votre adresse d’administrateur. Vous recevrez un code à 6 chiffres par email."}</p>
          {!user && <Link className="btn btn-lg" href="/connexion?next=/encodage">Me connecter</Link>}
        </div>
      </main>
    );
  }

  const lignes = all<{ batch_id: string; note: string | null; created_at: string; id: string; variant: string | null; design: string | null }>(
    `SELECT c.batch_id, b.note, b.created_at, c.id, c.variant, o.design FROM cards c
     JOIN batches b ON b.id = c.batch_id LEFT JOIN orders o ON o.id = c.order_id
     WHERE c.status = 'provisioned' ORDER BY b.created_at DESC, c.order_id, c.id`,
  );
  const lots = new Map<string, LotMobile>();
  for (const l of lignes) {
    const d = l.design ? JSON.parse(l.design) : basicDesign(l.variant === "blanc" ? "blanc" : "noir");
    const basic = d.template === "basic";
    const lot = lots.get(l.batch_id) ?? { id: l.batch_id, note: l.note, cartes: [] };
    lot.cartes.push({
      id: l.id,
      titre: basic ? `Carte Basic · ${BASIC_VARIANTS[d.material === "pvc_blanc" ? "blanc" : "noir"].label}` : d.name || "Carte personnalisée",
      detail: basic ? (l.design ? "Commande" : "Stock") : `${MATERIALS[d.material as MaterialId]?.label ?? d.material} · ${TEMPLATES[d.template as TemplateId]?.label ?? d.template}`,
    });
    lots.set(l.batch_id, lot);
  }

  return (
    <main>
      {entete}
      <EncodageMobile lots={[...lots.values()]} clesOk={clesServeur() !== null} />
    </main>
  );
}
