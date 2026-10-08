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
- **Assistant / conseiller en direct** : créer une clé `nlh_…` pour le site dans la console d'assistance et la placer côté serveur (voir ci-dessous). Sans clé, l'assistant fonctionne seul, sans bouton « Parler à un conseiller ».
- **LinkedIn** : remplacer le lien générique dans le footer.
- **Couleurs** : la palette est dérivée du logo (indigo `#4F46E5`, dégradé `#1A9BEA → #4F46E5 → #7C3AED`, accent cyan `#67E8F9`). Pour coller exactement à l'interface Approvia, ajuster les variables `--brand*` dans `styles.css`.
- **Logo** : `logo-mark.svg` est une reconstitution vectorielle du logo fourni ; le remplacer par le fichier source officiel si disponible.

## Ajouter une nouvelle solution à la gamme

Dans `index.html`, section `#solutions`, dupliquer un bloc `<li class="solution-card">` et adapter
logo, nom, domaine, statut (`status-live` = Disponible, `status-soon` = Bientôt) et lien « Découvrir ».
La grille s'adapte automatiquement. Une solution importante pourra ensuite avoir sa propre page
(ex. `/approvia/index.html`) en réutilisant les mêmes styles.

## Assistant NLapps et conseiller en direct

Même parcours que dans Approvia (conforme au guide `INTEGRATION.md` du centre d'assistance) :
bulle d'aide → l'assistant répond d'abord → « Parler à un conseiller » → conversation avec la console NLapps.

```
Navigateur (assets/js/assistant.js) ──► relais support/chat.php ──► centre d'assistance (api.php)
                                        clé nlh_… + jeton de conversation, jamais envoyés au navigateur
```

**Fonctionnement**
- s'ouvre seule après 6 s (`data-delay`), une fois par session ; sur mobile, simple invitation ;
- répond aux questions sur NLapps et Approvia ; réponses modifiables dans l'objet `INTENTS` d'`assets/js/assistant.js` ;
- « Parler à un conseiller » : demande le nom et l'email (facultatif, pour la transcription), puis ouvre la conversation (`open`) avec la transcription de l'assistant (12 derniers échanges) et le contexte (page, navigateur) ;
- messages envoyés avec `send`, réponses récupérées avec `poll` + `after` toutes les 4 s (45 s quand la bulle est fermée, avec pastille « nouvelle réponse ») ;
- statuts `open` / `pending` (« En attente de votre réponse ») / `closed` (arrêt du suivi, note 1 à 5 étoiles + commentaire) ; bouton « Terminer la conversation » (`close`) ;
- captures d'écran (JPEG, PNG, WebP, 4 Mo max) via `attach`, affichées via le relais (`file`) ;
- demandes de démo / projet : transmises au centre d'assistance (le conseiller peut répondre dans la bulle) ; si l'assistance est indisponible, envoi par le formulaire de contact ;
- disponibilité de l'équipe (`status`) : `away_message` affiché si personne n'est disponible ;
- messages `bot` / `user_bot` jamais affichés, texte toujours échappé, conversation reprise après rechargement.

**Mise en place**
1. Hébergement **PHP 8.1+ avec l'extension cURL** (le reste du site reste statique).
2. Console d'assistance : *Applications → [application] → Parc clients → Nouveau client* → copier la clé `nlh_…`.
3. Copier `support/config.sample.php` **hors de la racine web**, deux niveaux au-dessus de `support/chat.php`, sous le nom `nlapps-support-config.php`, et y coller la clé (ou définir les variables d'environnement `NLAPPS_SUPPORT_KEY` / `NLAPPS_SUPPORT_API`).
4. Vérifier : la bulle affiche « Conseiller disponible » quand un conseiller est en ligne, et une conversation test apparaît dans la console, rubrique *Conversations*.

Sans clé ou sur un hébergement sans PHP, le bouton « Parler à un conseiller » est masqué automatiquement.

**Protections du relais** : appels acceptés uniquement depuis le site lui-même, cookie de session `HttpOnly`/`SameSite`, limites anti-abus (5 conversations/heure, 40 messages/10 min par visiteur), délai réseau de 8 s, contrôle du type et de la taille des images, erreurs 401 journalisées sans nouvelle tentative, conversation oubliée sur 404 ou à la clôture.

## Notes

- Mockups Approvia construits en HTML/CSS (nets à toutes les tailles, aucune image à charger), données fictives.
- Accessibilité : lien d'évitement, navigation clavier, `prefers-reduced-motion` respecté.
- SEO : H1 unique, H2/H3 hiérarchisés, meta description, données structurées Organization / WebSite / SoftwareApplication.
