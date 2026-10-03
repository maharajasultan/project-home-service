<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceTransaction extends Model
{
    /** Dibuat otomatis oleh sistem. Tidak boleh dihapus dari laporan. */
    public const AUTO_CATEGORIES = ['penjualan', 'pembelian_stok'];

    /** Kategori yang boleh diinput admin secara manual (semuanya uang keluar). */
    public const EXPENSE_CATEGORIES = [
        'gaji_teknisi' => 'Gaji / Bonus Teknisi',
        'operasional' => 'Operasional',
        'transport' => 'Transport & Bensin',
        'marketing' => 'Marketing & Iklan',
        'refund' => 'Refund Pelanggan',
        'lainnya' => 'Lainnya',
    ];

    public const CATEGORY_LABELS = [
        'penjualan' => 'Penjualan',
        'pembelian_stok' => 'Pembelian Stok',
        'gaji_teknisi' => 'Gaji / Bonus Teknisi',
        'operasional' => 'Operasional',
        'transport' => 'Transport & Bensin',
        'marketing' => 'Marketing & Iklan',
        'refund' => 'Refund Pelanggan',
        'lainnya' => 'Lainnya',
    ];

    protected $fillable = [
        'order_id', 'type', 'category', 'amount', 'description', 'transaction_date', 'created_by',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isManual(): bool
    {
        return $this->category !== null && ! in_array($this->category, self::AUTO_CATEGORIES, true);
    }
}