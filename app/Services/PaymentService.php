<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ProductType;
use App\Exceptions\BusinessException;
use App\Models\FinanceTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\TechnicianSchedule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    public const METHOD_LABELS = [
        'bca_va' => 'BCA Virtual Account',
        'echannel' => 'Mandiri Bill Payment',
        'bri_va' => 'BRI Virtual Account',
        'bni_va' => 'BNI Virtual Account',
        'indomaret' => 'Indomaret',
        'alfamart' => 'Alfamart',
    ];

    private const FAILED_STATUSES = ['expire', 'cancel', 'deny', 'failure'];

    public function __construct(
        private readonly MidtransService $midtrans,
        private readonly OrderService $orders,
    ) {
    }

    /**
     * Buat (atau ambil kembali) transaksi Snap untuk pesanan.
     *
     * @throws BusinessException
     */
    public function pay(Order $order): Payment
    {
        $this->orders->expireOverdue();

        return DB::transaction(function () use ($order) {
            // Kunci baris pesanan agar dua request /pay bersamaan tidak membuat dua transaksi.
            $locked = Order::with(['items', 'user'])->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::Pending) {
                throw new BusinessException(
                    'Pesanan ini tidak dapat dibayar. Mungkin sudah dibayar, dibatalkan, atau melewati batas waktu.'
                );
            }

            $remaining = (int) now()->diffInSeconds($locked->expired_at, false);

            if ($remaining <= 0) {
                throw new BusinessException('Batas waktu pembayaran telah habis.');
            }

            $existing = Payment::where('order_id', $locked->id)->latest('id')->first();

            if ($existing) {
                if ($existing->status === 'pending' && $existing->snap_token) {
                    return $existing; // idempotent: tautan yang sama
                }

                throw new BusinessException('Transaksi pembayaran pesanan ini bermasalah. Hubungi admin.');
            }

            $snap = $this->midtrans->createSnapTransaction($this->buildParams($locked, $remaining));

            return Payment::create([
                'order_id' => $locked->id,
                'midtrans_order_id' => $locked->order_code,
                'status' => 'pending',
                'gross_amount' => $locked->total,
                'snap_token' => $snap['token'],
                'redirect_url' => $snap['redirect_url'],
            ]);
        });
    }

    /** Tanya status ke Midtrans lalu proses seperti notifikasi (dipakai Flutter setelah WebView ditutup). */
    public function sync(Order $order): void
    {
        $this->orders->expireOverdue();

        $payment = Payment::where('order_id', $order->id)->latest('id')->first();

        if (! $payment) {
            return;
        }

        $data = $this->midtrans->fetchStatus($payment->midtrans_order_id);

        if ($data !== null) {
            $this->handleNotification($data);
        }
    }

    /**
     * Proses notifikasi Midtrans (webhook atau hasil sync). Aman dipanggil berulang.
     * Pemanggil webhook WAJIB memverifikasi signature lebih dulu.
     */
    public function handleNotification(array $n): ?Payment
    {
        $midtransOrderId = (string) ($n['order_id'] ?? '');
        $status = $this->normalizeStatus($n);

        return DB::transaction(function () use ($n, $midtransOrderId, $status) {
            $payment = Payment::where('midtrans_order_id', $midtransOrderId)->lockForUpdate()->first();

            if (! $payment) {
                Log::info('Midtrans: order_id tidak dikenal, diabaikan.', ['order_id' => $midtransOrderId]);

                return null;
            }

            $amount = (int) round((float) ($n['gross_amount'] ?? 0));

            if ($amount !== (int) $payment->gross_amount) {
                Log::critical('Midtrans: nominal tidak cocok.', [
                    'order_id' => $midtransOrderId,
                    'expected' => $payment->gross_amount,
                    'received' => $amount,
                ]);

                return null;
            }

            // Status "settlement" tidak boleh diturunkan oleh notifikasi susulan.
            if ($payment->status === 'settlement'
                && in_array($status, ['pending', ...self::FAILED_STATUSES], true)) {
                return $payment;
            }

            $payment->fill([
                'status' => $status,
                'method' => $this->resolveMethod($n) ?? $payment->method,
                'payload' => Arr::except($n, ['signature_key']),
            ])->save();

            if ($status === 'settlement') {
                $this->markPaid($payment);
            } elseif (in_array($status, self::FAILED_STATUSES, true)) {
                $this->orders->release($payment->order_id); // stok kembali, slot bebas
            }

            return $payment->refresh();
        });
    }

    public function methodLabel(?string $method): string
    {
        return self::METHOD_LABELS[$method] ?? ($method ?: 'Midtrans');
    }

    // ------------------------------------------------------------------

    private function markPaid(Payment $payment): void
    {
        $order = Order::with('items.product')->whereKey($payment->order_id)->lockForUpdate()->first();

        if (! $order) {
            return;
        }

        // Uang masuk setelah pesanan ditandai gagal (balapan di detik terakhir).
        if ($order->status === OrderStatus::Failed && ! $this->revive($order)) {
            Log::critical('Pembayaran masuk untuk pesanan gagal yang tidak bisa dihidupkan. PERLU REFUND MANUAL.', [
                'order_code' => $order->order_code,
                'amount' => $order->total,
            ]);

            return;
        }

        if ($order->status !== OrderStatus::Pending) {
            return; // sudah diproses (notifikasi ganda)
        }

        $now = now();

        $order->update(['status' => OrderStatus::Paid, 'paid_at' => $now]);
        $payment->update(['paid_at' => $now]);

        // Barang keluar (stok sudah dikurangi saat pesanan dibuat; ini catatan untuk laporan inventory).
        foreach ($order->items as $item) {
            if ($item->product_variant_id && $item->product?->type === ProductType::Sparepart) {
                StockMovement::create([
                    'product_variant_id' => $item->product_variant_id,
                    'order_id' => $order->id,
                    'type' => 'out',
                    'qty' => $item->qty,
                    'note' => "Terjual - pesanan {$order->order_code}",
                ]);
            }
        }

        // Uang masuk untuk laporan keuangan.
        FinanceTransaction::create([
            'order_id' => $order->id,
            'type' => 'in',
            'amount' => $order->total,
            'description' => "Pembayaran pesanan {$order->order_code} via ".$this->methodLabel($payment->method),
            'transaction_date' => $now->toDateString(),
        ]);
    }

    /** Hidupkan kembali pesanan gagal jika stok dan slot teknisi masih tersedia. */
    private function revive(Order $order): bool
    {
        $at = $order->scheduled_at;

        if (! $order->technician_id || ! $at || $at->isPast()) {
            return false;
        }

        $variantIds = $order->items->pluck('product_variant_id')->filter()->all();
        $variants = ProductVariant::whereIn('id', $variantIds)->lockForUpdate()->get()->keyBy('id');

        $spareparts = $order->items->filter(
            fn ($item) => $item->product_variant_id && $item->product?->type === ProductType::Sparepart
        );

        foreach ($spareparts as $item) {
            $variant = $variants->get($item->product_variant_id);

            if (! $variant || $variant->stock < $item->qty) {
                return false;
            }
        }

        try {
            TechnicianSchedule::create([
                'technician_id' => $order->technician_id,
                'order_id' => $order->id,
                'date' => $at->toDateString(),
                'time_slot' => $at->format('H:i:s'),
            ]);
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                return false; // slot sudah diambil orang lain
            }

            throw $e;
        }

        foreach ($spareparts as $item) {
            $variants->get($item->product_variant_id)->decrement('stock', $item->qty);
        }

        $order->update(['status' => OrderStatus::Pending]);

        return true;
    }

    private function buildParams(Order $order, int $remainingSeconds): array
    {
        $items = $order->items->map(fn ($item) => [
            'id' => 'ITEM-'.$item->id,
            // Midtrans: harga item = harga barang + ongkir/pengecekan, supaya total item = gross_amount.
            'price' => (int) $item->price + (int) $item->base_fee,
            'quantity' => (int) $item->qty,
            'name' => Str::limit(trim($item->product_name.' '.$item->variant_label), 50, ''),
        ])->values()->all();

        return [
            'transaction_details' => [
                'order_id' => $order->order_code,
                'gross_amount' => (int) $order->total,
            ],
            'item_details' => $items,
            'customer_details' => [
                'first_name' => Str::limit($order->user->name, 100, ''),
                'email' => $order->user->email,
                'phone' => $order->phone_wa,
            ],
            'enabled_payments' => config('midtrans.enabled_payments'),
            // Dibulatkan ke bawah agar tagihan Midtrans tidak melewati batas pesanan.
            'expiry' => [
                'unit' => 'minutes',
                'duration' => max(1, intdiv($remainingSeconds, 60)),
            ],
            'callbacks' => ['finish' => url('/payment/finish')],
            'custom_field1' => $order->order_code,
        ];
    }

    private function normalizeStatus(array $n): string
    {
        $status = (string) ($n['transaction_status'] ?? '');

        if ($status === 'capture') {
            return ($n['fraud_status'] ?? 'accept') === 'challenge' ? 'pending' : 'settlement';
        }

        return $status;
    }

    private function resolveMethod(array $n): ?string
    {
        $type = $n['payment_type'] ?? null;

        return match (true) {
            $type === null => null,
            $type === 'bank_transfer' => isset($n['va_numbers'][0]['bank'])
                ? strtolower($n['va_numbers'][0]['bank']).'_va'
                : (isset($n['permata_va_number']) ? 'permata_va' : 'bank_transfer'),
            $type === 'cstore' => strtolower((string) ($n['store'] ?? 'cstore')),
            default => (string) $type, // echannel, dll.
        };
    }
}