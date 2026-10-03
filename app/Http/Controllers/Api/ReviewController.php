<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreReviewRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\ReviewService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    private const ORDER_WITH = ['items', 'technician.technicianProfile', 'payment', 'reviews', 'photos', 'warrantyClaims'];

    public function __construct(private readonly ReviewService $reviews)
    {
    }

    /** GET /api/v1/orders/{id}/review  (data form: teknisi, produk yang dinilai, ulasan terkirim) */
    public function show(Request $request, int $id): JsonResponse
    {
        $order = Order::where('user_id', $request->user()->id)->findOrFail($id);

        return ApiResponse::success($this->reviews->form($order));
    }

    /**
     * POST /api/v1/orders/{id}/review
     * { technician: {rating, comment}, products: [{product_id, rating, comment}] }
     */
    public function store(StoreReviewRequest $request, int $id): JsonResponse
    {
        $order = Order::where('user_id', $request->user()->id)->findOrFail($id);

        $this->reviews->submit($request->user(), $order, $request->validated());

        return ApiResponse::success(
            (new OrderResource($order->fresh(self::ORDER_WITH)))->resolve(),
            'Terima kasih! Ulasan Anda sudah terkirim.',
            201
        );
    }
}