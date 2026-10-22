import { Configurator } from "./Configurator";
import { STRIPE_ENABLED } from "@/lib/config";
import { MATERIALS, type MaterialId } from "@/lib/catalog";

export const metadata = { title: "Créer ma carte" };

export default async function CreerPage({ searchParams }: { searchParams: Promise<{ matiere?: string }> }) {
  const { matiere } = await searchParams;
  const initialMaterial = matiere && matiere in MATERIALS ? (matiere as MaterialId) : undefined;
  return (
    <div className="container" style={{ paddingTop: 32 }}>
      <h1>Créez votre carte</h1>
      <p className="muted">Votre aperçu se met à jour en direct. Vous validerez un BAT (bon à tirer) avant la production.</p>
      <Configurator stripe={STRIPE_ENABLED} initialMaterial={initialMaterial} />
    </div>
  );
}
