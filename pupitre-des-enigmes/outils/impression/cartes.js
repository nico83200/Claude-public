// Générateur de planches de cartes, commun à tous les mondes.
// Chaque monde fournit son thème et ses cartes, puis appelle planches(MONDE).
//
// MONDE = {
//   nom, mission, police (titres), accent (couleur des dos), motif (texte des dos),
//   types: { cle: { c: couleur, fond, libelle, zone } },
//   cartes: [ { id, t, nom, sous, vis | ini + coul, txt } ],
// }
function planches(MONDE) {
  document.head.insertAdjacentHTML("beforeend", `<style>
    @page { size: A4; margin: 0; }
    body { background: #fff; }
    .planche { width: 210mm; height: 297mm; position: relative; break-after: page; }
    .grille { position: absolute; left: 10.5mm; top: 12mm; display: grid; grid-template-columns: repeat(3, 63mm); grid-auto-rows: 88mm; }
    .legende { position: absolute; left: 10.5mm; right: 10.5mm; bottom: 5mm; font-size: 7.5pt; color: #8a8076; display: flex; justify-content: space-between; }
    .carte { width: 63mm; height: 88mm; outline: .2mm dashed #b9b2a6; outline-offset: -.1mm; padding: 3mm; position: relative; overflow: hidden; }
    .cadre { height: 100%; border-radius: 3mm; border: .5mm solid var(--c); background: #fffdf8; display: flex; flex-direction: column; overflow: hidden; }
    .type { background: var(--c); color: #fff; font-size: 6.8pt; letter-spacing: .14em; text-transform: uppercase; padding: 1.4mm 2.5mm;
            display: flex; justify-content: space-between; align-items: center; font-weight: 700; }
    .type .zone { background: rgba(255,255,255,.22); border-radius: 2mm; padding: .2mm 1.6mm; letter-spacing: .05em; }
    /* La tête de la carte (bandeau + nom + pictogramme) : les 30 mm qui dépassent de la fente du pupitre. */
    .tete { height: 20.5mm; display: flex; align-items: center; gap: 2.2mm; padding: 0 2.5mm;
            background: radial-gradient(circle at 15% 50%, #fff, var(--fond)); border-bottom: .3mm solid var(--c); }
    .visuel { flex: none; width: 16mm; height: 16mm; border-radius: 50%; background: #fff; border: .4mm solid var(--c);
              display: flex; align-items: center; justify-content: center; font-size: 21pt; line-height: 1; }
    .medaillon { flex: none; width: 16mm; height: 16mm; border-radius: 50%; background: var(--c); color: #fff; font-family: ${MONDE.police};
                 font-size: 13pt; display: flex; align-items: center; justify-content: center; border: .7mm solid ${MONDE.accent}; }
    .noms { flex: 1; min-width: 0; }
    .nom { font-family: ${MONDE.police}; font-weight: 900; font-size: 11.5pt; color: var(--c); line-height: 1.05; }
    .sous { font-size: 6.5pt; text-transform: uppercase; letter-spacing: .06em; color: #6d665d; margin-top: .8mm; line-height: 1.15; }
    .texte { font-size: 7.6pt; line-height: 1.32; padding: 3mm 3mm 0; text-align: center; flex: 1; position: relative; z-index: 1; }
    .filigrane { position: absolute; right: 2.5mm; bottom: 7mm; font-size: 34pt; line-height: 1; opacity: .13; }
    .texte em { font-family: "Playfair"; color: #6d665d; }
    .pied { display: flex; justify-content: space-between; font-size: 6pt; color: #6d665d; padding: 1mm 2.5mm 1.4mm; border-top: .2mm solid #d9cfbd; }
    .dos .cadre { background: var(--c); border-color: var(--c); color: ${MONDE.accent}; align-items: center; justify-content: center; text-align: center; position: relative; }
    .dos .motif { position: absolute; inset: 2.5mm; border: .4mm solid ${MONDE.accent}88; border-radius: 2mm; }
    .dos .symbole { font-size: 15pt; letter-spacing: .3em; margin-bottom: 2mm; }
    .dos .titre { font-family: ${MONDE.police}; font-weight: 700; font-size: 10pt; color: #fff; padding: 0 3mm; }
    .dos .categorie { font-size: 7pt; letter-spacing: .2em; text-transform: uppercase; margin-top: 1mm; }
    .dos .cadre { padding-bottom: 30mm; }
    /* La puce NFC, en bas : c'est ce bord qui entre dans la fente, jusqu'au lecteur. */
    .dos .nfc { position: absolute; bottom: 4mm; left: 50%; margin-left: -12.5mm; width: 25mm; height: 25mm; border-radius: 50%;
                border: .4mm dashed ${MONDE.accent}cc; display: flex; align-items: center; justify-content: center; font-size: 6pt; letter-spacing: .1em; }
    .dos .sens { position: absolute; bottom: 30.5mm; left: 0; right: 0; font-size: 5.6pt; letter-spacing: .14em; text-transform: uppercase; opacity: .85; }
    .dos .pupitre { font-size: 6pt; letter-spacing: .12em; text-transform: uppercase; margin-top: 3mm; opacity: .8; }
    .notice { padding: 16mm 18mm; }
    .notice h1 { font-size: 22pt; }
  </style>`);

  const T = MONDE.types;

  const recto = c => {
    const t = T[c.t];
    const visuel = c.ini ? `<div class="medaillon" style="background:${c.coul}">${c.ini}</div>` : `<div class="visuel">${c.vis}</div>`;
    return `<div class="carte" style="--c:${t.c};--fond:${t.fond}"><div class="cadre">
      <div class="type"><span>${t.libelle}</span><span class="zone">${t.zone}</span></div>
      <div class="tete">${visuel}<div class="noms"><div class="nom">${c.nom}</div><div class="sous">${c.sous}</div></div></div>
      <div class="texte">${c.txt}</div>
      ${c.ini ? "" : `<div class="filigrane">${c.vis}</div>`}
      <div class="pied"><span>${MONDE.nom}</span><span>${c.id}</span></div>
    </div></div>`;
  };

  const verso = c => `<div class="carte dos" style="--c:${T[c.t].c}"><div class="cadre"><div class="motif"></div>
    <div class="symbole">${MONDE.motif}</div><div class="titre">${MONDE.nom}</div>
    <div class="categorie">${T[c.t].libelle}</div>
    <div class="sens">▼ ce bord dans la fente ▼</div>
    <div class="nfc">PUCE NFC</div>
    <div class="pupitre">Le Pupitre des Énigmes</div>
  </div></div>`;

  // Verso en miroir horizontal, pour une impression recto verso « bord long ».
  const miroir = lot => [0, 3, 6].flatMap(i => [lot[i + 2], lot[i + 1], lot[i]]);
  const vide = '<div class="carte" style="outline:none"></div>';

  let html = "";
  for (let p = 0; p < MONDE.cartes.length; p += 9) {
    const lot = MONDE.cartes.slice(p, p + 9);
    const rectos = lot.map(recto), versos = lot.map(verso);
    while (rectos.length < 9) { rectos.push(vide); versos.push(vide); }
    const n = p / 9 + 1;
    html += `<div class="planche"><div class="grille">${rectos.join("")}</div>
      <div class="legende"><span>${MONDE.mission} · Cartes · planche ${n} · RECTO</span><span>Imprimer à 100 %</span></div></div>`;
    html += `<div class="planche"><div class="grille">${miroir(versos).join("")}</div>
      <div class="legende"><span>${MONDE.mission} · Cartes · planche ${n} · VERSO (miroir)</span><span>Recto verso, bord long</span></div></div>`;
  }

  // Récapitulatif des cartes par type, pour la notice.
  const parType = Object.entries(T).map(([cle, t]) => {
    const ids = MONDE.cartes.filter(c => c.t === cle).map(c => c.id);
    return ids.length ? `<tr><td>${t.libelle}</td><td>${ids.join(", ")}</td><td>${t.zone}</td></tr>` : "";
  }).join("");

  // La notice va à la fin, pour que chaque recto reste sur une page impaire.
  html += `<div class="planche notice">
    <h1>Les cartes · ${MONDE.mission}</h1>
    <p>${MONDE.cartes.length} cartes au format 63 × 88 mm (taille d'une carte à jouer), 9 par page.</p>
    <h3>Impression</h3>
    <ul>
      <li>Papier cartonné de <strong>300 à 350 g</strong>, à <strong>100 %</strong> (« taille réelle », sans ajustement à la page).</li>
      <li><strong>Recto verso, retournement bord long</strong> : chaque page de rectos est suivie de sa page de versos, déjà inversée en miroir.</li>
      <li>Sans imprimante recto verso : imprimez les pages impaires, remettez les feuilles dans le bac, puis imprimez les pages paires. Faites d'abord un essai sur papier ordinaire.</li>
    </ul>
    <h3>Fabrication</h3>
    <ul>
      <li>Découpez le long des pointillés, au cutter et à la règle, ou au massicot.</li>
      <li>Collez un <strong>autocollant NFC NTAG213</strong> (rond, 25 mm) au dos de chaque carte, sur le cercle « PUCE NFC », <strong>en bas de la carte</strong> : c'est le bord qui entre dans la fente du pupitre, jusqu'au lecteur.</li>
      <li>Une fois glissée, la carte ne montre que son haut : le bandeau, le pictogramme et le nom.</li>
      <li>Option : plastifiez les cartes ou glissez-les dans des protège-cartes (la puce se lit à travers).</li>
      <li>Le code en bas à droite sert à enregistrer chaque puce dans le pupitre.</li>
    </ul>
    <h3>Rangement</h3>
    <p>Les <strong>cartes Objet</strong> ne sont pas distribuées : chacune va dans l'enveloppe indiquée sur la carte.</p>
    <table><thead><tr><th>Type</th><th>Cartes</th><th>Fente</th></tr></thead><tbody>${parType}</tbody></table>
  </div>`;

  document.getElementById("contenu").innerHTML = html;
}
