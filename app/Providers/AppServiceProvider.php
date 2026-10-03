<?php

namespace App\Providers;

use App\Models\WarrantyClaim;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use App\Models\ChatMessage;

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

            // Pesan pelanggan ke admin yang belum dibaca (chat umum).
            $view->with('unreadChatCount', ChatMessage::whereNull('order_id')
                ->whereNull('read_at')
                ->whereHas('sender', fn($q) => $q->where('role', 'user'))
                ->count());
        });
    }
}
