<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddCartItemRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\CartItemResource;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    private const MAX_QTY_PER_ITEM = 5;

    /** GET /api/v1/cart */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success($this->cartPayload($request));
    }

    /** POST /api/v1/cart  { product_variant_id, qty? } */
    public function store(AddCartItemRequest $request): JsonResponse
    {
        $data = $request->validated();

        $variant = ProductVariant::purchasable()->with('product')->find($data['product_variant_id']);

        if (! $variant) {
            return ApiResponse::error('Varian produk ini sedang tidak tersedia.', 404);
        }

        $item = CartItem::firstOrNew([
            'user_id' => $request->user()->id,
            'product_variant_id' => $variant->id,
        ]);

        $newQty = ($item->exists ? $item->qty : 0) + ($data['qty'] ?? 1);

        if ($error = $this->qtyError($variant, $newQty)) {
            return $error;
        }

        $item->qty = $newQty;
        $item->save();

        return ApiResponse::success($this->cartPayload($request), 'Ditambahkan ke keranjang.', 201);
    }

    /** PATCH /api/v1/cart/{id}  { qty } */
    public function update(UpdateCartItemRequest $request, int $id): JsonResponse
    {
        $item = CartItem::where('user_id', $request->user()->id)
            ->with('variant.product')
            ->findOrFail($id);

        $qty = (int) $request->validated('qty');

        if ($error = $this->qtyError($item->variant, $qty)) {
            return $error;
        }

        $item->update(['qty' => $qty]);

        return ApiResponse::success($this->cartPayload($request), 'Keranjang diperbarui.');
    }

    /** DELETE /api/v1/cart/{id} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        CartItem::where('user_id', $request->user()->id)->findOrFail($id)->delete();

        return ApiResponse::success($this->cartPayload($request), 'Item dihapus dari keranjang.');
    }

    /** DELETE /api/v1/cart  (kosongkan keranjang) */
    public function clear(Request $request): JsonResponse
    {
        CartItem::where('user_id', $request->user()->id)->delete();

        return ApiResponse::success($this->cartPayload($request), 'Keranjang dikosongkan.');
    }

    /** Validasi jumlah terhadap tipe produk dan stok. Null jika aman. */
    private function qtyError(ProductVariant $variant, int $qty): ?JsonResponse
    {
        $variant->loadMissing('product');

        if ($variant->product->type !== ProductType::Sparepart && $qty > 1) {
            return ApiResponse::error('Layanan ini hanya dapat dipesan 1 kali per item.', 422);
        }

        if ($variant->stock < 1) {
            return ApiResponse::error('Stok varian ini habis.', 422);
        }

        if ($qty > $variant->stock) {
            return ApiResponse::error("Stok tidak cukup. Tersisa {$variant->stock}.", 422);
        }

        if ($qty > self::MAX_QTY_PER_ITEM) {
            return ApiResponse::error('Maksimal '.self::MAX_QTY_PER_ITEM.' per item.', 422);
        }

        return null;
    }

    /** Isi keranjang + ringkasan. Setiap endpoint keranjang mengembalikan ini agar UI selalu sinkron. */
    private function cartPayload(Request $request): array
    {
        $items = CartItem::where('user_id', $request->user()->id)
            ->with(['variant.product', 'variant.iphoneModel'])
            ->latest('id')
            ->get();

        $available = $items->filter(fn (CartItem $item) => $item->isAvailable());

        $subtotal = $available->sum(fn (CartItem $i) => $i->variant->price * $i->qty);
        $serviceFee = $available->sum(fn (CartItem $i) => $i->variant->product->base_fee * $i->qty);

        return [
            'items' => CartItemResource::collection($items)->resolve(),
            'summary' => [
                'item_count' => (int) $items->sum('qty'),
                'subtotal' => (int) $subtotal,
                'service_fee' => (int) $serviceFee, // ongkir/pengecekan (Matot)
                'total' => (int) ($subtotal + $serviceFee),
                'has_unavailable' => $available->count() !== $items->count(),
            ],
        ];
    }
}