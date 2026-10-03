<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MidtransService;
use App\Services\PaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MidtransWebhookController extends Controller
{
    /** POST /api/v1/midtrans/webhook  (dipanggil server Midtrans, tanpa token login) */
    public function __invoke(Request $request, MidtransService $midtrans, PaymentService $payments): JsonResponse
    {
        $payload = $request->json()->all() ?: $request->all();

        if (! $midtrans->isValidSignature($payload)) {
            Log::warning('Midtrans webhook: signature tidak valid', [
                'order_id' => $payload['order_id'] ?? null,
                'ip' => $request->ip(),
            ]);

            return ApiResponse::error('Signature tidak valid.', 403);
        }

        // Jika terjadi exception, Laravel membalas 500 dan Midtrans akan mengirim ulang.
        $payment = $payments->handleNotification($payload);

        return ApiResponse::success(null, $payment ? 'Notifikasi diproses.' : 'Diabaikan.');
    }
}