<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinanceTransaction;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WarrantyClaim;
use App\Services\OrderService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(OrderService $orders): View
    {
        $orders->expireOverdue();

        $byStatus = Order::toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $count = fn (string ...$statuses) => (int) collect($statuses)->sum(fn ($s) => $byStatus[$s] ?? 0);

        $income = fn () => FinanceTransaction::where('type', 'in');

        $stats = [
            'orders_total' => (int) $byStatus->sum(),
            'orders_pending' => $count('pending'),
            'orders_processing' => $count('paid', 'on_the_way', 'in_progress'),
            'orders_completed' => $count('completed'),
            'orders_failed' => $count('failed'),
            'revenue_total' => (int) $income()->sum('amount'),
            'revenue_month' => (int) $income()->where('transaction_date', '>=', now()->startOfMonth()->toDateString())->sum('amount'),
            'revenue_today' => (int) $income()->where('transaction_date', now()->toDateString())->sum('amount'),
            'customers' => User::where('role', 'user')->count(),
            'technicians' => User::where('role', 'technician')->where('is_active', true)->count(),
            'pending_claims' => WarrantyClaim::where('status', 'pending')->count(),
            'low_stock' => ProductVariant::purchasable()
                ->whereHas('product', fn ($q) => $q->where('type', 'sparepart'))
                ->where('stock', '<=', 3)
                ->count(),
        ];

        // Grafik pendapatan 14 hari terakhir (hari tanpa transaksi tetap tampil 0).
        $from = now()->subDays(13)->startOfDay();

        $rows = $income()
            ->where('transaction_date', '>=', $from->toDateString())
            ->selectRaw('transaction_date as d, sum(amount) as total')
            ->groupBy('transaction_date')
            ->pluck('total', 'd');

        $labels = [];
        $values = [];

        for ($i = 0; $i < 14; $i++) {
            $day = $from->copy()->addDays($i);
            $labels[] = $day->format('d/m');
            $values[] = (int) ($rows[$day->toDateString()] ?? 0);
        }

        return view('admin.dashboard', [
            'stats' => $stats,
            'revenueChart' => ['labels' => $labels, 'values' => $values],
            'statusChart' => [
                'labels' => ['Menunggu Pembayaran', 'Diproses', 'Selesai', 'Gagal'],
                'values' => [
                    $stats['orders_pending'],
                    $stats['orders_processing'],
                    $stats['orders_completed'],
                    $stats['orders_failed'],
                ],
            ],
            'recentOrders' => Order::with(['user', 'technician'])->latest('id')->limit(8)->get(),
            'pendingClaims' => WarrantyClaim::with(['order', 'user'])
                ->where('status', 'pending')->latest('id')->limit(5)->get(),
        ]);
    }
}