<?php

namespace App\Providers;

use App\Models\WarrantyClaim;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Angka badge "Klaim Garansi" di sidebar.
        View::composer('admin.layouts.app', function ($view) {
            $view->with('pendingClaimsCount', WarrantyClaim::where('status', 'pending')->count());
        });
    }
}