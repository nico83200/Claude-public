"use client";

import { useState } from "react";
import { IconUserPlus } from "./Icons";
import { LeadForm } from "./LeadForm";

/** Bouton « Ajouter aux contacts » ; s'il est activé, propose aussitôt l'échange de coordonnées (feuille en bas d'écran). */
export function SaveContact({ cardId, ownerFirstName, leadCapture }: { cardId: string; ownerFirstName: string; leadCapture: boolean }) {
  const [sheet, setSheet] = useState(false);
  return (
    <>
      <a className="p-save" href={`/vcard/${cardId}`} onClick={() => leadCapture && setTimeout(() => setSheet(true), 900)}>
        <IconUserPlus /> Ajouter aux contacts
      </a>
      {sheet && (
        <div role="dialog" aria-modal="true" aria-label="Échanger mes coordonnées" className="p-sheet-backdrop" onClick={(e) => e.target === e.currentTarget && setSheet(false)}>
          <div className="p-sheet">
            <div className="p-sheet-title">Et vous ? Échangez vos coordonnées</div>
            <LeadForm cardId={cardId} ownerFirstName={ownerFirstName} startOpen onDone={() => setSheet(false)} />
          </div>
        </div>
      )}
    </>
  );
}
