import type { Profile } from "./types";

/** Profil d'exemple affiché sur /exemple : ne dépend pas de la base (absent des données de production). */
export const DEMO_PROFILE: Profile = {
  id: "exemple",
  user_id: "exemple",
  label: "Professionnel",
  display_name: "Camille Martin",
  title: "Directrice commerciale",
  company: "Atelier Nova",
  bio: "J'aide les marques à créer des espaces qui marquent les esprits.",
  phone: "+33 4 78 00 00 00",
  mobile: "+33 6 12 34 56 78",
  email: "camille@ateliernova.fr",
  website: "ateliernova.fr",
  address: "12 rue des Arts, 69002 Lyon",
  avatar: null,
  links: JSON.stringify([
    { label: "LinkedIn", url: "https://www.linkedin.com/" },
    { label: "Portfolio", url: "https://ateliernova.fr/realisations" },
    { label: "Prendre rendez-vous", url: "https://calendly.com/" },
  ]),
  accent: "#5b3df5",
  dark: 0,
  lead_capture: 1,
  is_public: 1,
  created_at: "2026-01-01 00:00:00",
  updated_at: "2026-01-01 00:00:00",
};
