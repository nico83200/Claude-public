<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\Cron;

final class CronController
{
    public function run(string $token): void
    {
        $expected = (string) config('cron.token');
        if ($expected === '' || !hash_equals($expected, $token)) {
            abort(404);
        }
        header('Content-Type: text/plain; charset=utf-8');
        echo implode("\n", Cron::run()) . "\n";
    }
}
