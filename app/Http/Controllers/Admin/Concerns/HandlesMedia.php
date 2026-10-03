<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait HandlesMedia
{
    protected function storeFile(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, 'public');
    }

    /** URL penuh (gambar seeder dari picsum) tidak pernah dihapus dari disk. */
    protected function deleteFile(?string $path): void
    {
        if ($path && ! str_starts_with($path, 'http')) {
            Storage::disk('public')->delete($path);
        }
    }
}