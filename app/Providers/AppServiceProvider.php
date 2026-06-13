<?php

namespace App\Providers;

use App\Listeners\LogUserLogin;
use App\Listeners\LogUserLogout;
use App\Models\DepartmentNotification;
use App\Support\DepartmentScope;
use App\Support\DocumentFormOptions;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Event::listen(Login::class, LogUserLogin::class);
        Event::listen(Logout::class, LogUserLogout::class);

        View::composer('layouts.app', function ($view) {
            $notifications = collect();
            $user = auth()->user();

            if ($user && ! DepartmentScope::isOrgWide($user) && $user->department_id
                && Schema::hasTable('department_notifications')) {
                $notifications = DepartmentNotification::query()
                    ->with('document:id,tracking_number,document_type')
                    ->where('department_id', $user->department_id)
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
                && ! $user->can('manage system')
                && Schema::hasTable('departments')
                && Schema::hasTable('routing_rules');

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
