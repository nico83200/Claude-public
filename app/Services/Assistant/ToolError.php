<?php

namespace App\Services\Assistant;

use RuntimeException;

/** Erreur explicable renvoyée à l'IA (droits, données invalides) : l'action n'est pas réalisée. */
class ToolError extends RuntimeException {}
