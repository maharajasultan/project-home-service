<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ReviewResource;
use App\Models\Product;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /** GET /api/v1/products?type=&q=&per_page=&page= */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::enum(ProductType::class)],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $products = $this->baseQuery()
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 20);

        return ApiResponse::success(
            ProductResource::collection($products->getCollection())->resolve(),
            'OK',
            200,
            [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ]
        );
    }

    /** GET /api/v1/products/{id}  (detail + varian + 3 komentar terbaru) */
    public function show(int $id): JsonResponse
    {
        $product = $this->baseQuery()
            ->with(['variants' => fn ($q) => $q->purchasable()
                ->with('iphoneModel')
                ->orderBy('iphone_model_id')
                ->orderBy('price')])
            ->findOrFail($id);

        // Hindari query berulang saat menghitung final_price tiap varian.
        $product->variants->each(fn ($variant) => $variant->setRelation('product', $product));

        $latestReviews = $product->reviews()
            ->with('user:id,name,avatar')
            ->latest()
            ->limit(3)
            ->get();

        return ApiResponse::success([
            ...(new ProductResource($product))->resolve(),
            'latest_reviews' => ReviewResource::collection($latestReviews)->resolve(),
        ]);
    }

    /** GET /api/v1/products/{id}/reviews  (semua komentar, berpaginasi) */
    public function reviews(int $id): JsonResponse
    {
        $product = Product::active()->findOrFail($id);

        $reviews = $product->reviews()
            ->with('user:id,name,avatar')
            ->latest()
            ->paginate(10);

        return ApiResponse::success(
            ReviewResource::collection($reviews->getCollection())->resolve(),
            'OK',
            200,
            [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ]
        );
    }

    /** Query dasar: produk aktif + rating + harga mulai dari + total stok. */
    private function baseQuery(): Builder
    {
        return Product::active()
            ->withAvg('reviews as rating_avg', 'rating')
            ->withCount('reviews as rating_count')
            ->withMin(['variants as min_price' => fn ($q) => $q->purchasable()], 'price')
            ->withSum(['variants as total_stock' => fn ($q) => $q->purchasable()], 'stock');
    }
}