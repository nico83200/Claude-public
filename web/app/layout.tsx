import type { Metadata, Viewport } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: { default: "Tapikard — La carte de visite NFC personnalisable", template: "%s · Tapikard" },
  description: "Cartes de visite imprimées avec puce NFC sécurisée : un geste suffit pour partager vos coordonnées. Modifiables à vie, sans réimpression.",
};

export const viewport: Viewport = { width: "device-width", initialScale: 1, themeColor: "#5b3df5" };

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="fr">
      <body>{children}</body>
    </html>
  );
}
