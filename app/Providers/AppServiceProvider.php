<?php

namespace App\Providers;

use App\Models\ApplicationSetting;
use App\Models\CalendarEvent;
use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\Horse;
use App\Models\Invitation;
use App\Models\LessonCharge;
use App\Models\Treatment;
use App\Models\User;
use App\Services\CareCalendarSync;
use App\Services\CareExpenseSync;
use App\Services\CompetitionVaccines;
use App\Services\Entitlements;
use App\Services\HorseAccess;
use App\Services\IntegrationSettings;
use App\Services\LessonCosts;
use App\Services\LoadSimulation;
use App\Services\MailDesign;
use App\Services\ToValidate;
use App\Services\Upsell;
use App\Support\Installation;
use Illuminate\Auth\Access\Response;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Caches de droits remis à zéro à chaque requête (voir ResetRequestCaches).
        $this->app->scoped(Entitlements::class);
        $this->app->scoped(HorseAccess::class);
        $this->app->scoped(IntegrationSettings::class);
        $this->app->scoped(ToValidate::class);
        $this->app->scoped(CompetitionVaccines::class);
    }

    public function boot(): void
    {
        // Simulation de charge : aucun e-mail ne part vers les comptes de test (@simulation.invalid).
        Event::listen(MessageSending::class, function (MessageSending $event) {
            $to = $event->message->getTo();
            if ($to && collect($to)->every(fn ($a) => str_ends_with(strtolower($a->getAddress()), '@'.LoadSimulation::DOMAIN))) {
                return false;
            }
        });
        // Hors production : les chargements paresseux (N+1) sont journalisés pour optimisation, sans casser la page.
        Model::preventLazyLoading(! $this->app->isProduction());
        // En test : un chargement en cascade fait échouer le test (requêtes répétées à corriger par un with()).
        // En développement : simple trace dans le journal.
        if (! $this->app->runningUnitTests()) {
            Model::handleLazyLoadingViolationUsing(fn ($model, $relation) => logger()->debug('Chargement paresseux : '.get_class($model).'::'.$relation));
        }
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        Paginator::useTailwind();

        // Agenda ↔ Santé : chaque soin a son événement, chaque rendez-vous de soin réalisé a son soin (toutes sources : formulaires, assistant, factures).
        CareRecord::saved(fn ($care) => app(CareCalendarSync::class)->fromCare($care));
        // Soin ↔ dépense rattachée : date, montant et intervenant identiques des deux côtés.
        CareRecord::saved(fn ($care) => app(CareExpenseSync::class)->fromCare($care));
        Expense::updated(fn ($expense) => app(CareExpenseSync::class)->fromExpense($expense));
        CareRecord::deleted(fn ($care) => app(CareCalendarSync::class)->careDeleted($care));
        Treatment::saved(fn ($t) => app(CareCalendarSync::class)->fromTreatment($t));
        Treatment::deleted(fn ($t) => app(CareCalendarSync::class)->treatmentDeleted($t));
        CalendarEvent::created(fn ($event) => app(CareCalendarSync::class)->fromEvent($event, true));
        CalendarEvent::updated(fn ($event) => app(CareCalendarSync::class)->fromEvent($event));
        CalendarEvent::deleted(fn ($event) => app(CareCalendarSync::class)->eventDeleted($event));
        // Frais de cours : coaching d'un concours validé ; dépense recopiée dans le budget modifiée, supprimée ou rétablie.
        CalendarEvent::saved(fn ($event) => app(LessonCosts::class)->fromEvent($event));
        CalendarEvent::deleted(fn ($event) => app(LessonCosts::class)->eventDeleted($event));
        Expense::updated(fn ($expense) => app(LessonCosts::class)->fromExpense($expense));
        Expense::deleted(fn ($expense) => app(LessonCosts::class)->expenseDeleted($expense));
        Expense::restored(fn ($expense) => app(LessonCosts::class)->expenseRestored($expense));

        // HTTPS forcé seulement une fois installé et si l'adresse configurée est en https
        // (l'installeur doit rester accessible sur un domaine pas encore certifié).
        if ($this->app->isProduction() && Installation::isInstalled() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // URLs en français : /chevaux/nouveau, /chevaux/{id}/modifier
        Route::resourceVerbs(['create' => 'nouveau', 'edit' => 'modifier']);

        // Réglages email / Stripe saisis par le super-administrateur (priment sur le .env).
        if (Installation::isInstalled()) {
            rescue(fn () => app(IntegrationSettings::class)->apply(), null, false);
        }

        // Lien de confirmation d'email utilisable sans être connecté (ouvert depuis la messagerie du téléphone).
        VerifyEmail::createUrlUsing(fn ($notifiable) => URL::temporarySignedRoute(
            'verification.public', now()->addMinutes(config('auth.verification.expire', 60)),
            ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())],
        ));

        // E-mails de compte dans la même mise en forme que les notifications.
        VerifyEmail::toMailUsing(fn ($notifiable, string $url) => app(MailDesign::class)->mailMessage('verify_email', ['name' => $notifiable->name ?? null],
            'Confirmez votre adresse e-mail', 'Cliquez sur le bouton ci-dessous pour confirmer votre adresse e-mail.', $url));
        ResetPassword::toMailUsing(fn ($notifiable, string $token) => app(MailDesign::class)->mailMessage('reset_password', ['name' => $notifiable->name ?? null],
            'Réinitialisation du mot de passe', 'Vous avez demandé à réinitialiser votre mot de passe. Ce lien est valable '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60).' minutes. Si vous n\'êtes pas à l\'origine de cette demande, ignorez ce message.',
            url(route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()], false))));

        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        // Permission sur un cheval : Gate::authorize('horse', [$horse, 'health.view'])
        Gate::define('horse', function (User $user, Horse $horse, string $permission) {
            $access = app(HorseAccess::class);

            return $access->can($user, $horse, $permission)
                ? Response::allow()
                : Response::deny($access->denialReason($user, $horse, $permission));
        });

        // Permission dans une organisation : Gate::authorize('org', [$org, 'members.manage'])
        Gate::define('org', function (User $user, $organization, string $permission) {
            if (! $user->canInOrg($organization, $permission)) {
                return Response::deny('Votre rôle ne permet pas cette action dans cet espace.');
            }
            if (! str_ends_with($permission, 'view') && $permission !== 'billing.manage' && ! app(Entitlements::class)->writable($organization)) {
                return Response::deny(app(Entitlements::class)->for($organization)['read_only_reason']);
            }

            return Response::allow();
        });

        View::composer('*', function ($view) {
            static $name = null;
            $name ??= rescue(fn () => ApplicationSetting::get('app_name'), null, false) ?: config('app.name');
            $view->with('appName', $name);
        });
        View::composer('components.layouts.app', function ($view) {
            $user = auth()->user();
            $orgPerms = $user ? $user->memberships->flatMap(fn ($m) => $m->role->permissionKeys())->all() : [];
            $view->with('navPerms', $user ? array_values(array_unique([...app(HorseAccess::class)->navPermissions($user), ...$orgPerms])) : []);
            $view->with('unreadNotifications', $user ? $user->unreadNotifications()->count() : 0);
            // Partages reçus en attente : pastille sur « Partages » dans le menu et sur le bouton Menu.
            $view->with('pendingShares', $user ? Invitation::pending()->where('email', strtolower($user->email))->count() : 0);
            // Un seul cheval suivi : « Cheval » au lieu de « Chevaux » dans le menu (liste déjà lue pour les droits).
            $access = app(HorseAccess::class);
            $solo = $user ? $access->soloHorse($user) : null;
            $focus = $user ? $access->focusHorse($user) : null;
            $view->with('navHorseLabel', $solo ? 'Cheval' : 'Chevaux');
            // Cheval unique ou cheval actif : menu organisé autour du cheval (droits de la personne sur ce cheval).
            $view->with('soloHorse', $solo);
            $view->with('focusHorse', $focus);
            // « À valider » (rendez-vous et séances passés) : pastille sur l'accueil, même portée que l'accueil.
            $view->with('toValidateCount', $user ? app(ToValidate::class)->count($user, $focus) : 0);
            // Frais de cours à son nom : entrée « Frais de cours » du menu.
            $view->with('hasLessonCosts', $user ? LessonCharge::where('payer_id', $user->id)->exists() : false);
            $view->with('soloPerms', $focus ? $access->permissions($user, $focus) : []);
            // Plusieurs chevaux : choix du cheval actif en tête du menu.
            $view->with('focusChoices', $user && ! $solo && $access->hasSeveralHorses($user)
                ? $access->accessibleHorses($user)->whereNull('archived_at')->sortBy(fn ($h) => mb_strtolower($h->shortName()))->values() : collect());
            // Passage d'un cheval à plusieurs (ou l'inverse) : l'organisation change, on l'explique une fois.
            $view->with('soloTransition', $user ? self::soloTransition($user->id, $solo) : null);
            $view->with('appVersion', $user?->is_super_admin ? trim((string) @file_get_contents(base_path('VERSION'))) : null);
            $org = request()->attributes->get('organization');
            $view->with('navUpsell', $user && $org ? app(Upsell::class)->upgrade($org, $user) : null);
        });

        Gate::define('super-admin', fn (User $user) => $user->is_super_admin && ! $user->isSuspended());

        RateLimiter::for('exports', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('invitations', fn (Request $request) => Limit::perHour(30)->by($request->user()?->id ?: $request->ip()));
    }

    /**
     * Compare le mode « un cheval » à celui de la dernière page vue : 'multi' (deuxième cheval arrivé),
     * 'solo' (retour à un seul cheval) ou null. La première visite ne fait que mémoriser l'état.
     */
    public static function soloTransition(int $userId, ?Horse $solo): ?string
    {
        $key = 'jc-solo-mode:'.$userId;
        $now = $solo ? 'solo' : 'multi';
        $before = Cache::get($key);
        if ($before !== $now) {
            Cache::forever($key, $now);
        }

        return $before !== null && $before !== $now ? $now : null;
    }
}
