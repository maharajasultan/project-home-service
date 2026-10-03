<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CreateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\MidtransService;

class OrderController extends Controller
{
    private const WITH = ['items', 'technician.technicianProfile', 'payment', 'reviews', 'photos'];

    public function __construct(private readonly OrderService $orders) {}

    /** GET /api/v1/orders?status=pending|success|failed&per_page=&page= */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'success', 'failed'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $this->orders->expireOverdue();

        $query = Order::where('user_id', $request->user()->id)
            ->with(self::WITH)
            ->latest('id');

        if (! empty($filters['status'])) {
            $query->whereIn('status', $this->statusValues($filters['status']));
        }

        $paginator = $query->paginate($filters['per_page'] ?? 10);

        return ApiResponse::success(
            OrderResource::collection($paginator->getCollection())->resolve(),
            'OK',
            200,
            [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    /** GET /api/v1/orders/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        $this->orders->expireOverdue();

        $order = Order::where('user_id', $request->user()->id)
            ->with(self::WITH)
            ->findOrFail($id);

        return ApiResponse::success((new OrderResource($order))->resolve());
    }

    /** POST /api/v1/orders */
    public function store(CreateOrderRequest $request): JsonResponse
    {
        $order = $this->orders->create($request->user(), $request->validated());

        $minutes = config('reaple.payment_ttl_minutes');

        return ApiResponse::success(
            (new OrderResource($order))->resolve(),
            "Pesanan dibuat. Selesaikan pembayaran dalam {$minutes} menit.",
            201
        );
    }

    /** POST /api/v1/orders/{id}/cancel  (hanya pesanan yang belum dibayar) */
    public function cancel(Request $request, int $id, MidtransService $midtrans): JsonResponse
    {
        $order = Order::where('user_id', $request->user()->id)->with('payment')->findOrFail($id);

        $this->orders->cancelByUser($order);

        // Matikan tagihan di Midtrans agar VA / kode bayar tidak bisa dipakai lagi.
        if ($order->payment) {
            $midtrans->expireQuietly($order->payment->midtrans_order_id);
        }

        return ApiResponse::success(
            (new OrderResource($order->fresh(self::WITH)))->resolve(),
            'Pesanan dibatalkan.'
        );
    }

    /** @return string[] */
    private function statusValues(string $tab): array
    {
        return match ($tab) {
            'pending' => [OrderStatus::Pending->value],
            'failed' => [OrderStatus::Failed->value],
            default => array_map(fn(OrderStatus $s) => $s->value, OrderStatus::successful()),
        };
    }
}
