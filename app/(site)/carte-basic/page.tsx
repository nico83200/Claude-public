import Link from "next/link";
import { STRIPE_ENABLED } from "@/lib/config";
import { BASIC_UNIT_CENTS, euros } from "@/lib/catalog";
import { ruptures } from "@/lib/stock";
import { BasicOrderForm } from "./BasicOrderForm";

export const metadata = { title: "Carte Basic — prête à l’emploi" };

export default async function CarteBasicPage({ searchParams }: { searchParams: Promise<{ couleur?: string }> }) {
  const { couleur } = await searchParams;
  return (
    <div className="container">
      <div className="page-intro">
        <span className="eyebrow">Prête à l’emploi · {euros(BASIC_UNIT_CENTS).replace(",00", "")} la carte</span>
        <h1>La carte <em>Basic</em></h1>
        <p className="lead" style={{ maxWidth: 720 }}>
          La même puce NFC sécurisée et le même QR code que nos cartes sur mesure, avec le design Tapikard, en noir mat ou en blanc.
          Pas de BAT à valider : vous complétez votre profil à réception, et vous le modifiez à vie.
          Envie de votre logo et de vos couleurs ? <Link href="/creer">Créez une carte personnalisée</Link>.
        </p>
      </div>
      <BasicOrderForm stripe={STRIPE_ENABLED} initialVariant={couleur === "blanc" ? "blanc" : "noir"} ruptures={ruptures()} />
    </div>
  );
}
