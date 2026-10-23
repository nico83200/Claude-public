import { redirect } from "next/navigation";
import { Configurator } from "./Configurator";
import { OFFRE_PERSONNALISEE, STRIPE_ENABLED } from "@/lib/config";
import { MATERIALS, personnaliseeCommandable, type MaterialId } from "@/lib/catalog";
import { ruptures } from "@/lib/stock";

export const metadata = { title: "Créer ma carte" };

export default async function CreerPage({ searchParams }: { searchParams: Promise<{ matiere?: string }> }) {
  if (!OFFRE_PERSONNALISEE) redirect("/carte-basic");
  const { matiere } = await searchParams;
  const initialMaterial = matiere && matiere in MATERIALS ? (matiere as MaterialId) : undefined;
  const r = ruptures();
  return (
    <div className="container">
      <div className="page-intro">
        <span className="eyebrow">Carte personnalisée</span>
        <h1>Créez votre carte, <em>à votre image.</em></h1>
        <p className="lead">L’aperçu se met à jour en direct. Vous validerez un BAT (bon à tirer) avant l’impression.</p>
      </div>
      {!personnaliseeCommandable(r) && (
        <div className="alert alert-warn" style={{ marginBottom: 8 }}>
          La carte personnalisée est momentanément en rupture de stock. La <a href="/carte-basic">carte Basic</a> reste disponible.
        </div>
      )}
      <Configurator stripe={STRIPE_ENABLED} initialMaterial={initialMaterial} ruptures={r} />
    </div>
  );
}
