<?php

namespace App\Services\Assistant;

use RuntimeException;

/** ElevenLabs refuse la demande : le solde de crédits du compte est insuffisant. */
class VoiceCreditsExhausted extends RuntimeException
{
    /** Message montré à l'utilisateur (le détail technique reste réservé au super-administrateur). */
    public const USER_MESSAGE = 'Le service vocal (ElevenLabs) n\'a plus assez de crédits pour le moment. L\'administrateur de Jack&Cie a été prévenu.';
}
