"use client";

import { useEffect, useState } from "react";
import { IconUserPlus } from "./Icons";
import { LeadForm } from "./LeadForm";

export type ContactPublic = {
  nom: string; mobile: string | null; telephone: string | null; email: string | null; entreprise: string | null;
  fonction: string | null; adresse: string | null; site: string | null;
};

/**
 * Lien qui ouvre directement la fiche « Nouveau contact » d’Android, pré-remplie (intent INSERT des Contacts),
 * sans fichier à télécharger. Si aucune application ne répond, Chrome suit le lien de secours (la vCard).
 */
function intentAndroid(c: ContactPublic, secours: string): string {
  const e = encodeURIComponent;
  const extras: [string, string | null | undefined][] = [
    ["S.name", c.nom],
    ["S.phone", c.mobile || c.telephone],
    ["i.phone_type", c.mobile ? "2" : c.telephone ? "3" : null], // 2 : mobile, 3 : travail
    ["S.secondary_phone", c.mobile && c.telephone ? c.telephone : null],
    ["i.secondary_phone_type", c.mobile && c.telephone ? "3" : null],
    ["S.email", c.email],
    ["i.email_type", c.email ? "2" : null],
    ["S.company", c.entreprise],
    ["S.job_title", c.fonction],
    ["S.postal", c.adresse],
    ["S.notes", c.site ? `Site : ${c.site}` : null],
    ["S.browser_fallback_url", secours],
  ];
  const corps = extras.filter(([, v]) => v).map(([k, v]) => `${k}=${e(v!)}`).join(";");
  return `intent:#Intent;action=android.intent.action.INSERT;type=vnd.android.cursor.dir/raw_contact;${corps};end`;
}

/**
 * Bouton « Ajouter aux contacts » : sur Android, la fiche de contact du téléphone s’ouvre pré-remplie ;
 * sur iPhone, la vCard s’affiche dans la fiche native « Nouveau contact » (avec la photo).
 * S’il est activé, l’échange de coordonnées est proposé juste après (feuille en bas d’écran).
 */
export function SaveContact({ cardId, ownerFirstName, leadCapture, contact }: { cardId: string; ownerFirstName: string; leadCapture: boolean; contact?: ContactPublic }) {
  const [sheet, setSheet] = useState(false);
  const vcard = `/vcard/${cardId}`;
  const [href, setHref] = useState(vcard);
  useEffect(() => {
    if (contact && /Android/i.test(navigator.userAgent)) setHref(intentAndroid(contact, `${location.origin}${vcard}`));
  }, [contact, vcard]);
  return (
    <>
      <a
        className="p-save"
        href={href}
        onClick={() => {
          // La vCard compte l’ajout elle-même ; avec l’intent Android, on le signale au serveur.
          if (href !== vcard) navigator.sendBeacon?.("/api/evenements", new Blob([JSON.stringify({ action: "vcf", cardId })], { type: "application/json" }));
          if (leadCapture) setTimeout(() => setSheet(true), 900);
        }}
      >
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
