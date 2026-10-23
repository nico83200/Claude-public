"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { BASIC_PRIX_REFERENCE_CENTS, BASIC_UNIT_CENTS, euros } from "@/lib/catalog";

/** Barre d'achat fixée en bas de l'écran sur mobile, une fois le haut de page dépassé. */
export function BarreAchat() {
  const [visible, setVisible] = useState(false);
  useEffect(() => {
    const maj = () => setVisible(window.scrollY > 560);
    maj();
    window.addEventListener("scroll", maj, { passive: true });
    return () => window.removeEventListener("scroll", maj);
  }, []);
  return (
    <div className={`barre-achat${visible ? " visible" : ""}`} aria-hidden={!visible}>
      <div>
        <s>{euros(BASIC_PRIX_REFERENCE_CENTS)}</s> <strong>{euros(BASIC_UNIT_CENTS)}</strong>
        <small>Livraison offerte</small>
      </div>
      <Link href="/carte-basic" className="btn" tabIndex={visible ? 0 : -1}>Commander</Link>
    </div>
  );
}
