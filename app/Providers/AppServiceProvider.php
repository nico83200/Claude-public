<?php

namespace App\Providers;

use App\Models\ApplicationSetting;
use App\Models\Horse;
use App\Models\User;
use App\Services\Entitlements;
use App\Services\HorseAccess;
use Illuminate\Auth\Access\Response;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
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
    }

    public function boot(): void
    {
        // Hors production : les chargements paresseux (N+1) sont journalisés pour optimisation, sans casser la page.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::handleLazyLoadingViolationUsing(fn ($model, $relation) => logger()->debug('Chargement paresseux : '.get_class($model).'::'.$relation));
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        Paginator::useTailwind();

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // URLs en français : /chevaux/nouveau, /chevaux/{id}/modifier
        Route::resourceVerbs(['create' => 'nouveau', 'edit' => 'modifier']);

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
        });

        Gate::define('super-admin', fn (User $user) => $user->is_super_admin && ! $user->isSuspended());

        RateLimiter::for('horse-search', fn (Request $request) => Limit::perMinute(config('equine.horse_search.max_per_minute'))->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('sync', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('exports', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('invitations', fn (Request $request) => Limit::perHour(30)->by($request->user()?->id ?: $request->ip()));
    }
}
