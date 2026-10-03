<?php

namespace App\Enums;

enum ProductType: string
{
    case Sparepart = 'sparepart';
    case Matot = 'matot';
    case UnlockImei = 'unlock_imei';

    public function label(): string
    {
        return match ($this) {
            self::Sparepart => 'Sparepart',
            self::Matot => 'Service Matot',
            self::UnlockImei => 'Unlock IMEI',
        };
    }
}