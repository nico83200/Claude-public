<?php

namespace App\Console\Commands;

use App\Models\Horse;
use App\Models\RecurringExpense;
use App\Models\RecurringIncome;
use App\Services\RecurringExpenses;
use App\Services\RecurringIncomes;
use Illuminate\Console\Command;

class GenerateRecurringExpenses extends Command
{
    protected $signature = 'expenses:recurring';

    protected $description = 'Crée les échéances arrivées des dépenses récurrentes (à payer) et des recettes récurrentes (à recevoir)';

    public function handle(RecurringExpenses $service, RecurringIncomes $incomes): int
    {
        $count = 0;
        // Cheval supprimé : plus d'échéances générées.
        RecurringExpense::whereNull('paused_at')->whereDate('next_due_on', '<=', today())
            ->where(fn ($q) => $q->whereNull('horse_id')->orWhereIn('horse_id', Horse::query()->select('horses.id')))->each(function ($rec) use ($service, &$count) {
                $count += $service->generate($rec);
            });
        RecurringIncome::whereNull('paused_at')->whereDate('next_due_on', '<=', today())
            ->whereIn('horse_id', Horse::query()->select('horses.id'))->each(function ($rec) use ($incomes, &$count) {
                $count += $incomes->generate($rec);
            });
        $this->info("$count échéance(s) créée(s).");

        return self::SUCCESS;
    }
}
