<?php

namespace App\Providers;

use App\Listeners\LogUserLogin;
use App\Listeners\LogUserLogout;
use App\Support\Ai\LlmProvider;
use App\Support\Ai\NullProvider;
use App\Support\Ai\OllamaProvider;
use App\Support\DocumentFormOptions;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Facades\Health;

class AppServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        // Provider-agnostic LLM backend for the document assistant (Pillar 3).
        $this->app->singleton(LlmProvider::class, fn () => match (config('ai.provider')) {
            'ollama' => new OllamaProvider(
                config('ai.ollama.url'),
                config('ai.ollama.model'),
                config('ai.ollama.timeout'),
                config('ai.ollama.keep_alive'),
                config('ai.ollama.num_predict'),
            ),
            default => new NullProvider,
        });
    }

    public function boot(): void
    {
        Event::listen(Login::class, LogUserLogin::class);
        Event::listen(Logout::class, LogUserLogout::class);

        // Laravel Pulse dashboard (/pulse) — restrict to system admins so it is
        // safe outside local (Pulse defaults to local-only without this gate).
        Gate::define('viewPulse', fn ($user): bool => (bool) $user->can('manage system'));

        // Runtime health probes surfaced at /health (see routes/web.php) and
        // stored every 5 min by the scheduler. DebugMode/Environment expect a
        // production posture, so they flag until the app is configured for the
        // municipality handover — which is exactly when we want the reminder.
        Health::checks([
            DatabaseCheck::new(),
            UsedDiskSpaceCheck::new(),
            ScheduleCheck::new(),
            DebugModeCheck::new(),
            EnvironmentCheck::new(),
        ]);

        View::composer('layouts.app', function ($view): void {
            $user = auth()->user();

            // Header notifications are slated for an assignment-based rebuild
            // (the parked notifications / AI-router seam); empty for now.
            $view->with('headerNotifications', collect());

            // Data for the "New Submission" modal rendered in the layout.
            // System admins manage the org and do not create submissions.
            $canCreateDocuments = $user
                && $user->can('create documents')
                && ! $user->can('manage system');

            $view->with('showCreateDocumentModal', $canCreateDocuments);

            if ($canCreateDocuments) {
                $view->with('createModalCategories', DocumentFormOptions::categoryOptions());
            }
        });
    }
}
