<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';          // menunggu pembayaran
    case Paid = 'paid';                // sudah dibayar, menunggu teknisi
    case OnTheWay = 'on_the_way';      // teknisi dalam perjalanan
    case InProgress = 'in_progress';   // sedang dikerjakan
    case Completed = 'completed';      // selesai
    case Failed = 'failed';            // gagal / kedaluwarsa / dibatalkan

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Pembayaran',
            self::Paid, self::OnTheWay, self::InProgress => 'Diproses',
            self::Completed => 'Selesai',
            self::Failed => 'Gagal',
        };
    }

    /** Status yang dianggap "berhasil dibayar" (untuk riwayat tab Berhasil). */
    public static function successful(): array
    {
        return [self::Paid, self::OnTheWay, self::InProgress, self::Completed];
    }
}