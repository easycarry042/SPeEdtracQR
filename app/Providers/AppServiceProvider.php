<?php

namespace App\Providers;

use App\Support\Ai\LlmProvider;
use App\Support\Ai\NullProvider;
use App\Support\Ai\OllamaProvider;
use App\Support\DocumentFormOptions;
use App\Support\InternalRequestForm;
use App\Support\StaleViteHotFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
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
        // A leftover public/hot from a dev server that was killed rather than
        // stopped would point every page at a dead port and render the site
        // unstyled. Local-only: production never runs a dev server, and the
        // probe is a sub-millisecond loopback connect.
        if ($this->app->environment('local') && ! $this->app->runningInConsole()
            && StaleViteHotFile::isStale(Vite::hotFile())) {
            Vite::useHotFile(storage_path('framework/vite-hot-disabled'));
        }

        // LogUserLogin / LogUserLogout are picked up by listener discovery
        // (app/Listeners). Registering them here as well logged every sign-in
        // and sign-out twice.

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

            // "File Request" lives in the header, so its form rides every page.
            $view->with('fileRequestForm', InternalRequestForm::canFile($user)
                ? ['department' => $user->department] + InternalRequestForm::data()
                : null);
        });
    }
}
