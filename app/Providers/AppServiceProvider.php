<?php

namespace App\Providers;

use App\Enums\RoleCode;
use App\Models\EvaluationAssignment;
use App\Models\EvaluationCycle;
use App\Models\ReminderLog;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->defineAbilities();

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            Str::lower((string) $request->input('work_email')).'|'.$request->ip()
        ));

        View::composer('partials.navbar', function ($view): void {
            $user = auth()->user();
            $view->with('notifications', $user
                ? ReminderLog::query()
                    ->with('assignment.cycle.vendor', 'rule')
                    ->where('recipient_id', $user->id)
                    ->where('channel', 'In_App')
                    ->where('scheduled_at', '>=', now()->subDays(7))
                    ->latest('scheduled_at')
                    ->limit(8)
                    ->get()
                : collect());
        });
    }

    /**
     * Matriks peran dan hak akses (Tabel 3.5, FR-22).
     */
    private function defineAbilities(): void
    {
        $vm = RoleCode::VmSpecialist;
        $manager = RoleCode::PvmManager;
        $consulted = RoleCode::User;

        Gate::define('manage-master', fn (User $user): bool => $user->hasRole($vm));
        Gate::define('manage-cycles', fn (User $user): bool => $user->hasRole($vm));
        Gate::define('approve-evaluation', fn (User $user): bool => $user->hasRole($manager));
        Gate::define('view-all-evaluations', fn (User $user): bool => $user->hasRole($vm, $manager, $consulted));
        Gate::define('manage-followups', fn (User $user): bool => $user->hasRole($vm));
        Gate::define('view-followups', fn (User $user): bool => $user->hasRole($vm, $manager, $consulted));
        Gate::define('approve-decision', fn (User $user): bool => $user->hasRole($manager));
        Gate::define('export-reports', fn (User $user): bool => $user->hasRole($vm, $manager));
        Gate::define('view-audit-logs', fn (User $user): bool => $user->hasRole($vm, $manager));
        Gate::define('fill-assessments', fn (User $user): bool => $user->hasRole(RoleCode::Assessor));

        Gate::define('view-cycle', fn (User $user, EvaluationCycle $cycle): bool => $user->hasRole($vm, $manager, $consulted)
            || $cycle->assignments()->where('assessor_id', $user->id)->exists());

        Gate::define('view-assignment', fn (User $user, EvaluationAssignment $assignment): bool => $assignment->assessor_id === $user->id
            || $user->hasRole($vm, $manager));

        Gate::define('fill-assignment', fn (User $user, EvaluationAssignment $assignment): bool => $assignment->assessor_id === $user->id
            && $user->hasRole(RoleCode::Assessor));

        Gate::define('reopen-assignment', fn (User $user, EvaluationAssignment $assignment): bool => $assignment->assessor_id === $user->id
            || $user->hasRole($vm));
    }
}
