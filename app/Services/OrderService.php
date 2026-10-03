<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ProductType;
use App\Enums\UserRole;
use App\Exceptions\BusinessException;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\TechnicianSchedule;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Payment;

class OrderService
{
    private const MAX_QTY_PER_ITEM = 5;

    public function __construct(
        private readonly ScheduleService $schedule,
        private readonly AddressValidatorService $address,
    ) {
    }

    /**
     * Buat pesanan. $data berasal dari CreateOrderRequest::validated().
     *
     * @throws BusinessException
     */
    public function create(User $user, array $data): Order
    {
        $this->expireOverdue();

        $pending = Order::where('user_id', $user->id)
            ->where('status', OrderStatus::Pending->value)
            ->count();

        if ($pending >= (int) config('reaple.max_pending_orders')) {
            throw new BusinessException(
                "Anda masih punya {$pending} pesanan yang belum dibayar. Selesaikan atau batalkan dulu."
            );
        }

        $scheduledAt = $this->schedule->assertBookable($data['scheduled_date'], $data['scheduled_time']);
        $technician = $this->findTechnician((int) $data['technician_id']);
        $district = $this->address->canonicalDistrict($data['district']);

        $order = DB::transaction(function () use ($user, $data, $scheduledAt, $technician, $district) {
            $lines = $this->resolveLines($user, $data);
            $variants = ProductVariant::whereIn('id', $lines->pluck('product_variant_id'))
                ->orderBy('id')
                ->lockForUpdate() // kunci baris agar stok tidak dijual dua kali
                ->get()
                ->keyBy('id');
            $variants->load(['product', 'iphoneModel']);

            $subtotal = 0;
            $serviceFee = 0;
            $itemRows = [];

            foreach ($lines as $line) {
                $variant = $variants->get($line['product_variant_id']);
                $product = $variant?->product;

                if (
                    ! $variant || ! $variant->is_active || ! $product?->is_active
                    || ($variant->iphone_model_id && ! $variant->iphoneModel?->is_active)
                ) {
                    throw new BusinessException('Salah satu item sudah tidak tersedia. Muat ulang halaman.');
                }

                $qty = $line['qty'];
                $isSparepart = $product->type === ProductType::Sparepart;

                if (! $isSparepart && $qty > 1) {
                    throw new BusinessException("{$product->name} hanya dapat dipesan 1 kali per pesanan.");
                }

                if ($qty > self::MAX_QTY_PER_ITEM) {
                    throw new BusinessException('Maksimal '.self::MAX_QTY_PER_ITEM." per item ({$product->name}).");
                }

                if ($isSparepart && $variant->stock < $qty) {
                    throw new BusinessException(
                        "Stok {$product->name} ({$variant->label()}) tidak cukup. Tersisa {$variant->stock}."
                    );
                }

                $subtotal += $variant->price * $qty;
                $serviceFee += $product->base_fee * $qty;

                $itemRows[] = [
                    'variant' => $variant,
                    'product' => $product,
                    'qty' => $qty,
                    'isSparepart' => $isSparepart,
                ];
            }

            $order = Order::create([
                'order_code' => $this->generateOrderCode(),
                'user_id' => $user->id,
                'technician_id' => $technician->id,
                'status' => OrderStatus::Pending,
                'address' => trim($data['address']),
                'district' => $district,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'phone_wa' => $data['phone_wa'],
                'notes' => $data['notes'] ?? null,
                'scheduled_at' => $scheduledAt,
                'subtotal' => $subtotal,
                'service_fee' => $serviceFee,
                'total' => $subtotal + $serviceFee,
                'expired_at' => now()->addMinutes((int) config('reaple.payment_ttl_minutes')),
            ]);

            foreach ($itemRows as $row) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_variant_id' => $row['variant']->id,
                    'product_id' => $row['product']->id,
                    'product_name' => $row['product']->name,
                    'variant_label' => $row['variant']->label() ?: null,
                    'price' => $row['variant']->price,
                    'base_fee' => $row['product']->base_fee,
                    'qty' => $row['qty'],
                ]);

                if ($row['isSparepart']) {
                    $row['variant']->decrement('stock', $row['qty']);
                }
            }

            $this->reserveSlot($technician->id, $scheduledAt, $order->id);

            if ($data['source'] === 'cart') {
                CartItem::where('user_id', $user->id)->delete();
            }

            return $order;
        });

        return $order->load(['items', 'technician.technicianProfile', 'payment', 'reviews']);
    }

    /** Batalkan pesanan yang belum dibayar oleh pemiliknya. */
    public function cancelByUser(Order $order): void
    {
        if (! $this->release($order->id, 'cancel')) {
            throw new BusinessException('Pesanan ini tidak dapat dibatalkan.');
        }
    }

    /** Tandai semua pesanan pending yang lewat batas waktu sebagai gagal. */
    public function expireOverdue(): int
    {
        $ids = Order::where('status', OrderStatus::Pending->value)
            ->whereNotNull('expired_at')
            ->where('expired_at', '<=', now())
            ->pluck('id');

        $count = 0;

        foreach ($ids as $id) {
            if ($this->release($id)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Gagalkan pesanan pending: kembalikan stok dan bebaskan slot teknisi.
     * Aman dipanggil berulang (idempotent). Return true jika benar-benar diproses.
     */
    public function release(int $orderId, string $paymentStatus = 'expire'): bool
    {
        return DB::transaction(function () use ($orderId, $paymentStatus) {
            $order = Order::whereKey($orderId)->lockForUpdate()->first();

            if (! $order || $order->status !== OrderStatus::Pending) {
                return false;
            }

            $order->load('items.product');

            foreach ($order->items as $item) {
                if ($item->product_variant_id && $item->product?->type === ProductType::Sparepart) {
                    ProductVariant::whereKey($item->product_variant_id)->increment('stock', $item->qty);
                }
            }

            TechnicianSchedule::where('order_id', $order->id)->delete();

            Payment::where('order_id', $order->id)
                ->where('status', 'pending')
                ->update(['status' => $paymentStatus]);

            $order->update(['status' => OrderStatus::Failed]);

            return true;
        });
    }

    /** Gabungkan item pesanan dari keranjang atau dari "Beli Langsung". */
    private function resolveLines(User $user, array $data): Collection
    {
        if ($data['source'] === 'cart') {
            $lines = CartItem::where('user_id', $user->id)->get()
                ->map(fn (CartItem $i) => ['product_variant_id' => $i->product_variant_id, 'qty' => $i->qty]);
        } else {
            $lines = collect($data['items'])->map(fn (array $i) => [
                'product_variant_id' => (int) $i['product_variant_id'],
                'qty' => (int) ($i['qty'] ?? 1),
            ]);
        }

        if ($lines->isEmpty()) {
            throw new BusinessException('Keranjang Anda kosong.');
        }

        return $lines->groupBy('product_variant_id')
            ->map(fn (Collection $group, $id) => [
                'product_variant_id' => (int) $id,
                'qty' => (int) $group->sum('qty'),
            ])
            ->values();
    }

    private function findTechnician(int $id): User
    {
        $technician = $this->schedule->activeTechnicians()->whereKey($id)->first();

        if (! $technician) {
            throw new BusinessException('Teknisi yang dipilih tidak tersedia. Pilih teknisi lain.');
        }

        return $technician;
    }

    /** Kunci slot. Unique key database menjamin tidak ada dua pesanan di slot yang sama. */
    private function reserveSlot(int $technicianId, \Illuminate\Support\Carbon $at, int $orderId): void
    {
        try {
            TechnicianSchedule::create([
                'technician_id' => $technicianId,
                'order_id' => $orderId,
                'date' => $at->toDateString(),
                'time_slot' => $at->format('H:i:s'),
            ]);
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) { // duplicate entry
                throw new BusinessException(
                    'Jadwal teknisi tersebut baru saja terisi. Silakan pilih waktu atau teknisi lain.',
                    409
                );
            }

            throw $e;
        }
    }

    private function generateOrderCode(): string
    {
        do {
            $code = 'RPL-'.now()->format('ymd').'-'.strtoupper(Str::random(5));
        } while (Order::where('order_code', $code)->exists());

        return $code;
    }
}