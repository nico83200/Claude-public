"use client";

import "./globals.css";
import { ErreurPage } from "@/components/ErreurPage";

export default function GlobalError(props: { error: Error & { digest?: string }; reset: () => void }) {
  return (
    <html lang="fr">
      <body>
        <ErreurPage {...props} />
      </body>
    </html>
  );
}
