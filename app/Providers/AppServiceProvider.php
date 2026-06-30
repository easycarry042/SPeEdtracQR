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
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Provider-agnostic LLM backend for the document assistant (Pillar 3).
        $this->app->singleton(LlmProvider::class, function () {
            return match (config('ai.provider')) {
                'ollama' => new OllamaProvider(
                    config('ai.ollama.url'),
                    config('ai.ollama.model'),
                    config('ai.ollama.timeout'),
                    config('ai.ollama.keep_alive'),
                ),
                default => new NullProvider,
            };
        });
    }

    public function boot(): void
    {
        Event::listen(Login::class, LogUserLogin::class);
        Event::listen(Logout::class, LogUserLogout::class);

        View::composer('layouts.app', function ($view) {
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
