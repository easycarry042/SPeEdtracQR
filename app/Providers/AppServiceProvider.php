<?php

namespace App\Providers;

use App\Listeners\LogUserLogin;
use App\Listeners\LogUserLogout;
use App\Models\DepartmentNotification;
use App\Support\AssignmentScope;
use App\Support\Ai\LlmProvider;
use App\Support\Ai\NullProvider;
use App\Support\Ai\OllamaProvider;
use App\Support\DocumentFormOptions;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
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
            $notifications = collect();
            $user = auth()->user();

            if ($user && Schema::hasTable('department_notifications')) {
                $notifications = DepartmentNotification::query()
                    ->with('document:id,tracking_number,document_type')
                    ->whereHas('document', fn ($q) => AssignmentScope::applyDocumentScope($q, $user))
                    ->whereNull('read_at')
                    ->latest()
                    ->take(20)
                    ->get();
            }

            $view->with('headerNotifications', $notifications);

            // Data for the "New Submission" modal rendered in the layout.
            // System admins manage the org and do not create submissions.
            $canCreateDocuments = $user
                && $user->can('create documents')
                && ! $user->can('manage system');

            $view->with('showCreateDocumentModal', $canCreateDocuments);

            if ($canCreateDocuments) {
                $view->with([
                    'createModalDepartments' => DocumentFormOptions::departments(),
                    'createModalDefaultRoutes' => DocumentFormOptions::defaultRoutesByType(),
                    'createModalCategories' => DocumentFormOptions::categoryOptions(),
                ]);
            }
        });
    }
}
