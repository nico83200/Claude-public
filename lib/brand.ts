// Identité Tapikard : une seule définition du logo, utilisée partout (site, cartes imprimées, favicon).
// Le dessin est celui de la carte Basic : carré violet aux coins arrondis et ondes NFC blanches.

export const BRAND_NAME = "Tapikard";
export const BRAND_VIOLET = "#5b3df5";

/** Mot-symbole « Tapikard » : Inter, graisse 800, approche légèrement resserrée. */
export const WORDMARK_WEIGHT = 800;
export const WORDMARK_TRACKING_EM = -0.017;

/** Ondes NFC (point + trois arcs) dans un carré de 100 × 100. */
export function brandWaves(x: number, y: number, size: number, color: string): string {
  const k = size / 100;
  const cx = x + 30 * k;
  const cy = y + 50 * k;
  const arcs = [17.5, 31, 45]
    .map((r0) => {
      const r = r0 * k;
      return `<path d="M${cx} ${cy - r} A ${r} ${r} 0 0 1 ${cx} ${cy + r}" fill="none" stroke="${color}" stroke-width="${7.5 * k}" stroke-linecap="round"/>`;
    })
    .join("");
  return `<circle cx="${x + 18 * k}" cy="${cy}" r="${7 * k}" fill="${color}"/>${arcs}`;
}

/** Symbole complet (carré + ondes) en (x, y), côté `size`. Arrondi 36/140 du côté, ondes à (22, 20) sur 100. */
export function brandMark(x: number, y: number, size: number, opts: { tile?: string; ink?: string; square?: boolean } = {}): string {
  const k = size / 140;
  const rx = opts.square ? 0 : 36 * k;
  return (
    `<rect x="${x}" y="${y}" width="${size}" height="${size}" rx="${rx}" fill="${opts.tile ?? BRAND_VIOLET}"/>` +
    brandWaves(x + 22 * k, y + 20 * k, 100 * k, opts.ink ?? "#ffffff")
  );
}

/** Le symbole seul, en SVG autonome (favicon, composant React). */
export function brandMarkSvg(opts: { square?: boolean } = {}): string {
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 140 140">${brandMark(0, 0, 140, opts)}</svg>`;
}
