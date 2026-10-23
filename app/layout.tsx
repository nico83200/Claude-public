import type { Metadata, Viewport } from "next";
import "@fontsource-variable/inter";
import "@fontsource/instrument-serif/latin-400.css";
import "@fontsource/instrument-serif/latin-400-italic.css";
import "./globals.css";
import { APP_URL } from "@/lib/config";

export const metadata: Metadata = {
  metadataBase: new URL(APP_URL),
  title: { default: "Tapikard — La carte de visite NFC", template: "%s · Tapikard" },
  description: "Cartes de visite NFC à puce sécurisée : un geste suffit pour partager vos coordonnées. Carte Basic prête à l’emploi dès 19 €, ou entièrement personnalisée. Modifiable à vie.",
  openGraph: { siteName: "Tapikard", locale: "fr_FR", type: "website" },
};

export const viewport: Viewport = { width: "device-width", initialScale: 1, themeColor: "#121117" };

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="fr">
      <body>{children}</body>
    </html>
  );
}
