# ✈ VoyageStudio

Outil web d'aide à la création de voyages pour agent de voyage indépendant : comparer des destinations, des vols, des croisières, des durées et des transferts, puis calculer un tarif rentable et produire un devis client.

Conçu pour un **hébergement mutualisé** (OVH, o2switch, Ionos, Hostinger…) : PHP 8.1+, SQLite ou MySQL, aucune commande à lancer sur le serveur. On installe par FTP et un assistant fait le reste.

---

## Fonctionnalités

### Dossiers de voyage
- Fiche dossier : client, destination, dates, participants (adultes / enfants / bébés), budget, demande du client, statut (demande → devis → option → confirmé → soldé).
- **Variantes** : plusieurs propositions par dossier (ex. croisière balcon ou cabine intérieure, avec ou sans nuit d'hôtel), dupliquées en un clic.
- **Prestations** : vol, train, hébergement, croisière, transfert, location, excursion, assurance, visa, frais. Chaque type a ses champs propres : n° de vol, escales, bagages, navire, cabine, formule (BB/HB/FB/AI), mode de transfert, distance…
- **Durées réelles avec les fuseaux horaires** : saisissez les horaires locaux et les codes IATA ; le fuseau est déduit de l'aéroport (environ 250 aéroports intégrés), changements d'heure compris.
- Frise chronologique jour par jour, avec les statuts de réservation (à demander, sous option, confirmé) et les dates limites d'option des fournisseurs.
- **Programme jour par jour** généré à partir des prestations, puis modifiable.
- **Voyageurs** : contrôle de validité des passeports (6 mois après le retour pour les voyages hors UE).
- **Échéancier** : acompte et solde générés automatiquement, encaissements clients et paiements fournisseurs.
- **Dupliquer un dossier** pour s'en servir comme modèle pour un nouveau client.

### Tarification (moteur testé)
- Achat **en net**, avec votre marge : majoration %, taux de marque %, montant fixe par personne ou par dossier, ou majoration propre à une prestation.
- Achat **commissionné** : vente au prix public, la commission est votre marge.
- **Taxes non commissionnables et non majorées** (aéroport, taxes portuaires, taxe de séjour) : refacturées au coût.
- Unités : forfait, par personne, par nuit, par personne et par nuit, par chambre ou cabine, par véhicule, par jour. Quantités calculées automatiquement.
- **Devises** : taux de la BCE mis à jour en un clic, plus une marge de sécurité de change paramétrable.
- Frais de dossier, **arrondi commercial** du prix par personne.
- **TVA sur la marge** (régime particulier des agences, art. 266-1-e du CGI), exonérée hors UE ; ou **franchise en base** pour les micro-entrepreneurs.
- Rentabilité en direct : achat, vente, marge brute, taux de marque, coefficient, TVA, marge nette, prix par personne et par nuit, écart avec le budget du client.

### Comparateurs
- **Comparatif des variantes** : prix, temps de vol, escales, transferts, temps de transport, confort, marges. Le meilleur résultat de chaque ligne est mis en évidence. Une vue client sans marges peut être imprimée.
- **Comparateur de destinations** (« Où partir en mars ? ») : saisonnalité mois par mois, budget sur place, durée de vol, décalage horaire, formalités.
- **Comparateur rapide d'offres** (Outils) : comparez des offres trouvées sur différents sites, avec un score pondéré (prix, durée, escales, confort).

### Suggestions « anti-oubli »
Un contrôle automatique et instantané de chaque variante signale notamment :
- un transport aller ou retour manquant, des **nuits sans hébergement** ;
- un **transfert manquant** à l'arrivée ou au départ d'un vol ;
- une **correspondance serrée** sur des billets séparés, ou des horaires incohérents ;
- une arrivée le jour de l'embarquement en croisière (une nuit avant la croisière est conseillée) ;
- une arrivée tardive ou matinale à l'hôtel ;
- une **assurance non proposée** (devoir de conseil), un visa ou un ESTA, des passeports bientôt expirés ;
- des prix ou des taux de change manquants, une marge faible ou négative, un budget dépassé ;
- des options fournisseurs sur le point d'expirer, des idées de ventes additionnelles.

Chaque suggestion propose un bouton **« + Ajouter »** qui ouvre la prestation déjà pré-remplie.

### ✨ Assistant IA (optionnel, Claude d'Anthropic)
- **Import par capture d'écran** : glissez ou collez (Ctrl+V) une capture d'un site de compagnie, d'un comparateur ou d'un extranet hôtelier, un **PDF de confirmation** ou le texte d'un e-mail. L'IA en extrait les prestations (vols aller et retour, hôtel, croisière, transfert…), avec les dates, les horaires, les aéroports, les prix, les devises et le PNR. Vous les relisez avant de les ajouter. Les fournisseurs déjà connus sont reconnus automatiquement.
- **Analyse IA du voyage** : une relecture complète du dossier qui propose des suggestions détaillées, en plus des règles automatiques.

### Devis client
Une proposition soignée, à imprimer ou à enregistrer en PDF avec le navigateur. Elle contient votre logo et votre couleur, le programme, les prestations, le prix par personne et le prix total, les options, « comprend / ne comprend pas », l'échéancier, les formalités, les conditions et les **mentions légales** (immatriculation Atout France, garant financier, RC professionnelle).

### Gestion
- Tableau de bord : chiffre d'affaires et marge de l'année, devis en cours, taux de transformation, alertes (options qui expirent, échéances, prestations à réserver, passeports), prochains départs.
- Clients (documents de voyage, préférences, historique), fournisseurs (commission par défaut, conditions), destinations (formalités, climat, saisonnalité).
- Recherche globale (raccourci `/`), exports CSV compatibles Excel, sauvegarde et restauration au format JSON.
- **Mise à jour en un clic** : déposez le ZIP d'une nouvelle version dans *Réglages › Mise à jour*.

---

## Installation sur un hébergement mutualisé

1. Décompressez `voyagestudio-X.Y.Z.zip`, puis envoyez le dossier `voyagestudio/` par FTP (par exemple dans `www/voyages/`).
2. Ouvrez `https://votre-domaine.fr/voyages/` : l'assistant d'installation s'affiche.
3. Choisissez **SQLite**, recommandé et sans aucun réglage, ou MySQL (identifiants fournis par votre hébergeur). Créez votre compte et, si vous le souhaitez, collez votre clé API Anthropic. Vous pouvez aussi charger les données de démonstration.
4. C'est prêt. Activez **HTTPS** chez votre hébergeur, puis décommentez les 2 lignes « Forcer HTTPS » du fichier `.htaccess`.

**Prérequis** : PHP ≥ 8.1 avec PDO (SQLite ou MySQL), mbstring et SimpleXML. Pour les mises à jour en ligne, l'extension zip. Pour l'IA et les taux BCE, l'accès sortant HTTPS (curl). Apache avec `.htaccess` : les dossiers `app/`, `data/` et `vendor/` sont protégés. Sous Nginx, interdisez l'accès à ces dossiers dans la configuration.

## Activer l'assistant IA

1. Créez une clé API sur <https://console.anthropic.com> (facturation à l'usage : quelques centimes par import).
2. Ouvrez `config.php` par FTP et renseignez la clé :
   ```php
   'ai' => [
       'api_key' => 'sk-ant-…',
       'model' => 'claude-opus-5-5',
       'effort_extract' => 'low',
       'effort_suggest' => 'medium',
   ],
   ```
3. Vérifiez dans *Réglages › Assistant IA* que les deux voyants sont verts.

La clé reste sur votre serveur : elle n'est jamais envoyée au navigateur. **Ne la publiez jamais** dans un dépôt Git (`config.php` est exclu par `.gitignore`).

## Mises à jour

*Réglages › Mise à jour* : déposez le ZIP d'une nouvelle version. L'application :
1. vérifie que le paquet est complet et que sa version est valide ;
2. **sauvegarde automatiquement** les fichiers (`data/backups/app-….zip`) et la base (`data/backups/base-….json`) ;
3. installe les nouveaux fichiers sans toucher à `config.php` ni à `data/` ;
4. met à jour la structure de la base au chargement suivant.

Si votre hébergeur limite la taille des fichiers envoyés, utilisez le paquet `-sans-vendor.zip` : le dossier `vendor/` déjà installé est conservé. Pour revenir en arrière, déposez la sauvegarde `app-….zip` en cochant « Autoriser le retour à une version antérieure ».

## Sécurité
- Mots de passe hachés (`password_hash`), sessions `HttpOnly` / `SameSite=Strict`, jeton CSRF sur chaque modification, limitation des tentatives de connexion.
- Requêtes SQL préparées, champs vérifiés par le schéma, en-têtes CSP stricts (aucun script externe).
- Exports CSV protégés contre l'injection de formules ; ZIP de mise à jour contrôlés (liste blanche, protection contre le *zip-slip*).

## Développement

```
app/        PHP : Schema (source unique du modèle), Database (migrations SQLite/MySQL), Repository (CRUD),
            Api (routeur JSON), Auth, Dashboard, Rates (BCE), AiAssistant (Claude), Updater, Demo
assets/js/  SPA sans build : pricing.js (moteur de prix), time.js (fuseaux), checks.js (suggestions),
            airports.js, ui.js, api.js, app.js, views/
tests/      api_test.php (intégration API) · pricing.test.mjs (moteur de prix, fuseaux, suggestions)
scripts/    build.sh (génère les ZIP dans dist/)
```

```bash
composer install                  # SDK Anthropic (pour l'assistant IA)
php tests/api_test.php            # tests API
node --test tests/pricing.test.mjs
php -S 127.0.0.1:8000             # puis ouvrir http://127.0.0.1:8000/install.php
scripts/build.sh                  # paquets de distribution
```

Pour publier une nouvelle version, incrémentez `VERSION`, puis lancez `scripts/build.sh`.

> Les règles fiscales (TVA sur marge) et les formalités sont données à titre indicatif : faites-les valider par votre expert-comptable et vérifiez-les sur diplomatie.gouv.fr.
