<?php

namespace App\Support;

class PhoneNumber
{
    /** Samakan format nomor Indonesia ke 08xxxxxxxxxx (terima +62, 62, atau 0). */
    public static function normalize(mixed $phone): string
    {
        $digits = preg_replace('/[^\d+]/', '', (string) $phone);

        if (str_starts_with($digits, '+62')) {
            return '0'.substr($digits, 3);
        }

        if (str_starts_with($digits, '62')) {
            return '0'.substr($digits, 2);
        }

        return $digits;
    }
}