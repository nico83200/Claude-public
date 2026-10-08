# Site vitrine NLapps

Site statique (HTML + CSS + JS vanilla, aucune dépendance ni étape de build).

```
nlapps-site/
├── index.html                              # page principale (toutes les sections du brief)
├── mentions-legales/index.html             # URL propre /mentions-legales/
├── politique-de-confidentialite/index.html
├── assets/css/styles.css                   # design system (variables en tête de fichier)
├── assets/js/main.js                       # menu mobile, animations, formulaire
├── assets/img/logo-mark.svg                # pictogramme NLapps (aussi favicon.svg)
├── robots.txt / sitemap.xml
```

## Aperçu local

```bash
npx http-server nlapps-site -p 8080   # ou : python3 -m http.server -d nlapps-site 8080
```

## À compléter avant mise en ligne

- **Domaine** : `https://www.nlapps.fr/` est utilisé par hypothèse (canonical, Open Graph, JSON-LD, sitemap, robots). À remplacer si besoin.
- **Formulaire** : renseigner `data-endpoint` sur `#contact-form` (Formspree, Basin, backend…). Sans endpoint, la demande s'ouvre dans la messagerie vers `data-mailto` (`contact@nlapps.fr`).
- **Mentions légales / confidentialité** : compléter les champs surlignés `[…]` (forme juridique, SIREN, adresse, hébergeur…).
- **Image de partage** : ajouter `assets/img/og-image.png` (1200×630).
- **LinkedIn** : remplacer le lien générique dans le footer.
- **Couleurs** : la palette est dérivée du logo (indigo `#4F46E5`, dégradé `#1A9BEA → #4F46E5 → #7C3AED`, accent cyan `#67E8F9`). Pour coller exactement à l'interface Approvia, ajuster les variables `--brand*` dans `styles.css`.
- **Logo** : `logo-mark.svg` est une reconstitution vectorielle du logo fourni ; le remplacer par le fichier source officiel si disponible.

## Ajouter une nouvelle solution à la gamme

Dans `index.html`, section `#solutions`, dupliquer un bloc `<li class="solution-card">` et adapter
logo, nom, domaine, statut (`status-live` = Disponible, `status-soon` = Bientôt) et lien « Découvrir ».
La grille s'adapte automatiquement. Une solution importante pourra ensuite avoir sa propre page
(ex. `/approvia/index.html`) en réutilisant les mêmes styles.

## Notes

- Mockups Approvia construits en HTML/CSS (nets à toutes les tailles, aucune image à charger), données fictives.
- Accessibilité : lien d'évitement, navigation clavier, `prefers-reduced-motion` respecté.
- SEO : H1 unique, H2/H3 hiérarchisés, meta description, données structurées Organization / WebSite / SoftwareApplication.
