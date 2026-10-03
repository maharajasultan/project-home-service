<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\PaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments)
    {
    }

    /** POST /api/v1/orders/{id}/pay  -> tautan halaman bayar Midtrans (dibuka di WebView) */
    public function pay(Request $request, int $id): JsonResponse
    {
        $order = Order::where('user_id', $request->user()->id)->findOrFail($id);

        $payment = $this->payments->pay($order);
        $order->refresh();

        return ApiResponse::success([
            'order_id' => $order->id,
            'order_code' => $order->order_code,
            'amount' => (int) $payment->gross_amount,
            'redirect_url' => $payment->redirect_url,
            'snap_token' => $payment->snap_token,
            'expired_at' => $order->expired_at?->toIso8601String(),
            'seconds_remaining' => max(0, (int) now()->diffInSeconds($order->expired_at, false)),
            // Flutter menutup WebView saat URL mengandung teks ini.
            'finish_marker' => '/payment/finish',
            'accepted_methods' => collect(PaymentService::METHOD_LABELS)
                ->map(fn ($label, $code) => ['code' => $code, 'label' => $label])
                ->values(),
        ], 'Transaksi pembayaran siap.');
    }

    /** POST /api/v1/orders/{id}/payment/sync  -> periksa status ke Midtrans, kembalikan pesanan terbaru */
    public function sync(Request $request, int $id): JsonResponse
    {
        $order = Order::where('user_id', $request->user()->id)->findOrFail($id);

        $this->payments->sync($order);

        $order = $order->fresh(['items', 'technician.technicianProfile', 'payment', 'reviews']);

        return ApiResponse::success((new OrderResource($order))->resolve());
    }
}