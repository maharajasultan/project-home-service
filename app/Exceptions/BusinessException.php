<?php

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Error aturan bisnis yang aman ditampilkan ke user (stok habis, jadwal penuh, dll). */
class BusinessException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $status = 422,
        private readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), $this->status, $this->errors);
    }

    /** true = sudah "ditangani", tidak perlu dicatat ke laravel.log. */
    public function report(): bool
    {
        return true;
    }
}