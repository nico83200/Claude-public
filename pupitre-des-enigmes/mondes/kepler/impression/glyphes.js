// Le code « bande étroite » de la mission 3 : chaque lettre est un motif de 3 × 3 cases.
// Le motif de la lettre n° i (A = 0) est le nombre (i + 1) × 19 modulo 512, lu en binaire
// sur les 9 cases (de gauche à droite, de haut en bas) : 26 motifs tous différents.
const ALPHABET = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";

function motif(lettre) {
  const valeur = ((ALPHABET.indexOf(lettre) + 1) * 19) % 512;
  return Array.from({ length: 9 }, (_, k) => (valeur >> (8 - k)) & 1);
}

// Un glyphe en SVG. taille = côté d'une case (en unités SVG), allume / eteint = couleurs.
function glyphe(lettre, taille, allume, eteint) {
  const cases = motif(lettre).map((on, k) =>
    `<rect x="${(k % 3) * taille}" y="${Math.floor(k / 3) * taille}" width="${taille * .86}" height="${taille * .86}" rx="${taille * .15}" fill="${on ? allume : eteint}"/>`
  ).join("");
  return `<svg width="${taille * 3}" height="${taille * 3}" viewBox="0 0 ${taille * 3} ${taille * 3}">${cases}</svg>`;
}
