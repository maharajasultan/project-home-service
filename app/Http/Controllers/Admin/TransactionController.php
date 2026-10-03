<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Services\MidtransService;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionController extends Controller
{
    private const PROCESSING = ['paid', 'on_the_way', 'in_progress'];

    public function __construct(private readonly OrderService $orders)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'tab' => ['nullable', 'in:all,pending,processing,completed,failed'],
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $this->orders->expireOverdue();

        $current = $filters['tab'] ?? 'all';
        $term = trim((string) ($filters['q'] ?? ''));

        $statuses = match ($current) {
            'pending' => ['pending'],
            'processing' => self::PROCESSING,
            'completed' => ['completed'],
            'failed' => ['failed'],
            default => null,
        };

        $orders = Order::with(['user', 'technician'])
            ->when($statuses, fn ($q) => $q->whereIn('status', $statuses))
            ->when($term !== '', fn ($q) => $q->where(
                fn ($w) => $w->where('order_code', 'like', "%{$term}%")
                    ->orWhere('phone_wa', 'like', "%{$term}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%"))
            ))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $by = Order::toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $sum = fn (array $keys) => (int) collect($keys)->sum(fn ($k) => $by[$k] ?? 0);

        $tabs = [
            'all' => ['label' => 'Semua', 'count' => (int) $by->sum()],
            'pending' => ['label' => 'Menunggu Pembayaran', 'count' => $sum(['pending'])],
            'processing' => ['label' => 'Diproses', 'count' => $sum(self::PROCESSING)],
            'completed' => ['label' => 'Selesai', 'count' => $sum(['completed'])],
            'failed' => ['label' => 'Gagal', 'count' => $sum(['failed'])],
        ];

        return view('admin.transactions.index', compact('orders', 'tabs', 'current'));
    }

    public function show(int $id): View
    {
        $this->orders->expireOverdue();

        $order = Order::with([
            'user', 'technician.technicianProfile', 'items.product', 'payment',
            'photos', 'warrantyClaims', 'reviews.user',
        ])->findOrFail($id);

        $productNames = Product::whereIn(
            'id',
            $order->reviews->where('target_type', 'product')->pluck('target_id')
        )->pluck('name', 'id');

        $needsManualRefund = $order->status->value === 'failed'
            && $order->payment?->status === 'settlement';

        return view('admin.transactions.show', compact('order', 'productNames', 'needsManualRefund'));
    }

    /** Batalkan pesanan yang belum dibayar (stok kembali, slot bebas, tagihan Midtrans dimatikan). */
    public function cancel(int $id, MidtransService $midtrans): RedirectResponse
    {
        $order = Order::with('payment')->findOrFail($id);

        try {
            $this->orders->cancelByUser($order);
        } catch (BusinessException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($order->payment) {
            $midtrans->expireQuietly($order->payment->midtrans_order_id);
        }

        return back()->with('success', "Pesanan {$order->order_code} dibatalkan. Stok dan jadwal teknisi dikembalikan.");
    }
}