import Link from "next/link";
import { STRIPE_ENABLED } from "@/lib/config";
import { BASIC_UNIT_CENTS, euros } from "@/lib/catalog";
import { BasicOrderForm } from "./BasicOrderForm";

export const metadata = { title: "Carte Basic — prête à l’emploi" };

export default function CarteBasicPage() {
  return (
    <div className="container" style={{ paddingTop: 32 }}>
      <span className="badge badge-brand">Prête à l’emploi · {euros(BASIC_UNIT_CENTS)}</span>
      <h1 style={{ marginTop: 12 }}>Carte Basic</h1>
      <p className="muted" style={{ maxWidth: 720 }}>
        La même puce NFC sécurisée et le même QR code que nos cartes personnalisées, avec un design Tapikard sobre.
        Pas de BAT à valider : expédiée plus vite. Vous complétez votre profil en ligne à réception, et vous le modifiez à vie.
        Envie de votre logo et de vos couleurs ? <Link href="/creer">Créez une carte personnalisée</Link>.
      </p>
      <BasicOrderForm stripe={STRIPE_ENABLED} />
    </div>
  );
}
