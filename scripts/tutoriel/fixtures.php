<?php
// Données de démonstration pour le tournage (base de développement uniquement).
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\{User, Horse, RecurringExpense, Expense, ExpenseCategory, RidingSession, PushSubscription};
$u = User::where('email', 'proprietaire@demo.jackcie.test')->firstOrFail();
$uni = Horse::find(1);
PushSubscription::query()->delete();
Expense::whereNotNull('recurring_expense_id')->forceDelete(); RecurringExpense::withTrashed()->forceDelete();
RidingSession::where('instructor', 'Jimmy')->get()->each->delete();
$rec = new RecurringExpense(['horse_id' => $uni->id, 'expense_category_id' => ExpenseCategory::where('name', 'Pension')->value('id'), 'label' => 'Pension Écurie des Tilleuls', 'amount' => 380, 'frequency' => 'monthly', 'starts_on' => now()->subMonths(2)->day(5)->toDateString(), 'next_due_on' => now()->subMonths(2)->day(5)->toDateString()]);
$rec->organization_id = $uni->organization_id; $rec->author_id = $u->id; $rec->save();
app(App\Services\RecurringExpenses::class)->generate($rec, notify: false);
$rec->occurrences()->orderBy('spent_on')->first()->update(['paid_on' => now()->subMonths(2)->day(6)]);
app(App\Services\RidingSessionService::class)->create($uni, $u, ['scheduled_at' => now()->setTime(9, 0), 'session_type' => 'group_lesson', 'instructor' => 'Jimmy', 'planned_minutes' => 60, 'status' => 'planned', 'objective' => 'Équilibre au galop']);
App\Models\AssistantRequest::where('user_id', $u->id)->delete();
// Tournage : passer « 0 » pour masquer le lien Administration du compte de démonstration, « 1 » pour le rétablir.
if (isset($argv[1])) { $u->forceFill(["is_super_admin" => (bool) $argv[1]])->save(); }
echo "ok\n";
