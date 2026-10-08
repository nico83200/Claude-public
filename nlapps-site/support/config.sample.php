<?php
/**
 * Modèle de configuration du relais d'assistance.
 *
 * Copier ce fichier HORS de la racine web, deux niveaux au-dessus de support/chat.php,
 * sous le nom nlapps-support-config.php. Exemple sur un hébergement mutualisé :
 *
 *   /home/compte/nlapps-support-config.php   ← ce fichier, renseigné
 *   /home/compte/www/                        ← racine du site (index.html, support/chat.php…)
 *
 * Alternative : variables d'environnement NLAPPS_SUPPORT_KEY et NLAPPS_SUPPORT_API.
 * Ne jamais placer la clé dans un fichier accessible depuis le navigateur.
 */
return [
    // Clé « nlh_… » créée dans la console : Applications → [application] → Parc clients → Nouveau client
    'key' => 'nlh_A_RENSEIGNER',
    // Adresse de l'API du centre d'assistance
    'api' => 'https://nlapps.fr/assistance/api.php',
];
