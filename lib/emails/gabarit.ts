// Gabarit des emails Tapikard : tableaux et styles en ligne (seule mise en forme fiable dans Gmail, Outlook,
// Apple Mail), images PNG hébergées sur le site (Gmail n'affiche pas le SVG), polices de repli sûres.
import { APP_URL } from "../config";

export const COULEURS = {
  encre: "#121117", texte: "#3d3a45", gris: "#7a7682", ligne: "#e6e2d9",
  ivoire: "#f7f5f0", ivoireFonce: "#efebe3", violet: "#5b3df5", violetClair: "#a99bff", nuit: "#0e0d13", nuitTexte: "#b9b5c4",
};
const C = COULEURS;
export const SANS = "Inter, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
export const SERIF = "'Instrument Serif', Georgia, 'Times New Roman', serif";

/** Échappe une valeur saisie par un utilisateur avant de l'insérer dans un email. */
export const h = (v: string | null | undefined) =>
  (v ?? "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");

export const image = (fichier: string) => `${APP_URL}/email/${fichier}`;
export const lien = (chemin: string) => `${APP_URL}${chemin}`;

// --- Blocs ---------------------------------------------------------------------------------------

export function titre(texte: string, accent?: string, sombre = false): string {
  const couleur = sombre ? "#ffffff" : C.encre;
  return `<h1 style="margin:0 0 18px;font-family:${SERIF};font-weight:400;font-size:38px;line-height:1.08;letter-spacing:-0.5px;color:${couleur};">${h(texte)}${
    accent ? ` <em style="font-style:italic;color:${sombre ? C.violetClair : C.violet};">${h(accent)}</em>` : ""
  }</h1>`;
}

export function surtitre(texte: string, sombre = false): string {
  return `<p style="margin:0 0 14px;font-family:${SANS};font-size:12px;font-weight:600;letter-spacing:2px;text-transform:uppercase;color:${sombre ? C.violetClair : C.gris};">${h(texte)}</p>`;
}

/** Paragraphe : `html` est construit par nos modèles (les valeurs saisies y sont déjà échappées). */
export function para(html: string, opts: { sombre?: boolean; petit?: boolean; marge?: number } = {}): string {
  return `<p style="margin:0 0 ${opts.marge ?? 16}px;font-family:${SANS};font-size:${opts.petit ? 14 : 16}px;line-height:1.65;color:${opts.sombre ? C.nuitTexte : opts.petit ? C.gris : C.texte};">${html}</p>`;
}

export function bouton(libelle: string, url: string, variante: "sombre" | "clair" | "contour" = "sombre"): string {
  const fond = variante === "sombre" ? C.encre : variante === "clair" ? "#ffffff" : "transparent";
  const couleur = variante === "sombre" ? "#ffffff" : C.encre;
  const bord = variante === "contour" ? C.ligne : fond;
  return `<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:10px 0 22px;"><tr>
<td bgcolor="${fond}" style="border-radius:999px;background:${fond};border:1px solid ${bord};">
<a href="${url}" target="_blank" style="display:inline-block;padding:15px 30px;font-family:${SANS};font-size:15px;font-weight:600;color:${couleur};text-decoration:none;border-radius:999px;">${h(libelle)}</a>
</td></tr></table>`;
}

/** Encadré ivoire (récapitulatif, conseil…). */
export function encart(html: string): string {
  return `<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:8px 0 20px;"><tr>
<td style="background:${C.ivoire};border-radius:14px;padding:20px 22px;">${html}</td></tr></table>`;
}

/** Lignes « libellé / valeur » ; les valeurs sont du HTML déjà échappé. */
export function recap(lignes: [string, string][], total?: [string, string]): string {
  const ligne = ([k, v]: [string, string], fort = false) =>
    `<tr><td style="padding:7px 0;font-family:${SANS};font-size:${fort ? 16 : 14}px;color:${fort ? C.encre : C.gris};${fort ? "font-weight:700;border-top:1px solid " + C.ligne + ";padding-top:12px;" : ""}">${h(k)}</td>
<td align="right" style="padding:7px 0;font-family:${SANS};font-size:${fort ? 16 : 14}px;color:${C.encre};font-weight:${fort ? 700 : 500};${fort ? "border-top:1px solid " + C.ligne + ";padding-top:12px;" : ""}">${v}</td></tr>`;
  return encart(`<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">${lignes.map((l) => ligne(l)).join("")}${total ? ligne(total, true) : ""}</table>`);
}

/** Étapes numérotées (chiffres en serif violet). */
export function etapes(items: [string, string][], sombre = false): string {
  return `<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:6px 0 18px;">${items
    .map(([t, d], i) => `<tr>
<td width="44" valign="top" style="padding:10px 0;font-family:${SERIF};font-size:30px;line-height:1;color:${sombre ? C.violetClair : C.violet};">${i + 1}</td>
<td valign="top" style="padding:10px 0;font-family:${SANS};">
<div style="font-size:15px;font-weight:700;color:${sombre ? "#ffffff" : C.encre};margin-bottom:3px;">${h(t)}</div>
<div style="font-size:14px;line-height:1.55;color:${sombre ? C.nuitTexte : C.gris};">${d}</div></td></tr>`)
    .join("")}</table>`;
}

/** Liste à coches violettes. */
export function coches(items: string[], sombre = false): string {
  return `<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:4px 0 18px;">${items
    .map((t) => `<tr><td width="28" valign="top" style="padding:6px 0;font-family:${SANS};font-size:15px;font-weight:700;color:${sombre ? C.violetClair : C.violet};">✓</td>
<td style="padding:6px 0;font-family:${SANS};font-size:15px;line-height:1.5;color:${sombre ? "#e9e7ef" : C.texte};">${t}</td></tr>`)
    .join("")}</table>`;
}

export function visuel(fichier: string, alt: string, largeur = 504): string {
  return `<img src="${image(fichier)}" width="${largeur}" alt="${h(alt)}" style="display:block;width:100%;max-width:${largeur}px;height:auto;border:0;margin:0 auto;">`;
}

export const separateur = () => `<div style="height:1px;line-height:1px;background:${C.ligne};margin:26px 0;">&nbsp;</div>`;

// --- Sections et document -------------------------------------------------------------------------

export type Section = { html: string; fond?: "blanc" | "nuit" | "ivoire"; haut?: number; bas?: number };

export function section(html: string, opts: Omit<Section, "html"> = {}): Section {
  return { html, ...opts };
}

type Options = {
  sujet: string;
  preheader: string;
  sections: Section[];
  /** Raison de l'envoi, rappelée dans le pied de page (emails automatiques). */
  raison?: string;
  /** Campagne : ajoute le lien de désinscription (balise de l'outil d'emailing). */
  desinscription?: string;
};

export function document({ sujet, preheader, sections, raison, desinscription }: Options): string {
  const debutSombre = sections[0]?.fond === "nuit";
  const lignes = sections
    .map((s, i) => {
      const fond = s.fond === "nuit" ? C.nuit : s.fond === "ivoire" ? C.ivoire : "#ffffff";
      const rayon = [i === 0 ? "20px 20px" : "0 0", i === sections.length - 1 ? "20px 20px" : "0 0"].join(" ");
      return `<tr><td class="pad" bgcolor="${fond}" style="background:${fond};padding:${s.haut ?? 40}px 48px ${s.bas ?? 24}px;border-radius:${rayon};">${s.html}</td></tr>`;
    })
    .join("");
  const logo = debutSombre
    ? ""
    : `<tr><td style="padding:0 6px 22px;"><a href="${lien("/")}" target="_blank"><img src="${image("logo.png")}" width="148" height="36" alt="Tapikard" style="display:block;border:0;width:148px;height:36px;"></a></td></tr>`;
  const piedLien = (t: string, u: string) => `<a href="${u}" target="_blank" style="color:${C.gris};text-decoration:underline;">${t}</a>`;
  return `<!doctype html>
<html lang="fr" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
<meta name="color-scheme" content="light only">
<meta name="supported-color-schemes" content="light">
<title>${h(sujet)}</title>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  body { margin:0; padding:0; background:${C.ivoire}; -webkit-text-size-adjust:100%; }
  a { color:${C.violet}; }
  @media (max-width: 620px) {
    .conteneur { width:100% !important; }
    .pad { padding-left:24px !important; padding-right:24px !important; }
    h1 { font-size:31px !important; }
    .col { display:block !important; width:100% !important; padding:0 0 18px !important; }
  }
</style>
</head>
<body style="margin:0;padding:0;background:${C.ivoire};">
<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">${h(preheader)}${"&#8199;&#65279;&#847; ".repeat(40)}</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="${C.ivoire}" style="background:${C.ivoire};">
<tr><td align="center" style="padding:36px 12px 40px;">
<table role="presentation" class="conteneur" width="600" cellspacing="0" cellpadding="0" border="0" style="width:600px;max-width:600px;">
${logo}
<tr><td style="border-radius:20px;border:1px solid ${C.ligne};background:#ffffff;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">${lignes}</table>
</td></tr>
<tr><td style="padding:28px 24px 0;font-family:${SANS};font-size:12px;line-height:1.7;color:${C.gris};text-align:center;">
<img src="${image("symbole.png")}" width="28" height="28" alt="" style="display:inline-block;border:0;width:28px;height:28px;margin-bottom:10px;"><br>
<strong style="color:${C.texte};">Tapikard</strong> · Cartes de visite NFC imprimées, encodées et vérifiées en France<br>
Une question ? Répondez simplement à cet email, nous vous lisons.<br>
${raison ? `${h(raison)}<br>` : ""}
${piedLien("Site", lien("/"))} &nbsp;·&nbsp; ${piedLien("Mon espace", lien("/compte"))} &nbsp;·&nbsp; ${piedLien("Confidentialité", lien("/confidentialite"))}${
    desinscription ? ` &nbsp;·&nbsp; ${piedLien("Se désinscrire", desinscription)}` : ""
  }
</td></tr>
</table>
</td></tr></table>
</body></html>`;
}

/** Version texte (multipart) : améliore la délivrabilité et sert aux messageries sans HTML. */
export function versionTexte(html: string): string {
  return html
    .replace(/<style[\s\S]*?<\/style>/gi, "")
    .replace(/<div style="display:none[\s\S]*?<\/div>/i, "")
    .replace(/<a [^>]*href="([^"]+)"[^>]*>([\s\S]*?)<\/a>/gi, (_m, href, txt) => {
      const t = txt.replace(/<[^>]+>/g, "").trim();
      return t ? `${t} : ${href}` : "";
    })
    .replace(/<(br|\/p|\/h1|\/tr|\/div)[^>]*>/gi, "\n")
    .replace(/<[^>]+>/g, "")
    .replace(/&nbsp;/g, " ").replace(/&amp;/g, "&").replace(/&lt;/g, "<").replace(/&gt;/g, ">").replace(/&quot;/g, '"')
    .replace(/&#?\w+;/g, "")
    .replace(/[ \t]+/g, " ")
    .split("\n").map((l) => l.trim()).filter((l, i, a) => l || (a[i - 1] ?? "") !== "")
    .join("\n").trim();
}
