<?php

namespace App\Services\Assistant;

use RuntimeException;

/** ElevenLabs refuse la demande : le solde de crédits du compte est insuffisant. */
class VoiceCreditsExhausted extends RuntimeException
{}
