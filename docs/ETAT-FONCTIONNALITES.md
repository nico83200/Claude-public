# État des fonctionnalités

Légende : ✅ terminé et couvert par des tests automatisés · ☑️ terminé, vérifié manuellement / partiellement testé ·
⚙️ terminé mais nécessite une configuration · ⚠️ partiel · ❌ non réalisé

## Socle
- ✅ Inscription avec espace personnel, connexion, déconnexion, vérification d'email, réinitialisation du mot de passe, limitation des tentatives
- ✅ 2FA TOTP (☑️ parcours d'activation vérifié manuellement uniquement), obligatoire pour l'administration
- ✅ Organisations multiples, changement d'espace, rôles système et rôles personnalisés, invitations de membres
- ✅ Cloisonnement entre comptes / organisations, accès par URL refusé (tests d'isolation)
- ✅ Licences → limites (chevaux, membres, partages, stockage) et fonctionnalités par offre ; lecture seule si expiré/suspendu, exports conservés

## Chevaux
- ✅ Fiche d'identité complète, identifiants SIRE/UELN/transpondeur, archivage, transfert d'espace, suppression logique
- ✅ Propriétaires/détenteurs datés, lieux de vie, rattachement à une écurie avec droits choisis par le propriétaire
- ✅ Généalogie (2 générations) avec liens vers les fiches accessibles, représentation graphique
- ✅ Photos (compression, miniatures, diffusion contrôlée), documents privés
- ✅ Recherche d'identité : adaptateurs, comparaison, différences, validation champ par champ, provenance — ⚙️ Wikidata sans clé, Brave avec clé ; ❌ registre IFCE (pas d'API ouverte, voir INTEGRATIONS.md)

## Santé
- ✅ Soins par catégorie, professionnel, coût → dépense, document joint, prochain contrôle → calendrier, verrou de modification concurrente
- ✅ Traitements (posologie saisie, jamais calculée), administrations, fin → calendrier ; observations / signalements avec notification ciblée
- ✅ Vue d'ensemble : échéances, alertes, traitements ; export PDF de l'historique sanitaire
- ⚠️ Catégories de soins personnalisées : prévues en base (`care_categories.organization_id`), sans écran de gestion

## Intervenants
- ✅ Répertoire par espace, anti-doublon, association aux chevaux, historique des interventions

## Calendrier
- ✅ Vues jour/semaine/mois (liste sur mobile), types, statuts, récurrences calculées sans doublon, événements générés par soins/traitements
- ✅ Rappels : rendez-vous J-1, échéances de soins J-7, fins de traitement J-1, envoyés une fois, aux seules personnes autorisées
- ⚠️ Délais de rappel non paramétrables par l'utilisateur (fixés dans `reminders:send`)
- ❌ Synchronisation avec un calendrier externe (iCal) — prévue (`external_uid`)

## Séances et exercices
- ✅ Création, préparation (phases, ordre, durées, répétitions, consignes, pauses), modèles, mode séance mobile, bilan, historique filtrable, indicateurs de progression
- ✅ Copie figée des exercices (snapshot + version) : la bibliothèque ne modifie jamais les séances passées ; duplication sans altérer l'originale
- ✅ Bibliothèque : 23 exercices par défaut, exercices personnels et partagés en écurie, duplication, archivage, filtres
- ❌ Médias de séance (photos/vidéos) : table prévue (`session_media`), sans interface

## Partage et demi-pension
- ✅ Invitations par email (jeton haché, usage unique, adresse vérifiée), droits configurables, préréglages, période d'accès, révocation immédiate, historique des accès
- ✅ Révocation propagée au hors ligne (refus serveur + purge au pull) — limite documentée pour les appareils restés hors ligne

## Quotidien et budget
- ✅ Plans alimentaires avec historique, suivi quotidien, notification en cas d'anomalie
- ✅ Dépenses par cheval / espace, justificatifs, indicateurs (mois, année, catégorie, cheval, évolution), exports CSV/PDF

## Hors ligne (PWA)
- ✅ Manifeste, Service Worker, coquille hors ligne, IndexedDB par utilisateur, file d'opérations atomique, idempotence, fusion à trois voies, conflits avec résolution, opérations refusées conservées, expiration, purge à la déconnexion et à distance, PIN facultatif
- ✅ Test de bout en bout Chromium (coupure réseau, saisie, rechargement, resynchronisation, purge)
- ⚠️ Safari iOS / Firefox : non testés automatiquement (à valider sur appareils réels)
- ⚠️ Photos non disponibles hors ligne (choix de minimisation)

## Commercial
- ✅ Offres administrables (prix, limites, fonctionnalités, essai, public, archivage sans suppression), historique tarifaire, migration avec préavis ≥ 30 j
- ✅ Webhooks signés, idempotents, désordre géré, retraitement, alertes ; licences synchronisées ; grâce puis suspension ; licences manuelles
- ⚙️ Checkout, portail client, changement d'offre, résiliation/reprise : intégrés et testés avec une passerelle simulée — **nécessitent les clés Stripe** et un test en mode test Stripe
- ☑️ `billing:price-migrations` (notification + application) : non couvert par des tests automatisés
- ⚠️ TVA : à déléguer à Stripe Tax si nécessaire

## Administration
- ✅ Indicateurs (comptes, organisations, chevaux, abonnements, répartition, échecs, résiliations, MRR **estimé** distinct du facturé et de l'encaissé), utilisateurs, organisations, suspensions, licences, offres, événements Stripe, journal d'audit, erreurs, demandes RGPD
- ⚠️ Paramètres « logo » et « couleur principale » enregistrés mais pas encore appliqués à l'interface (seul le nom de l'application l'est)

## Notifications, exports, RGPD
- ✅ Notifications en application ; ⚙️ par email dès qu'un SMTP est configuré
- ✅ Recherche globale limitée aux droits ; exports fiche/santé/séances/dépenses ; export RGPD JSON ; anonymisation ; purge planifiée

## Reste à configurer pour la production
1. Serveur MySQL, `.env` de production, HTTPS, cron `schedule:run`.
2. SMTP (`MAIL_*`) — indispensable pour la vérification d'email et les invitations.
3. Stripe : clés, webhook, portail client, prix des offres.
4. Premier super-administrateur + 2FA.
5. Textes légaux validés et paramètres RGPD (raison sociale, DPO, sous-traitants).
6. Sauvegardes et test de restauration.
7. Facultatif : `BRAVE_SEARCH_API_KEY`, `HORSE_SEARCH_USER_AGENT` avec contact réel.
