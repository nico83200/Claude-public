// Dessins partagés par les documents du Haras : fers, traces et semelles.
// Le script insère une bibliothèque de <symbol> SVG dans la page ; on s'en sert
// ensuite avec <svg><use href="#fer-oeuf"/></svg>.
(function () {
  const BOUE = "#4a3826";
  const CLOU = "#e9dfcc";

  // Trous de clous le long d'un fer en U : 4 de chaque côté.
  const clousU = [[27, 82], [24, 64], [27, 46], [35, 31], [73, 82], [76, 64], [73, 46], [65, 31]]
    .map(([x, y]) => `<rect x="${x - 2.5}" y="${y - 4}" width="5" height="8" rx="1" fill="${CLOU}"/>`).join("");
  const ferU = `<path d="M18 100C4 62 12 14 50 12C88 14 96 62 82 100H67C77 64 71 28 50 27C29 28 23 64 33 100Z" fill="${BOUE}"/>`;

  // Fer en œuf : ovale fermé, 6 clous sur l'avant.
  const ferOeuf = `<path fill-rule="evenodd" fill="${BOUE}" d="M50 8C80 8 92 40 90 64C88 90 72 104 50 104C28 104 12 90 10 64C8 40 20 8 50 8ZM50 24C33 24 25 44 26 62C27 80 36 89 50 89C64 89 73 80 74 62C75 44 67 24 50 24Z"/>` +
    [[24, 58], [26, 40], [36, 24], [76, 58], [74, 40], [64, 24]]
      .map(([x, y]) => `<rect x="${x - 2.5}" y="${y - 4}" width="5" height="8" rx="1" fill="${CLOU}"/>`).join("");

  // Semelle de botte (pied droit) et ses deux motifs.
  const contourSemelle = "M30 6C46 4 54 18 53 36C52 52 47 60 46 72C45 82 50 92 48 104C46 116 36 120 28 118C18 116 14 106 16 94C18 80 14 66 12 50C10 30 14 8 30 6Z";
  const chevrons = Array.from({ length: 11 }, (_, i) => {
    const y = 14 + i * 9.5;
    return `<path d="M14 ${y + 5}L32 ${y}L52 ${y + 5}" stroke="${CLOU}" stroke-width="2.6" fill="none"/>`;
  }).join("");

  const symboles = {
    "fer-standard": [ferU + clousU, "0 0 100 112"],
    "fer-crampons": [ferU + clousU + `<rect x="16" y="98" width="16" height="11" rx="2" fill="${BOUE}"/><rect x="68" y="98" width="16" height="11" rx="2" fill="${BOUE}"/>`, "0 0 100 112"],
    "fer-oeuf": [ferOeuf, "0 0 100 112"],
    "sabot-nu": [`<path d="M50 10C82 10 94 44 90 74C88 92 78 102 66 104L58 80L50 96L42 80L34 104C22 102 12 92 10 74C6 44 18 10 50 10Z" fill="${BOUE}" opacity=".85"/>`, "0 0 100 112"],
    "chevreuil": [`<path d="M30 20C22 40 20 70 30 92C38 96 44 90 44 80C44 56 40 34 30 20Z" fill="${BOUE}"/><path d="M70 20C78 40 80 70 70 92C62 96 56 90 56 80C56 56 60 34 70 20Z" fill="${BOUE}"/>`, "0 0 100 112"],
    "semelle-chevrons": [`<path d="${contourSemelle}" fill="${BOUE}"/><clipPath id="clip-semelle"><path d="${contourSemelle}"/></clipPath><g clip-path="url(#clip-semelle)">${chevrons}</g>`, "0 0 66 124"],
    "semelle-lisse": [`<path d="${contourSemelle}" fill="${BOUE}"/><path d="M18 78H50" stroke="${CLOU}" stroke-width="1.5" opacity=".6"/>`, "0 0 66 124"],
    "branches": [`<g stroke="${BOUE}" stroke-width="5" stroke-linecap="round" fill="none"><path d="M10 90L58 40L92 30"/><path d="M40 58L46 22"/><path d="M20 30L70 96"/><path d="M58 78L88 70"/></g>`, "0 0 100 112"],
    "rien": [`<g fill="#7b8f5a" opacity=".8"><ellipse cx="30" cy="40" rx="9" ry="4" transform="rotate(30 30 40)"/><ellipse cx="68" cy="66" rx="10" ry="4" transform="rotate(-20 68 66)"/><ellipse cx="44" cy="88" rx="8" ry="3.5" transform="rotate(60 44 88)"/></g>`, "0 0 100 112"],
  };

  const defs = Object.entries(symboles)
    .map(([id, [contenu, vue]]) => `<symbol id="${id}" viewBox="${vue}">${contenu}</symbol>`).join("");
  document.body.insertAdjacentHTML("afterbegin",
    `<svg width="0" height="0" style="position:absolute" aria-hidden="true">${defs}</svg>`);
})();
