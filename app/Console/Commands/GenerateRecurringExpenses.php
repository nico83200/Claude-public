<?php

namespace App\Console\Commands;

use App\Models\RecurringExpense;
use App\Services\RecurringExpenses;
use Illuminate\Console\Command;

class GenerateRecurringExpenses extends Command
{
    protected $signature = 'expenses:recurring';

    protected $description = 'Crée les échéances arrivées des dépenses récurrentes (à payer)';

    public function handle(RecurringExpenses $service): int
    {
        $count = 0;
        RecurringExpense::whereNull('paused_at')->whereDate('next_due_on', '<=', today())->each(function ($rec) use ($service, &$count) {
            $count += $service->generate($rec);
        });
        $this->info("$count échéance(s) créée(s).");

        return self::SUCCESS;
    }
}
