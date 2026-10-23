// Liste des emails pour le back-office (aperçu avec données d'exemple, envoi de test, export).
import { APP_URL } from "../config";
import { DEFAULT_DESIGN, basicDesign, priceCents, basicPriceCents } from "../catalog";
import { DEMO_PROFILE } from "../demoProfile";
import type { Order } from "../types";
import { OUTILS, campagneConseils, campagneDecouverte, campagneOffreBasic, type Outil } from "./campagnes";
import { emailCarteActivee, emailCommandeConfirmee, emailCommandeExpediee, emailConnexion, emailFicheContact, emailNouveauContact, emailRemboursement, type Email } from "./messages";

const commande = (product: "custom" | "basic"): Order => ({
  id: "cmd_Exemple7Kq2ZrT9", user_id: "", profile_id: "exemple", status: "paid", product,
  design: "", material: product === "basic" ? "pvc_noir" : "pvc_blanc", finish: "mat", quantity: 2,
  amount_cents: product === "basic" ? basicPriceCents(2).total : priceCents("pvc_blanc", "mat", 2).total,
  shipping: "{}", proof_comment: null, tracking: "6A12345678901", stripe_session_id: null,
  created_at: "", paid_at: null, proof_approved_at: null, shipped_at: null, archived_at: null, refunded_cents: 0, code_promo: null, remise_cents: 0, etiquette_pdf: null,
});
const prospect = { name: "Hugo Lambert", email: "hugo@studio-lambert.fr", phone: "+33 6 98 76 54 32", company: "Studio Lambert", message: "Ravi de notre échange au salon, on se rappelle la semaine prochaine ?" };

export const EMAILS_AUTO: { id: string; nom: string; quand: string; exemple: () => Email }[] = [
  { id: "connexion", nom: "Lien de connexion", quand: "À chaque demande de connexion", exemple: () => emailConnexion(`${APP_URL}/connexion/verifier?token=exemple`, "482913", "Camille Martin") },
  { id: "commande-perso", nom: "Commande confirmée — personnalisée", quand: "Paiement reçu, BAT à valider", exemple: () => emailCommandeConfirmee(commande("custom"), DEFAULT_DESIGN, "Camille Martin") },
  { id: "commande-basic", nom: "Commande confirmée — Basic", quand: "Paiement reçu d’une carte Basic", exemple: () => emailCommandeConfirmee(commande("basic"), basicDesign("noir"), "Camille Martin") },
  { id: "expedition", nom: "Commande expédiée", quand: "Commande marquée expédiée", exemple: () => emailCommandeExpediee(commande("custom"), "6A12345678901", "Camille Martin") },
  { id: "remboursement", nom: "Remboursement", quand: "Remboursement enregistré dans le back-office (retour)", exemple: () => emailRemboursement(commande("basic"), 2099, true, "Camille Martin") },
  { id: "activation", nom: "Carte activée", quand: "Première activation d’une carte", exemple: () => emailCarteActivee("exemple", "Camille Martin") },
  { id: "nouveau-contact", nom: "Nouveau contact (au porteur)", quand: "Un visiteur laisse ses coordonnées", exemple: () => emailNouveauContact(prospect, DEMO_PROFILE) },
  { id: "fiche-contact", nom: "Fiche contact (au visiteur)", quand: "Le visiteur demande la fiche du porteur", exemple: () => emailFicheContact(prospect.name, DEMO_PROFILE, "exemple") },
];

export const CAMPAGNES: { id: string; nom: string; cible: string; email: (outil: Outil) => Email }[] = [
  { id: "decouverte", nom: "Découverte de Tapikard", cible: "Prospects : indépendants, commerciaux, entreprises", email: campagneDecouverte },
  { id: "offre-basic", nom: "Offre carte Basic", cible: "Prospects et clients : la carte prête à l’emploi", email: campagneOffreBasic },
  { id: "conseils", nom: "Cinq réflexes pour votre carte", cible: "Clients : mieux utiliser leur carte", email: campagneConseils },
];

export function emailParId(id: string, outil: Outil = "brevo"): { email: Email; campagne: boolean } | null {
  const auto = EMAILS_AUTO.find((e) => e.id === id);
  if (auto) return { email: auto.exemple(), campagne: false };
  const c = CAMPAGNES.find((e) => e.id === id);
  return c ? { email: c.email(outil in OUTILS ? outil : "brevo"), campagne: true } : null;
}
