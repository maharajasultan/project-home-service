<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use App\Services\ScheduleService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleService $schedule,
        private readonly OrderService $orders,
    ) {
    }

    /** GET /api/v1/schedule/dates  (pilihan hari dan tanggal) */
    public function dates(): JsonResponse
    {
        return ApiResponse::success($this->schedule->dates());
    }

    /** GET /api/v1/schedule/slots?date=YYYY-MM-DD  (jam dan teknisi yang tersedia) */
    public function slots(Request $request): JsonResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']], [
            'date.required' => 'Tanggal wajib diisi.',
            'date.date_format' => 'Format tanggal harus YYYY-MM-DD.',
        ]);

        // Bebaskan dulu slot dari pesanan yang kedaluwarsa agar data akurat.
        $this->orders->expireOverdue();

        return ApiResponse::success($this->schedule->slotsFor($data['date']));
    }
}