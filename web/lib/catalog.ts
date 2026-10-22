// Catalogue et tarifs — partagé entre le configurateur (client) et le serveur (calcul faisant foi).

export type MaterialId = "pvc_blanc" | "pvc_noir" | "bambou" | "metal_noir";
export type FinishId = "mat" | "brillant" | "soft_touch";
export type TemplateId = "classique" | "moderne" | "minimal" | "audacieux";
export type FontId = "sans" | "serif" | "mono";

export const MATERIALS: Record<MaterialId, { label: string; description: string; unitCents: number; printable: boolean; base: string; ink: string }> = {
  pvc_blanc: { label: "PVC blanc", description: "Impression quadri recto/verso, toutes couleurs", unitCents: 2400, printable: true, base: "#ffffff", ink: "#111827" },
  pvc_noir: { label: "PVC noir mat", description: "Noir profond, impression blanche ou couleur", unitCents: 2600, printable: true, base: "#15151a", ink: "#ffffff" },
  bambou: { label: "Bambou", description: "Bois naturel, gravure laser", unitCents: 3900, printable: false, base: "#d9b483", ink: "#3b2614" },
  metal_noir: { label: "Métal noir", description: "Acier brossé, gravure, inlay ferrite", unitCents: 6900, printable: false, base: "#1f2024", ink: "#d4d4d8" },
};

export const FINISHES: Record<FinishId, { label: string; extraCents: number; materials: MaterialId[] }> = {
  mat: { label: "Mat", extraCents: 0, materials: ["pvc_blanc", "pvc_noir", "bambou", "metal_noir"] },
  brillant: { label: "Brillant", extraCents: 0, materials: ["pvc_blanc", "pvc_noir"] },
  soft_touch: { label: "Soft-touch", extraCents: 300, materials: ["pvc_blanc", "pvc_noir"] },
};

export const TEMPLATES: Record<TemplateId, { label: string }> = {
  classique: { label: "Classique" },
  moderne: { label: "Moderne" },
  minimal: { label: "Minimal" },
  audacieux: { label: "Audacieux" },
};

export const FONTS: Record<FontId, { label: string; css: string }> = {
  sans: { label: "Sans serif", css: "Inter, 'Helvetica Neue', Arial, sans-serif" },
  serif: { label: "Serif", css: "Georgia, 'Times New Roman', serif" },
  mono: { label: "Mono", css: "'SF Mono', Menlo, Consolas, monospace" },
};

/** Remise dégressive par quantité. */
export const QUANTITIES: { qty: number; discount: number }[] = [
  { qty: 1, discount: 0 },
  { qty: 2, discount: 0.1 },
  { qty: 5, discount: 0.2 },
  { qty: 10, discount: 0.3 },
  { qty: 25, discount: 0.4 },
];

export type CardDesign = {
  template: TemplateId;
  material: MaterialId;
  finish: FinishId;
  font: FontId;
  accent: string;
  background: string;
  ink: string;
  name: string;
  title: string;
  company: string;
  phone: string;
  email: string;
  website: string;
  logo: string | null;
};

export const DEFAULT_DESIGN: CardDesign = {
  template: "moderne",
  material: "pvc_blanc",
  finish: "mat",
  font: "sans",
  accent: "#5b3df5",
  background: "#ffffff",
  ink: "#111827",
  name: "Camille Martin",
  title: "Directrice commerciale",
  company: "Atelier Nova",
  phone: "+33 6 12 34 56 78",
  email: "camille@ateliernova.fr",
  website: "ateliernova.fr",
  logo: null,
};

export function priceCents(material: MaterialId, finish: FinishId, quantity: number) {
  const tier = [...QUANTITIES].reverse().find((t) => quantity >= t.qty) ?? QUANTITIES[0];
  const unit = Math.round((MATERIALS[material].unitCents + FINISHES[finish].extraCents) * (1 - tier.discount));
  return { unit, total: unit * quantity, discount: tier.discount };
}

export function euros(cents: number) {
  return (cents / 100).toLocaleString("fr-FR", { style: "currency", currency: "EUR" });
}

const HEX = /^#[0-9a-fA-F]{6}$/;

/** Valide et normalise un design reçu du navigateur. */
export function sanitizeDesign(input: unknown): CardDesign {
  const d = (input ?? {}) as Record<string, unknown>;
  const str = (k: string, max = 80) => (typeof d[k] === "string" ? (d[k] as string).trim().slice(0, max) : "");
  const pick = <T extends string>(k: string, allowed: Record<T, unknown>, def: T): T =>
    typeof d[k] === "string" && (d[k] as string) in allowed ? (d[k] as T) : def;
  const color = (k: string, def: string) => (typeof d[k] === "string" && HEX.test(d[k] as string) ? (d[k] as string) : def);
  const material = pick("material", MATERIALS, DEFAULT_DESIGN.material);
  let finish = pick("finish", FINISHES, "mat");
  if (!FINISHES[finish].materials.includes(material)) finish = "mat";
  const logo = typeof d.logo === "string" && /^data:image\/(png|jpeg|webp);base64,/.test(d.logo) && d.logo.length < 600_000 ? d.logo : null;
  return {
    template: pick("template", TEMPLATES, DEFAULT_DESIGN.template),
    material,
    finish,
    font: pick("font", FONTS, "sans"),
    accent: color("accent", DEFAULT_DESIGN.accent),
    background: MATERIALS[material].printable ? color("background", MATERIALS[material].base) : MATERIALS[material].base,
    ink: color("ink", MATERIALS[material].ink),
    name: str("name", 60),
    title: str("title", 60),
    company: str("company", 60),
    phone: str("phone", 30),
    email: str("email", 80),
    website: str("website", 80),
    logo,
  };
}
