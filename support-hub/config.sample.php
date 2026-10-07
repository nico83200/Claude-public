<?php
/**
 * Centre d'assistance NLapps — copier en config.php (facultatif : les valeurs par défaut conviennent).
 * Le mot de passe de la console se choisit à la première connexion.
 */
return [
    'operator_name' => 'NLapps',                 // nom affiché aux utilisateurs (« NLapps vous répond »)
    'notify_email'  => 'contact@nlapps.fr',      // alerte à chaque nouvelle conversation ou nouveau message
    'from_email'    => 'assistance@nlapps.fr',   // expéditeur des alertes
    'ntfy_url'      => '',                       // facultatif : notification sur le téléphone via ntfy.sh, ex. https://ntfy.sh/nlapps-xxxx
    'timezone'      => 'Europe/Paris',
];
