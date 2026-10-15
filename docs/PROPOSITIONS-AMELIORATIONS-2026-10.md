# Propositions d'améliorations — analyse du 15/10/2026 (version 2.17.1)

Analyse faite directement dans le code, avec des mesures sur l'application de démonstration (requêtes par page, captures mobiles,
journal de développement). Chaque constat cite sa preuve. Impact : fort / moyen / faible. Effort : S (< ½ journée), M (1 à 3 jours), L (> 3 jours).

**Résumé.** L'application est riche, cohérente et bien testée côté serveur (308 tests), avec de bonnes bases de sécurité
(webhooks Stripe signés, sessions chiffrées, liens publics à jeton long et limités en débit, droits par cheval centralisés).
Trois priorités : **rétablir l'intégration continue** (en échec permanent), **envoyer les notifications en file d'attente**
(aujourd'hui pendant la requête de l'utilisateur), **alléger le tableau de bord mobile**.

---

## Technique

### T1 · Notifications envoyées pendant la requête de l'utilisateur — impact fort, effort S
**Constat.** `AppNotification` n'implémente pas `ShouldQueue` (`app/Notifications/AppNotification.php:21`) : e-mail et push partent
pendant la requête (27 appels `notify()` dans l'app). Un serveur d'e-mails lent ralentit l'action ; un serveur en panne la fait échouer
(page d'erreur alors que l'action a été enregistrée).
**Proposition.** `implements ShouldQueue` (la file est déjà traitée chaque minute par le cron, `routes/console.php`) ; garder
`notifyNow()` pour l'essai de notification. Ajouter un test qui vérifie la mise en file.

### T2 · Réglages vides relus et réécrits à chaque page — impact moyen, effort S
**Constat.** `ApplicationSetting::get()` (`app/Models/ApplicationSetting.php:15`) utilise `cache()->remember` : une valeur `null` n'est
jamais considérée comme en cache. Mesuré sur `/notifications` : pour `app_name` et `monitor_enabled`, à chaque page une lecture du cache,
une lecture de la table et une **écriture** dans le cache.
**Proposition.** Mettre en cache un enveloppe (`['v' => $value]`) pour mémoriser aussi l'absence de valeur.

### T3 · 35 à 65 requêtes SQL par page, même avec peu de données — impact moyen, effort M
**Constat.** Mesuré sur la démo : tableau de bord 65, agenda 62, budget 65, fiche cheval 57, notifications 35 (le gabarit commun en fait
une trentaine). Le journal de développement relève des chargements en cascade : `User::profile` (363 fois), `Horse::organization` (240),
`OrganizationMember::role` (176). Les adhésions et rôles sont chargés deux fois (middleware `org` puis `HorseAccess::membershipsOf`,
`app/Services/HorseAccess.php:90`), le compteur de notifications non lues deux fois.
**Proposition.** Chargements groupés (`with`) sur ces relations, une seule lecture des adhésions par requête, compteur de notifications
partagé avec la vue. Objectif : moins de 25 requêtes par page. Gain réel quand un compte aura beaucoup de chevaux (écuries).

### T4 · Sauvegardes automatiques hors de l'hébergement — impact fort, effort M
**Constat.** L'application ne sauvegarde la base qu'avant une mise à jour ; les sauvegardes quotidiennes reposent sur l'hébergeur
(`docs/SAUVEGARDE.md:21`). Une erreur de manipulation ou un incident Hostinger peut faire perdre des données de santé et de facturation.
**Proposition.** (idée 13 gardée de côté) Tâche planifiée : export chiffré de la base et des fichiers privés vers un stockage externe
(compatible S3 ou Google Drive), rétention 30 jours, alerte en cas d'échec, et une restauration testée une fois par trimestre.

### T5 · Page de confidentialité : sous-traitants « [à compléter] » par défaut — impact moyen (juridique), effort S
**Constat.** `resources/views/public/privacy.blade.php:17` affiche « Hébergement : [à compléter] … Envoi d'emails : [à compléter] »
tant que le réglage n'est pas rempli.
**Proposition.** Vérifier Administration › Réglages › Sous-traitants en production : Hostinger, Stripe, Anthropic (assistant),
ElevenLabs (voix), Google et Apple (connexion), le service d'e-mails. Ajouter un rappel dans le tableau de bord administrateur tant
que le texte contient « [à compléter] ».

---

## Développement

### D1 · L'intégration continue est en échec à chaque envoi — impact fort, effort S
**Constat.** `.github/workflows/tests.yml` lance `npm test`, script absent de `package.json` (échec « Missing script: test »), et
`pint --test`, qui échoue sur 65 fichiers. Le contrôle automatique ne donne donc plus aucune information.
**Proposition.** Un commit de formatage (`pint`), retirer `npm test` (ou le brancher sur D3), puis garder l'intégration continue verte :
chaque envoi est alors vérifié automatiquement (tests, style).

### D2 · Analyse statique (Larastan) — impact moyen, effort M
**Constat.** Aucun outil d'analyse statique. Exemple d'erreur qu'il aurait signalée : `TypeError array_intersect()` dans
`HorseAccess.php:90` (journal du 11/10, corrigé depuis par la réécriture des droits).
**Proposition.** Larastan niveau 5 dans l'intégration continue, avec un fichier de référence pour l'existant, puis montée progressive.

### D3 · Parcours JavaScript non testés automatiquement — impact moyen, effort M
**Constat.** Plus de 2 000 lignes dans `resources/js` (assistant vocal, file hors ligne, écran des notifications, glisser pour supprimer)
vérifiées seulement par des scripts Playwright ponctuels, supprimés après usage.
**Proposition.** Un petit dossier `tests/e2e` (Playwright, déjà installé) avec 6 à 8 parcours : connexion, assistant (Claude simulé),
écran des notifications, glisser-supprimer-annuler, mode séance hors ligne. Lancé par `npm test` dans l'intégration continue.

### D4 · Détecter les chargements en cascade dans les tests — impact moyen, effort S
**Constat.** `Model::preventLazyLoading()` ne fait qu'écrire dans le journal (`app/Providers/AppServiceProvider.php:40-41`) : personne ne le voit.
**Proposition.** Lever une exception en environnement de test : chaque nouveau chargement en cascade fera échouer un test.

### D5 · Fichiers très longs — impact faible, effort M
**Constat.** `AssistantTools.php` (729 lignes), `RidingSessionController.php` (417), `BillingService.php` (402).
**Proposition.** Découper au fil des modifications (un fichier d'outils par domaine pour l'assistant), sans chantier dédié.

---

## Expérience utilisateur

### U1 · Tableau de bord mobile trop long, l'essentiel sous le premier écran — impact fort, effort S/M
**Constat.** Mesuré : 3,6 écrans de haut. « Bien démarrer », la fiche d'urgence et le rappel de bilan s'empilent avant « Aujourd'hui »
et « Échéances » (`resources/views/dashboard.blade.php`).
**Proposition.** « Aujourd'hui » et « Échéances » en premier ; une seule carte « À faire » qui présente une suggestion à la fois
(passer à la suivante, « Plus tard ») ; « Bien démarrer » réduit à une ligne de progression une fois 3 étapes faites.

### U2 · Généraliser « glisser pour supprimer » et « Annuler » — impact moyen, effort M
**Constat.** 18 boîtes de confirmation natives du navigateur (`confirm()` dans les vues), d'apparence étrangère dans l'application
installée ; le geste avec « Annuler » n'existe que pour les séances.
**Proposition.** Même geste et même message d'annulation pour les dépenses, les soins, les événements d'agenda et les notifications
(le composant `resources/js/swipe-delete.js` est réutilisable) ; remplacer les `confirm()` restants par une fenêtre de l'application.

### U3 · Cibles tactiles trop petites pour des actions destructrices — impact moyen, effort S
**Constat.** Fiche du cheval : « Principale » et « Suppr. » sous chaque photo, en petit texte côte à côte : risque de suppression par erreur.
**Proposition.** Boutons d'au moins 44 px (icônes), suppression avec « Annuler » (U2).

### U4 · Listes paginées par 20 avec liens de pages — impact faible, effort M
**Constat.** 10 listes paginées (`paginate()`), avec des liens de page peu pratiques au doigt.
**Proposition.** Bouton « Afficher plus » (ou chargement au défilement) sur téléphone.

---

## Produit (idées)

- **P1 · Actions dans les notifications** (nouveau) : « C'est fait » / « Reporter d'une semaine » directement depuis la notification
  d'un rappel de soin, sans ouvrir l'app (`public/sw.js:90` ne gère aujourd'hui que l'ouverture). Impact fort sur la tenue du carnet.
- **P2 · Détection de changements de santé** (idée 3) : croiser poids, observations et bilans pour prévenir, sans diagnostic.
- **P3 · Paiement en ligne des relevés de pension** (idée 9) : les écuries encaissent depuis la notification (Stripe).
- **P4 · Lien temporaire du dossier pour un professionnel** (idée 10).
- **P5 · Séance guidée à la voix** (idée 8).

---

## Feuille de route

- **À faire maintenant** : D1 (intégration continue), T1 (notifications en file), T2 (réglages), T5 (sous-traitants), U1 (tableau de bord).
  ✅ Fait en 2.18.0.
- **Prochainement** : T4 (sauvegardes externes), T3 (requêtes), D4, U2, U3, P1.
  ✅ T3, D4, U2, U3, P1 faits en 2.19.0 (tableau de bord d'écurie 75 → 44 requêtes, test qui empêche le retour des
  requêtes par cheval ; « Annuler » sur dépenses et soins ; fenêtre de confirmation de l'application ; boutons photo de 44 px ;
  « C'est fait » / « Reporter » dans les notifications de rappel). T4 en attente du choix du stockage externe.
- **Plus tard** : D2, D3, D5, U4, P2 à P5.
