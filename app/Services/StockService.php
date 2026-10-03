<?php

namespace App\Services;

use App\Enums\ProductType;
use App\Exceptions\BusinessException;
use App\Models\FinanceTransaction;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Barang masuk ('in') atau koreksi/barang keluar manual ('out').
     * Jika $cost diisi pada barang masuk, otomatis tercatat sebagai uang keluar.
     *
     * @throws BusinessException
     */
    public function adjust(int $variantId, string $type, int $qty, ?string $note, int $adminId, ?int $cost = null): ProductVariant
    {
        if (! in_array($type, ['in', 'out'], true) || $qty < 1) {
            throw new BusinessException('Data stok tidak valid.');
        }

        return DB::transaction(function () use ($variantId, $type, $qty, $note, $adminId, $cost) {
            $variant = ProductVariant::with(['product', 'iphoneModel'])->lockForUpdate()->findOrFail($variantId);

            if ($variant->product->type !== ProductType::Sparepart) {
                throw new BusinessException('Stok hanya dikelola untuk produk sparepart.');
            }

            if ($type === 'out' && $qty > $variant->stock) {
                throw new BusinessException("Stok tidak cukup. Tersisa {$variant->stock}.");
            }

            $type === 'in' ? $variant->increment('stock', $qty) : $variant->decrement('stock', $qty);

            $note = trim(strip_tags((string) $note));

            StockMovement::create([
                'product_variant_id' => $variant->id,
                'order_id' => null,
                'type' => $type,
                'qty' => $qty,
                'note' => $note !== '' ? $note : ($type === 'in' ? 'Barang masuk' : 'Koreksi stok'),
                'created_by' => $adminId,
            ]);

            if ($type === 'in' && $cost !== null && $cost > 0) {
                FinanceTransaction::create([
                    'order_id' => null,
                    'type' => 'out',
                    'amount' => $cost,
                    'description' => "Pembelian stok {$variant->product->name} ({$variant->label()}) x{$qty}",
                    'transaction_date' => now()->toDateString(),
                    'created_by' => $adminId,
                ]);
            }

            return $variant->refresh();
        });
    }
}