// Convertit une page HTML en PDF avec Chromium (Playwright).
// Utilisation : node rendre.js entree.html sortie.pdf [paysage] [titre de pied de page]
const { chromium } = require("playwright");
const path = require("path");

(async () => {
  const [entree, sortie, orientation, piedDePage] = process.argv.slice(2);
  const paysage = orientation === "paysage";
  const navigateur = await chromium.launch();
  const page = await navigateur.newPage();
  await page.goto("file://" + path.resolve(entree), { waitUntil: "networkidle" });
  await page.evaluate(() => document.fonts.ready);

  const pied = piedDePage
    ? `<div style="width:100%;font:7.5pt sans-serif;color:#8a8076;padding:0 14mm;display:flex;justify-content:space-between">
         <span>${piedDePage}</span><span><span class="pageNumber"></span> / <span class="totalPages"></span></span>
       </div>`
    : "<div></div>";

  await page.pdf({
    path: sortie,
    format: "A4",
    landscape: paysage,
    printBackground: true,
    preferCSSPageSize: true,
    displayHeaderFooter: Boolean(piedDePage),
    headerTemplate: "<div></div>",
    footerTemplate: pied,
  });
  await navigateur.close();
})();
