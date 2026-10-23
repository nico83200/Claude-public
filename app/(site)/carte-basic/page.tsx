import { STRIPE_ENABLED } from "@/lib/config";
import { BASIC_PRIX_REFERENCE_CENTS, BASIC_UNIT_CENTS, QUANTITIES, REMISE_LANCEMENT, euros } from "@/lib/catalog";
import { ruptures } from "@/lib/stock";
import { BasicOrderForm } from "./BasicOrderForm";

export const metadata = {
  title: `Commander votre carte de visite NFC — ${euros(BASIC_UNIT_CENTS)}`,
  description: `Carte de visite NFC Tapikard : ${euros(BASIC_UNIT_CENTS)} au lieu de ${euros(BASIC_PRIX_REFERENCE_CENTS)} (offre de lancement −${REMISE_LANCEMENT * 100} %). Livraison offerte, profil modifiable à vie.`,
};

export default async function CarteBasicPage({ searchParams }: { searchParams: Promise<{ couleur?: string; quantite?: string }> }) {
  const { couleur, quantite } = await searchParams;
  const q = Number(quantite);
  return (
    <div className="container">
      <div className="page-intro">
        <span className="badge-lancement">Offre de lancement · −{REMISE_LANCEMENT * 100} % immédiatement</span>
        <h1 style={{ marginTop: 16 }}>Votre carte <em>Tapikard</em></h1>
        <p className="lead" style={{ maxWidth: 720 }}>
          {euros(BASIC_UNIT_CENTS)} au lieu de {euros(BASIC_PRIX_REFERENCE_CENTS)}, livraison offerte. Puce NFC sécurisée et QR code, en noir mat ou en blanc.
          Pas de maquette à valider : vous complétez votre profil à réception, et vous le modifiez à vie.
        </p>
      </div>
      <BasicOrderForm
        stripe={STRIPE_ENABLED}
        initialVariant={couleur === "blanc" ? "blanc" : "noir"}
        initialQuantity={QUANTITIES.some((x) => x.qty === q) ? q : 1}
        ruptures={ruptures()}
      />
    </div>
  );
}
