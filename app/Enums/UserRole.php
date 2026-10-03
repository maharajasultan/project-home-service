<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case User = 'user';
    case Technician = 'technician';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::User => 'Pelanggan',
            self::Technician => 'Teknisi',
        };
    }
}