// Rendu SVG d'une carte (85,6 × 54 mm, unité = 0,1 mm). Utilisé pour l'aperçu du configurateur
// et pour le BAT téléchargeable. Fonction pure : fonctionne côté client et côté serveur.
import QRCode from "qrcode";
import { FONTS, MATERIALS, type CardDesign } from "./catalog";

export const CARD_W = 856;
export const CARD_H = 540;
const BLEED = 30; // 3 mm de fonds perdus pour le BAT

const esc = (s: string) => s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");

function qrPath(text: string, x: number, y: number, size: number): string {
  const qr = QRCode.create(text, { errorCorrectionLevel: "M" });
  const n = qr.modules.size;
  const quiet = 2;
  const cell = size / (n + quiet * 2);
  let d = "";
  for (let r = 0; r < n; r++)
    for (let c = 0; c < n; c++)
      if (qr.modules.get(r, c)) {
        const px = x + (c + quiet) * cell;
        const py = y + (r + quiet) * cell;
        d += `M${px.toFixed(2)} ${py.toFixed(2)}h${cell.toFixed(2)}v${cell.toFixed(2)}h-${cell.toFixed(2)}z`;
      }
  return d;
}

function nfcIcon(x: number, y: number, s: number, color: string): string {
  // Ondes NFC stylisées
  const arcs = [0.35, 0.62, 0.9]
    .map((k) => {
      const r = s * k * 0.5;
      return `<path d="M${x + s * 0.3} ${y + s / 2 - r} A ${r} ${r} 0 0 1 ${x + s * 0.3} ${y + s / 2 + r}" fill="none" stroke="${color}" stroke-width="${s * 0.075}" stroke-linecap="round"/>`;
    })
    .join("");
  return `<circle cx="${x + s * 0.18}" cy="${y + s / 2}" r="${s * 0.07}" fill="${color}"/>${arcs}`;
}

function materialFill(d: CardDesign): { defs: string; fill: string } {
  if (d.material === "bambou") {
    return {
      defs: `<linearGradient id="wood" x1="0" y1="0" x2="1" y2="0.3"><stop offset="0" stop-color="#e2c191"/><stop offset="0.45" stop-color="#d4a971"/><stop offset="0.7" stop-color="#ddb985"/><stop offset="1" stop-color="#c99a5e"/></linearGradient>`,
      fill: "url(#wood)",
    };
  }
  if (d.material === "metal_noir") {
    return {
      defs: `<linearGradient id="metal" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#2a2b30"/><stop offset="0.5" stop-color="#18191c"/><stop offset="1" stop-color="#2c2d33"/></linearGradient>`,
      fill: "url(#metal)",
    };
  }
  return { defs: "", fill: d.background };
}

type Opts = { side: "recto" | "verso"; qrUrl: string; proof?: boolean };

export function cardSvg(d: CardDesign, { side, qrUrl, proof = false }: Opts): string {
  const font = FONTS[d.font].css;
  const printable = MATERIALS[d.material].printable;
  const accent = printable ? d.accent : d.ink;
  const { defs, fill } = materialFill(d);
  const pad = proof ? BLEED : 0;
  const W = CARD_W + pad * 2;
  const H = CARD_H + pad * 2;
  const logo = (x: number, y: number, w: number, h: number) =>
    d.logo ? `<image href="${d.logo}" x="${x}" y="${y}" width="${w}" height="${h}" preserveAspectRatio="xMinYMid meet"/>` : "";
  const t = (x: number, y: number, size: number, text: string, opts = "") =>
    text ? `<text x="${x}" y="${y}" font-size="${size}" ${opts}>${esc(text)}</text>` : "";
  const contacts = [d.phone, d.email, d.website].filter(Boolean);

  let body = "";
  if (side === "recto") {
    switch (d.template) {
      case "classique":
        body =
          logo(60, 50, 220, 90) +
          (!d.logo ? t(60, 110, 34, d.company, `font-weight="700" fill="${accent}" letter-spacing="1"`) : "") +
          t(60, 300, 52, d.name, `font-weight="700" fill="${d.ink}"`) +
          t(60, 345, 28, d.title, `fill="${d.ink}" opacity="0.75"`) +
          `<rect x="60" y="375" width="90" height="5" fill="${accent}"/>` +
          contacts.map((c, i) => t(60, 430 + i * 34, 24, c, `fill="${d.ink}" opacity="0.85"`)).join("");
        break;
      case "moderne":
        body =
          `<rect x="0" y="0" width="300" height="${CARD_H}" fill="${accent}"/>` +
          logo(40, 200, 220, 140) +
          (!d.logo ? t(150, 285, 30, d.company, `font-weight="700" fill="#fff" text-anchor="middle"`) : "") +
          t(350, 150, 48, d.name, `font-weight="700" fill="${d.ink}"`) +
          t(350, 192, 26, d.title, `fill="${d.ink}" opacity="0.7"`) +
          (d.logo ? t(350, 226, 24, d.company, `fill="${accent}" font-weight="600"`) : "") +
          contacts.map((c, i) => t(350, 340 + i * 38, 24, c, `fill="${d.ink}"`)).join("");
        break;
      case "minimal":
        body =
          (d.logo ? `<image href="${d.logo}" x="${CARD_W / 2 - 60}" y="70" width="120" height="80" preserveAspectRatio="xMidYMid meet"/>` : "") +
          t(CARD_W / 2, 270, 50, d.name, `font-weight="600" fill="${d.ink}" text-anchor="middle" letter-spacing="2"`) +
          t(CARD_W / 2, 315, 24, d.title.toUpperCase(), `fill="${accent}" text-anchor="middle" letter-spacing="4"`) +
          t(CARD_W / 2, 470, 21, contacts.join("   ·   "), `fill="${d.ink}" opacity="0.7" text-anchor="middle"`);
        break;
      case "audacieux": {
        const ink = printable ? "#ffffff" : d.ink;
        body =
          (printable ? `<rect x="${-pad}" y="${-pad}" width="${W}" height="${H}" fill="${accent}"/>` : "") +
          `<circle cx="${CARD_W - 40}" cy="40" r="230" fill="${ink}" opacity="0.08"/>` +
          logo(60, 50, 200, 80) +
          t(60, 300, 64, d.name, `font-weight="800" fill="${ink}"`) +
          t(60, 345, 28, [d.title, d.company].filter(Boolean).join(" — "), `fill="${ink}" opacity="0.85"`) +
          contacts.map((c, i) => t(60, 425 + i * 32, 22, c, `fill="${ink}" opacity="0.9"`)).join("");
        break;
      }
    }
  } else {
    const bg = printable ? accent : "transparent";
    const ink = printable ? "#ffffff" : d.ink;
    const qrSize = 250;
    const qx = CARD_W - qrSize - 70;
    const qy = (CARD_H - qrSize) / 2 - 20;
    body =
      (printable ? `<rect x="${-pad}" y="${-pad}" width="${W}" height="${H}" fill="${bg}"/>` : "") +
      (d.logo ? logo(70, 70, 260, 110) : t(70, 140, 40, d.company || d.name, `font-weight="700" fill="${ink}"`)) +
      nfcIcon(70, 300, 90, ink) +
      t(180, 340, 24, "Approchez votre téléphone", `fill="${ink}" font-weight="600"`) +
      t(180, 372, 20, "ou scannez le QR code", `fill="${ink}" opacity="0.8"`) +
      `<rect x="${qx}" y="${qy}" width="${qrSize}" height="${qrSize}" rx="18" fill="#ffffff"/>` +
      `<path d="${qrPath(qrUrl, qx, qy, qrSize)}" fill="#111111"/>` +
      t(qx + qrSize / 2, qy + qrSize + 40, 17, "tapikard.com", `fill="${ink}" opacity="0.7" text-anchor="middle" letter-spacing="1"`);
  }

  const marks = proof
    ? `<g stroke="#000" stroke-width="1" fill="none">${[
        [0, pad, pad - 6, pad], [pad, 0, pad, pad - 6], [W, pad, W - pad + 6, pad], [W - pad, 0, W - pad, pad - 6],
        [0, H - pad, pad - 6, H - pad], [pad, H, pad, H - pad + 6], [W, H - pad, W - pad + 6, H - pad], [W - pad, H, W - pad, H - pad + 6],
      ].map(([a, b, c, e]) => `<line x1="${a}" y1="${b}" x2="${c}" y2="${e}"/>`).join("")}
      <rect x="${pad + 30}" y="${pad + 30}" width="${CARD_W - 60}" height="${CARD_H - 60}" stroke="#e11d48" stroke-dasharray="6 6" stroke-width="1.5"/></g>`
    : "";

  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${W} ${H}" width="${proof ? `${(W / 10).toFixed(1)}mm` : "100%"}" ${proof ? `height="${(H / 10).toFixed(1)}mm"` : ""} font-family="${esc(font)}" role="img" aria-label="Carte ${side}">
<defs>${defs}<clipPath id="card-${side}"><rect x="0" y="0" width="${W}" height="${H}" rx="${proof ? 0 : 32}"/></clipPath></defs>
<g clip-path="url(#card-${side})"><rect x="0" y="0" width="${W}" height="${H}" fill="${fill}"/><g transform="translate(${pad} ${pad})">${body}</g></g>${marks}
</svg>`;
}
