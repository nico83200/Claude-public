<?php

namespace App\Services\Sync;

use RuntimeException;

/** Opération refusée définitivement (droits, validation, licence). */
class SyncRejected extends RuntimeException {}
