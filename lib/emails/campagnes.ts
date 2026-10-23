// Emails de campagne (promotion), à importer dans un outil d'emailing (Brevo, Mailchimp…) qui gère
// les listes, le consentement et la désinscription. Aucun envoi en masse depuis le site.
import { BASIC_UNIT_CENTS, MATERIALS, euros } from "../catalog";
import { COULEURS as C, SANS, bouton, coches, document, encart, etapes, h, image, lien, para, section, surtitre, titre, visuel } from "./gabarit";
import type { Email } from "./messages";

const prix = (c: number) => euros(c).replace(",00", "");

/** Balise de désinscription selon l'outil d'emailing. */
export const OUTILS = {
  brevo: { label: "Brevo", balise: "{{ unsubscribe }}" },
  mailchimp: { label: "Mailchimp", balise: "*|UNSUB|*" },
  mailjet: { label: "Mailjet", balise: "[[UNSUB_LINK_FR]]" },
  autre: { label: "Autre (à remplacer)", balise: "#DESINSCRIPTION" },
} as const;
export type Outil = keyof typeof OUTILS;

const logoBlanc = `<a href="${lien("/")}" target="_blank"><img src="${image("logo-blanc.png")}" width="148" height="36" alt="Tapikard" style="display:block;border:0;width:148px;height:36px;margin:0 0 34px;"></a>`;

/** Trois colonnes (empilées sur mobile). */
function colonnes(items: [string, string][]): string {
  return `<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr>${items
    .map(([t, d], i) => `<td class="col" width="33%" valign="top" style="padding:0 ${i < items.length - 1 ? 14 : 0}px 0 0;font-family:${SANS};">
<div style="font-family:Georgia,'Times New Roman',serif;font-size:28px;line-height:1;color:${C.violet};margin-bottom:10px;">0${i + 1}</div>
<div style="font-size:15px;font-weight:700;color:${C.encre};margin-bottom:6px;">${h(t)}</div>
<div style="font-size:14px;line-height:1.55;color:${C.gris};">${d}</div></td>`)
    .join("")}</tr></table>`;
}

function offreBasic(): string {
  return encart(`<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr>
<td class="col" valign="middle" style="font-family:${SANS};">
<div style="font-size:12px;font-weight:600;letter-spacing:2px;text-transform:uppercase;color:${C.gris};margin-bottom:6px;">Carte Basic · prête à l’emploi</div>
<div style="font-family:Georgia,'Times New Roman',serif;font-size:44px;line-height:1;color:${C.encre};">${prix(BASIC_UNIT_CENTS)}</div>
<div style="font-size:14px;color:${C.gris};margin-top:6px;">la carte · livraison offerte · noir mat ou blanc</div></td>
<td class="col" align="right" valign="middle">${bouton("Commander", lien("/carte-basic"))}</td></tr></table>`);
}

export function campagneDecouverte(outil: Outil = "brevo"): Email {
  const sujet = "Et si votre carte de visite s’enregistrait toute seule ?";
  return {
    sujet,
    html: document({
      sujet,
      preheader: "Une carte NFC élégante : un geste, et vos coordonnées sont dans le téléphone de votre interlocuteur.",
      desinscription: OUTILS[outil].balise,
      sections: [
        section(
          logoBlanc +
            surtitre("La carte de visite NFC", true) +
            titre("Votre carte de visite,", "d’un simple geste.", true) +
            para("Approchez votre carte d’un téléphone : vos coordonnées s’affichent et s’enregistrent. Sans application, sur iPhone comme sur Android.", { sombre: true }) +
            visuel("cartes-hero.png", "Cartes de visite NFC Tapikard, noir mat et blanc", 504) +
            `<div style="height:26px;"></div>` +
            bouton("Découvrir Tapikard", lien("/"), "clair"),
          { fond: "nuit", haut: 44, bas: 40 },
        ),
        section(
          surtitre("Pourquoi Tapikard") +
            colonnes([
              ["Un geste suffit", "Votre contact est enregistré en une seconde, avec votre photo et vos liens."],
              ["Modifiable à vie", "Nouveau poste, nouveau numéro : votre carte reste à jour, sans réimpression."],
              ["Infalsifiable", "Puce NTAG 424 DNA : chaque scan est signé, une copie est détectée."],
            ]),
          { haut: 40, bas: 12 },
        ),
        section(
          titre("Ne perdez plus", "aucun contact.") +
            para("Votre interlocuteur enregistre votre fiche… et dans la foulée, il vous laisse la sienne. Vous la recevez aussitôt par email, prête à relancer.") +
            coches(["Formulaire pré-rempli par son téléphone", "Fiche contact jointe à votre notification", "Suivi, notes et export Excel de vos contacts", "Synchronisation CRM via Zapier ou Make"]),
          { haut: 28, bas: 8 },
        ),
        section(offreBasic() + para(`Envie de votre logo et de vos couleurs ? Carte personnalisée en PVC, bambou ou métal, dès ${prix(MATERIALS.pvc_blanc.unitCents)}. <a href="${lien("/creer")}" style="color:${C.violet};">Créer la mienne</a>`, { petit: true, marge: 0 }), { haut: 8, bas: 34 }),
      ],
    }),
  };
}

export function campagneOffreBasic(outil: Outil = "brevo"): Email {
  const sujet = `La carte Basic : votre carte de visite NFC à ${prix(BASIC_UNIT_CENTS)}`;
  return {
    sujet,
    html: document({
      sujet,
      preheader: "Prête à l’emploi, noir mat ou blanc, livrée gratuitement. Votre profil reste modifiable à vie.",
      desinscription: OUTILS[outil].balise,
      sections: [
        section(
          surtitre("Nouveau · Carte Basic") +
            titre("Tout l’essentiel,", "rien de superflu.") +
            para("La même puce NFC sécurisée et le même QR code que nos cartes sur mesure, avec le design Tapikard. Pas de maquette à valider : vous commandez, vous recevez, vous scannez pour activer.") +
            visuel("carte-basic-duo.png", "Carte Basic Tapikard, recto et verso", 504) +
            `<div style="height:22px;"></div>` +
            coches([
              "Carte PVC noir mat ou blanc, finition mate",
              "Puce NFC NTAG 424 DNA infalsifiable et QR code de secours",
              "Profil en ligne illimité : photo, coordonnées, liens, rendez-vous",
              "Échange de coordonnées et statistiques de scans",
            ]) +
            offreBasic(),
          { bas: 10 },
        ),
        section(
          surtitre("Comment ça marche") +
            etapes([
              ["Commandez", "Noir mat ou blanc, à l’unité ou par lot (jusqu’à −40 %)."],
              ["Activez", "À réception, scannez votre carte et complétez votre profil."],
              ["Partagez", "Un geste, et votre contact est enregistré. Le leur vous revient par email."],
            ]),
          { haut: 10, bas: 30 },
        ),
      ],
    }),
  };
}

export function campagneConseils(outil: Outil = "brevo"): Email {
  const sujet = "5 réflexes pour que votre carte Tapikard vous rapporte des contacts";
  return {
    sujet,
    html: document({
      sujet,
      preheader: "Photo, liens, échange de coordonnées, relance : les réglages qui font la différence.",
      desinscription: OUTILS[outil].balise,
      sections: [
        section(
          surtitre("Conseils") +
            titre("Cinq réflexes", "qui font la différence.") +
            para("Votre carte est votre première impression. Voici ce que font les profils qui reçoivent le plus de contacts en retour :") +
            etapes([
              ["Une photo nette et souriante", "On retient un visage bien mieux qu’un nom : c’est le réglage le plus rentable."],
              ["Trois liens maximum", "LinkedIn, votre site et la prise de rendez-vous suffisent. Au-delà, on se perd."],
              ["L’échange de coordonnées activé", "Vos interlocuteurs vous laissent les leurs juste après avoir enregistré les vôtres."],
              ["Une relance sous 48 heures", "Vous recevez chaque contact par email : notez le contexte et relancez vite."],
              ["Un profil pour chaque usage", "Pro et perso, ou par activité : changez celui d’une carte en un clic, sans réimprimer."],
            ]) +
            bouton("Améliorer mon profil", lien("/compte/profils")),
          { bas: 28 },
        ),
        section(
          `<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr>
<td class="col" width="56%" valign="middle" style="padding-right:18px;">${surtitre("Pour votre équipe")}${para("Des cartes pour vos collaborateurs, à vos couleurs, avec une gestion centralisée.", { marge: 12 })}${bouton("Personnaliser des cartes", lien("/creer"), "contour")}</td>
<td class="col" width="44%" valign="middle">${visuel("cartes-perso.png", "Cartes personnalisées Tapikard", 230)}</td>
</tr></table>`,
          { fond: "ivoire", haut: 28, bas: 28 },
        ),
      ],
    }),
  };
}
