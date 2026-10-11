<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        // Role-based access control: admin accounts reach the reporting area.
        Gate::define('admin', fn (User $user) => $user->isAdmin());

        // Render pagination with Bootstrap 5 markup.
        Paginator::useBootstrapFive();
    }
}
