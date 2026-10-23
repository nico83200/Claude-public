// Emails automatiques envoyés par le site.
import { BASIC_VARIANTS, FINISHES, MATERIALS, euros, type CardDesign } from "../catalog";
import type { Lead, Order, Profile } from "../types";
import { bouton, coches, document, encart, etapes, h, lien, para, recap, section, surtitre, titre, visuel } from "./gabarit";

export type Email = { sujet: string; html: string };

const prenom = (nom: string | null | undefined) => (nom ?? "").trim().split(/\s+/)[0] || "";
const bonjour = (nom?: string | null) => (prenom(nom) ? `Bonjour ${h(prenom(nom))},` : "Bonjour,");

function produit(o: Pick<Order, "product" | "material" | "finish" | "quantity">, d: CardDesign): string {
  if (o.product === "basic") {
    const v = d.material === "pvc_blanc" ? "blanc" : "noir";
    return `Carte Basic · ${BASIC_VARIANTS[v].label}`;
  }
  return `${MATERIALS[d.material]?.label ?? o.material} · ${FINISHES[d.finish]?.label ?? o.finish}`;
}

/** Lien de connexion (sans mot de passe). */
export function emailConnexion(lienConnexion: string, code: string, nom?: string | null): Email {
  const sujet = "Votre lien de connexion Tapikard";
  return {
    sujet,
    html: document({
      sujet,
      preheader: `Code de connexion : ${code}. Ou un clic pour accéder à votre espace (20 minutes).`,
      raison: "Vous recevez cet email car une connexion à votre espace Tapikard a été demandée.",
      sections: [
        section(
          surtitre("Connexion sécurisée") +
            titre("Votre lien", "de connexion") +
            para(`${bonjour(nom)} voici votre accès à votre espace Tapikard. Pas de mot de passe à retenir : ce lien vous connecte en un clic.`) +
            bouton("Me connecter à mon espace", lienConnexion) +
            encart(`<p style="margin:0 0 6px;font-family:Inter,Helvetica,Arial,sans-serif;font-size:13px;color:#7a7682;">Sur un autre appareil (ex. l’application Tapikard Encodeur), saisissez ce code :</p>
<p style="margin:0;font-family:'SF Mono',Menlo,Consolas,monospace;font-size:30px;font-weight:700;letter-spacing:6px;color:#121117;">${h(code.slice(0, 3))} ${h(code.slice(3))}</p>`) +
            para("Lien et code personnels, valables <strong>20 minutes</strong>, utilisables une seule fois.", { petit: true, marge: 0 }),
          { bas: 28 },
        ),
        section(
          para("Vous n’êtes pas à l’origine de cette demande ? Ignorez simplement cet email : personne ne peut accéder à votre compte sans ce lien.", { petit: true, marge: 0 }),
          { fond: "ivoire", haut: 22, bas: 22 },
        ),
      ],
    }),
  };
}

/** Remboursement effectué (total ou partiel). */
export function emailRemboursement(o: Order, montantCents: number, total: boolean, nom?: string | null): Email {
  const sujet = total ? "Votre commande Tapikard a été remboursée" : "Remboursement de votre commande Tapikard";
  return {
    sujet,
    html: document({
      sujet,
      preheader: `${euros(montantCents)} vous ont été remboursés. Le crédit apparaît sous 5 à 10 jours ouvrés.`,
      raison: "Vous recevez cet email suite à votre commande sur Tapikard.",
      sections: [
        section(
          surtitre("Remboursement") +
            titre("C’est fait,", "votre remboursement est parti.") +
            para(`${bonjour(nom)} nous avons remboursé <strong>${euros(montantCents)}</strong>${total ? " : votre commande est entièrement remboursée." : " sur votre commande."} Le crédit apparaît sur votre moyen de paiement sous 5 à 10 jours ouvrés, selon votre banque.`) +
            recap([["Commande", `<span style="font-family:monospace;font-size:13px;">${h(o.id)}</span>`], ["Montant remboursé", `<strong>${euros(montantCents)}</strong>`]]) +
            para("Une question ? Répondez simplement à cet email, nous vous répondons rapidement."),
          { bas: 28 },
        ),
      ],
    }),
  };
}

/** Commande payée : BAT à valider (personnalisée) ou mise en préparation (Basic). */
export function emailCommandeConfirmee(o: Order, d: CardDesign, nom?: string | null, codeEntourage?: string | null): Email {
  const basic = o.product === "basic";
  const sujet = basic ? "Merci ! Votre carte Basic est en préparation" : "Merci ! Validez le BAT de votre carte";
  const suivi = lien(`/commande/${o.id}`);
  return {
    sujet,
    html: document({
      sujet,
      preheader: basic ? "Votre commande est confirmée : impression, encodage et vérification de vos cartes." : "Dernière étape avant l’impression : vérifiez et validez votre BAT.",
      raison: "Vous recevez cet email suite à votre commande sur Tapikard.",
      sections: [
        section(
          surtitre("Commande confirmée") +
            titre("Merci pour", "votre confiance.") +
            para(
              `${bonjour(nom)} votre commande est bien enregistrée. ${
                basic
                  ? "Vos cartes partent maintenant à l’atelier : nous les imprimons, encodons et vérifions une à une avant de les expédier."
                  : "Avant d’imprimer, nous avons besoin de votre feu vert : vérifiez l’orthographe, les numéros et les couleurs sur le BAT (bon à tirer)."
              }`,
            ) +
            bouton(basic ? "Suivre ma commande" : "Vérifier et valider mon BAT", suivi) +
            recap(
              [
                ["Commande", `<span style="font-family:monospace;font-size:13px;">${h(o.id)}</span>`],
                ["Produit", h(produit(o, d))],
                ["Quantité", `${o.quantity} carte${o.quantity > 1 ? "s" : ""}`],
                ["Puce", "NFC sécurisée NTAG 424 DNA"],
                ["Livraison", "Offerte"],
                ...(o.remise_cents > 0 ? [["Code " + h(o.code_promo ?? ""), `−${euros(o.remise_cents)}`] as [string, string]] : []),
              ],
              ["Total TTC", euros(o.amount_cents)],
            ),
          { bas: 8 },
        ),
        section(
          surtitre("La suite") +
            etapes(
              basic
                ? [
                    ["Impression et encodage", "Chaque puce reçoit sa propre clé de sécurité, puis est testée comme le ferait un téléphone."],
                    ["Expédition", "Vous recevez un email avec le numéro de suivi."],
                    ["Activation", "À réception, scannez votre carte : votre profil est prêt à compléter."],
                  ]
                : [
                    ["Validation du BAT", "Vous vérifiez la maquette et nous donnez votre accord en un clic."],
                    ["Impression et encodage", "Chaque puce reçoit sa propre clé de sécurité, puis est testée une à une."],
                    ["Expédition et activation", "Suivi par email, puis activation en scannant votre carte."],
                  ],
            ),
          { haut: 8, bas: codeEntourage ? 8 : 28 },
        ),
        codeEntourage
          ? section(
              encart(
                surtitre("Pour votre entourage") +
                  para(`Faites profiter vos proches de <strong>25 % de remise supplémentaire</strong> sur leur carte Tapikard avec votre code personnel :`) +
                  `<div style="font-family:monospace;font-size:26px;font-weight:700;letter-spacing:3px;color:#121117;margin:6px 0 14px;">${h(codeEntourage)}</div>` +
                  bouton("Partager le lien", lien(`/carte-basic?code=${encodeURIComponent(codeEntourage)}`), "contour"),
              ),
              { haut: 8, bas: 28 },
            )
          : false,
      ],
    }),
  };
}

/** Commande expédiée. */
export function emailCommandeExpediee(o: Order, tracking: string | null, nom?: string | null): Email {
  const sujet = "Vos cartes Tapikard sont en route";
  return {
    sujet,
    html: document({
      sujet,
      preheader: tracking ? `Numéro de suivi : ${tracking}. Préparez votre profil en attendant.` : "Vos cartes ont quitté l’atelier. Préparez votre profil en attendant.",
      raison: "Vous recevez cet email suite à votre commande sur Tapikard.",
      sections: [
        section(
          surtitre("Expédiée") +
            titre("Vos cartes", "sont en route.") +
            para(`${bonjour(nom)} vos ${o.quantity > 1 ? `${o.quantity} cartes ont` : "carte a"} quitté l’atelier, imprimée${o.quantity > 1 ? "s" : ""}, encodée${o.quantity > 1 ? "s" : ""} et vérifiée${o.quantity > 1 ? "s" : ""} une à une.`) +
            (tracking ? recap([["Numéro de suivi", `<strong>${h(tracking)}</strong>`], ["Commande", `<span style="font-family:monospace;font-size:13px;">${h(o.id)}</span>`]]) : "") +
            bouton("Préparer mon profil dès maintenant", lien("/compte/profils")),
          { bas: 8 },
        ),
        section(
          surtitre("À réception") +
            etapes([
              ["Scannez votre carte", "Approchez-la de votre téléphone : la page d’activation s’ouvre."],
              ["Activez-la", "Connectez-vous et confirmez : la carte est reliée à votre profil."],
              ["Faites un essai", "Approchez-la d’un autre téléphone : vos coordonnées s’affichent avec le badge « Carte authentique »."],
            ]),
          { haut: 8, bas: 28 },
        ),
      ],
    }),
  };
}

/** Carte activée : conseils pour un profil réussi. */
export function emailCarteActivee(profileId: string, nom?: string | null): Email {
  const sujet = "Votre carte Tapikard est active";
  return {
    sujet,
    html: document({
      sujet,
      preheader: "Quatre réglages de deux minutes pour un profil qui donne envie de vous rappeler.",
      raison: "Vous recevez cet email car vous venez d’activer une carte Tapikard.",
      sections: [
        section(
          surtitre("Carte activée") +
            titre("Bienvenue,", prenom(nom) ? `${prenom(nom)}.` : "et merci.") +
            para("Votre carte est active : approchez-la d’un téléphone, votre profil s’affiche et s’enregistre en un geste. Quelques réglages suffisent pour en tirer le meilleur :") +
            coches([
              "<strong>Une photo</strong> : on retient un visage bien mieux qu’un nom.",
              "<strong>Vos liens utiles</strong> : LinkedIn, site, prise de rendez-vous, plaquette.",
              "<strong>L’échange de coordonnées</strong> : vos interlocuteurs vous laissent les leurs, vous les recevez par email.",
              "<strong>Un second profil</strong> si besoin (pro et perso), à changer à tout moment sans réimprimer.",
            ]) +
            bouton("Compléter mon profil", lien(`/compte/profils/${profileId}`)),
          { bas: 28 },
        ),
        section(
          para("<strong style=\"color:#121117;\">Carte perdue ?</strong> Désactivez-la depuis « Mes cartes » : elle n’affichera plus aucune donnée personnelle.", { petit: true, marge: 0 }),
          { fond: "ivoire", haut: 22, bas: 22 },
        ),
      ],
    }),
  };
}

/** Au porteur : quelqu'un a laissé ses coordonnées (fiche .vcf jointe). */
export function emailNouveauContact(lead: Pick<Lead, "name" | "email" | "phone" | "company" | "message">, porteur: Pick<Profile, "display_name">): Email {
  const sujet = `Nouveau contact : ${lead.name}${lead.company ? ` (${lead.company})` : ""}`;
  const lignes: [string, string][] = [["Nom", `<strong>${h(lead.name)}</strong>`]];
  if (lead.company) lignes.push(["Entreprise", h(lead.company)]);
  if (lead.email) lignes.push(["Email", `<a href="mailto:${h(lead.email)}" style="color:#5b3df5;">${h(lead.email)}</a>`]);
  if (lead.phone) lignes.push(["Téléphone", `<a href="tel:${h(lead.phone.replace(/[^\d+]/g, ""))}" style="color:#5b3df5;">${h(lead.phone)}</a>`]);
  return {
    sujet,
    html: document({
      sujet,
      preheader: `${lead.name} a scanné votre carte et vous a laissé ses coordonnées.`,
      raison: "Vous recevez cet email car l’échange de coordonnées est activé sur votre profil Tapikard.",
      sections: [
        section(
          surtitre("Nouveau contact") +
            titre(lead.name, "vous a laissé ses coordonnées.") +
            para(`${bonjour(porteur.display_name)} après avoir enregistré votre carte, ${h(lead.name)} a souhaité garder le contact. Sa fiche est jointe à cet email : ouvrez-la pour l’ajouter à votre carnet d’adresses.`) +
            recap(lignes) +
            (lead.message ? encart(`<p style="margin:0;font-family:Georgia,serif;font-style:italic;font-size:16px;line-height:1.6;color:#3d3a45;">« ${h(lead.message)} »</p>`) : "") +
            bouton("Voir mes contacts", lien("/compte/contacts")) +
            para("Conseil : un message dans les 48 heures fait toute la différence. Notez où vous vous êtes rencontrés dans votre espace.", { petit: true, marge: 0 }),
          { bas: 30 },
        ),
      ],
    }),
  };
}

/** Au visiteur : la fiche contact du porteur (fichier .vcf joint). */
export function emailFicheContact(nomVisiteur: string, porteur: Profile, cardId: string): Email {
  const sujet = `La fiche contact de ${porteur.display_name}`;
  const fonction = [porteur.title, porteur.company].filter(Boolean).join(" · ");
  return {
    sujet,
    html: document({
      sujet,
      preheader: `Enregistrez ${porteur.display_name} dans vos contacts en un clic.`,
      raison: "Vous recevez cet email car vous avez échangé vos coordonnées via une carte Tapikard.",
      sections: [
        section(
          para(bonjour(nomVisiteur)) +
            titre("Merci pour", "l’échange.") +
            para(`Voici la fiche contact de <strong>${h(porteur.display_name)}</strong>, jointe à cet email : ouvrez-la pour l’enregistrer dans votre téléphone.`) +
            encart(
              `<p style="margin:0;font-family:Georgia,serif;font-size:22px;color:#121117;">${h(porteur.display_name)}</p>${
                fonction ? `<p style="margin:4px 0 0;font-family:Inter,Helvetica,Arial,sans-serif;font-size:14px;color:#7a7682;">${h(fonction)}</p>` : ""
              }`,
            ) +
            bouton("Revoir son profil", lien(`/q/${cardId}`), "contour"),
          { bas: 26 },
        ),
        section(
          `<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr>
<td class="col" width="58%" valign="middle" style="padding-right:18px;">${surtitre("Vous aussi ?")}${para("Partagez vos coordonnées d’un geste, avec une carte de visite NFC élégante et modifiable à vie.", { marge: 12 })}${bouton("Découvrir Tapikard", lien("/"), "sombre")}</td>
<td class="col" width="42%" valign="middle">${visuel("carte-basic.png", "Carte de visite NFC Tapikard", 220)}</td>
</tr></table>`,
          { fond: "ivoire", haut: 26, bas: 26 },
        ),
      ],
    }),
  };
}
