<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warranty\StoreWarrantyClaimRequest;
use App\Models\Order;
use App\Services\WarrantyService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarrantyController extends Controller
{
    public function __construct(private readonly WarrantyService $warranty)
    {
    }

    /** GET /api/v1/orders/{id}/warranty-claims */
    public function index(Request $request, int $id): JsonResponse
    {
        $order = Order::where('user_id', $request->user()->id)->findOrFail($id);

        return ApiResponse::success($this->warranty->summary($order));
    }

    /** POST /api/v1/orders/{id}/warranty-claims  { reason } */
    public function store(StoreWarrantyClaimRequest $request, int $id): JsonResponse
    {
        $order = Order::where('user_id', $request->user()->id)->findOrFail($id);

        $this->warranty->claim($request->user(), $order, $request->validated('reason'));

        return ApiResponse::success(
            $this->warranty->summary($order->fresh()),
            'Klaim garansi terkirim. Admin akan menghubungi Anda melalui chat atau WhatsApp.',
            201
        );
    }
}