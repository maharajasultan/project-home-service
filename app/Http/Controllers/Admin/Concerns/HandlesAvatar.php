<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait HandlesAvatar
{
    /** Simpan avatar baru, hapus yang lama. Return path baru. */
    protected function replaceAvatar(UploadedFile $file, ?string $oldPath): string
    {
        $path = $file->store('avatars', 'public');
        $this->deleteAvatar($oldPath);

        return $path;
    }

    protected function deleteAvatar(?string $path): void
    {
        if ($path && ! str_starts_with($path, 'http')) {
            Storage::disk('public')->delete($path);
        }
    }
}