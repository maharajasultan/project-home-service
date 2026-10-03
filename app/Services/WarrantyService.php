<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use App\Models\WarrantyClaim;
use Illuminate\Support\Facades\DB;

class WarrantyService
{
    private const STATUS_LABELS = [
        'pending' => 'Menunggu Ditinjau',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
    ];

    /** @throws BusinessException */
    public function claim(User $user, Order $order, string $reason): WarrantyClaim
    {
        return DB::transaction(function () use ($user, $order, $reason) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::Completed) {
                throw new BusinessException('Klaim garansi hanya untuk pesanan yang sudah selesai.');
            }

            $reviewed = Review::where('order_id', $locked->id)->where('user_id', $user->id)->exists();

            if (! $reviewed) {
                throw new BusinessException('Beri rating dan ulasan terlebih dahulu untuk membuka klaim garansi.');
            }

            if (! $locked->isUnderWarranty()) {
                $until = $locked->warrantyValidUntil()?->format('d-m-Y');

                throw new BusinessException("Masa garansi 3 bulan sudah berakhir pada {$until}.");
            }

            $hasPending = WarrantyClaim::where('order_id', $locked->id)->where('status', 'pending')->exists();

            if ($hasPending) {
                throw new BusinessException('Klaim garansi Anda masih diproses. Mohon tunggu kabar dari admin.', 409);
            }

            return WarrantyClaim::create([
                'order_id' => $locked->id,
                'user_id' => $user->id,
                'reason' => trim(strip_tags($reason)),
                'status' => 'pending',
            ]);
        });
    }

    public function summary(Order $order): array
    {
        $claims = WarrantyClaim::where('order_id', $order->id)->latest('id')->get();

        return [
            'valid_until' => $order->warrantyValidUntil()?->toIso8601String(),
            'is_under_warranty' => $order->status === OrderStatus::Completed && $order->isUnderWarranty(),
            'claims' => $claims->map(fn (WarrantyClaim $c) => $this->payload($c))->values()->all(),
        ];
    }

    public function payload(WarrantyClaim $claim): array
    {
        return [
            'id' => $claim->id,
            'reason' => $claim->reason,
            'status' => $claim->status,
            'status_label' => self::STATUS_LABELS[$claim->status] ?? $claim->status,
            'admin_note' => $claim->admin_note,
            'created_at' => $claim->created_at?->toIso8601String(),
        ];
    }
}