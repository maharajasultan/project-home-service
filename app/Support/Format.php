<?php

namespace App\Support;

use App\Enums\OrderStatus;
use Carbon\CarbonInterface;

class Format
{
    public static function rupiah(int|float|string|null $amount): string
    {
        return 'Rp'.number_format((float) $amount, 0, ',', '.');
    }

    public static function dateTime(?CarbonInterface $date, string $format = 'd-m-Y H:i'): string
    {
        return $date ? $date->format($format) : '-';
    }

    public static function statusBadge(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Pending => 'text-bg-warning',
            OrderStatus::Paid => 'text-bg-info',
            OrderStatus::OnTheWay, OrderStatus::InProgress => 'text-bg-primary',
            OrderStatus::Completed => 'text-bg-success',
            OrderStatus::Failed => 'text-bg-danger',
        };
    }

    /** Label rinci untuk admin (lebih spesifik daripada OrderStatus::label()). */
    public static function statusLabel(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Pending => 'Menunggu Pembayaran',
            OrderStatus::Paid => 'Dibayar, Menunggu Teknisi',
            OrderStatus::OnTheWay => 'Teknisi Dalam Perjalanan',
            OrderStatus::InProgress => 'Sedang Dikerjakan',
            OrderStatus::Completed => 'Selesai',
            OrderStatus::Failed => 'Gagal',
        };
    }

    public static function claimBadge(string $status): string
    {
        return match ($status) {
            'approved' => 'text-bg-success',
            'rejected' => 'text-bg-danger',
            default => 'text-bg-warning',
        };
    }

    public static function claimLabel(string $status): string
    {
        return match ($status) {
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            default => 'Menunggu Ditinjau',
        };
    }

    /** 0812xxxx -> https://wa.me/62812xxxx */
    public static function whatsappUrl(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        return 'https://wa.me/'.(str_starts_with($digits, '0') ? '62'.substr($digits, 1) : $digits);
    }
}