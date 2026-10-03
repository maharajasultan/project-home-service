<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class MediaUrl
{
    /**
     * Ubah path penyimpanan menjadi URL publik.
     * Jika sudah berupa URL penuh (http/https), kembalikan apa adanya.
     */
    public static function resolve(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}