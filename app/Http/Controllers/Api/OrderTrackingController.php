<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\TechnicianLocation;
use App\Support\ApiResponse;
use App\Support\MediaUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderTrackingController extends Controller
{
    /** GET /api/v1/orders/{id}/tracking  (Flutter memanggil tiap ±5-10 detik saat layar peta terbuka) */
    public function show(Request $request, int $id): JsonResponse
    {
        $order = Order::where('user_id', $request->user()->id)
            ->with('technician.technicianProfile')
            ->findOrFail($id);

        if (! in_array($order->status, OrderStatus::successful(), true)) {
            throw new BusinessException('Pelacakan tersedia setelah pembayaran berhasil.');
        }

        $tracking = $order->status === OrderStatus::OnTheWay;
        $location = $tracking ? TechnicianLocation::where('order_id', $order->id)->first() : null;

        $secondsAgo = $location ? (int) $location->updated_at->diffInSeconds(now()) : null;
        $isStale = $secondsAgo !== null && $secondsAgo > (int) config('reaple.technician.location_stale_seconds');

        $message = match ($order->status) {
            OrderStatus::Paid => 'Teknisi belum berangkat. Jadwal kunjungan: '.$order->scheduled_at->format('d-m-Y H:i').' WIB.',
            OrderStatus::OnTheWay => match (true) {
                $location === null => 'Teknisi sedang bersiap berangkat. Menunggu lokasi...',
                $isStale => 'Menunggu pembaruan lokasi terbaru dari teknisi...',
                default => 'Teknisi sedang dalam perjalanan ke lokasi Anda.',
            },
            OrderStatus::InProgress => 'Teknisi sudah tiba dan sedang mengerjakan.',
            default => 'Service telah selesai.',
        };

        $technician = $order->technician;

        return ApiResponse::success([
            'order_id' => $order->id,
            'order_code' => $order->order_code,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'is_tracking' => $tracking,
            'message' => $message,

            'technician' => $technician ? [
                'id' => $technician->id,
                'name' => $technician->name,
                'avatar_url' => MediaUrl::resolve($technician->avatar),
                'avg_rating' => (float) ($technician->technicianProfile?->avg_rating ?? 0),
            ] : null,

            // null jika teknisi belum mengirim lokasi atau sudah tiba.
            'technician_location' => $location ? [
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'updated_at' => $location->updated_at->toIso8601String(),
                'seconds_ago' => $secondsAgo,
                'is_stale' => $isStale,
            ] : null,

            // Titik tujuan. Bisa null jika alamat tidak dipilih lewat peta.
            'destination' => [
                'latitude' => $order->latitude,
                'longitude' => $order->longitude,
                'address' => $order->address,
            ],
            'scheduled_at' => $order->scheduled_at->toIso8601String(),
            'server_time' => now()->toIso8601String(),
        ]);
    }
}