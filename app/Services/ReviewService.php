<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessException;
use App\Http\Resources\ReviewResource;
use App\Models\Order;
use App\Models\Review;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Support\MediaUrl;
use Illuminate\Support\Facades\DB;

class ReviewService
{
    /** Data untuk form ulasan: siapa dan apa saja yang bisa dinilai + ulasan yang sudah terkirim. */
    public function form(Order $order): array
    {
        $order->loadMissing(['items.product', 'technician']);

        $submitted = $order->reviews()->with('user:id,name,avatar')->get();
        $reviewed = $submitted->isNotEmpty();

        $products = $order->items
            ->filter(fn ($item) => $item->product_id && $item->product)
            ->unique('product_id')
            ->map(fn ($item) => [
                'product_id' => (int) $item->product_id,
                'name' => $item->product->name,
                'type' => $item->product->type->value,
                'thumbnail' => $item->product->image_urls[0] ?? null,
            ])
            ->values()
            ->all();

        return [
            'can_review' => $order->status === OrderStatus::Completed && ! $reviewed,
            'already_reviewed' => $reviewed,
            'technician' => $order->technician ? [
                'id' => $order->technician->id,
                'name' => $order->technician->name,
                'avatar_url' => MediaUrl::resolve($order->technician->avatar),
            ] : null,
            'products' => $products,
            'submitted' => ReviewResource::collection($submitted)->resolve(),
        ];
    }

    /**
     * Simpan ulasan teknisi dan semua produk sekaligus.
     *
     * @throws BusinessException
     */
    public function submit(User $user, Order $order, array $data): void
    {
        DB::transaction(function () use ($user, $order, $data) {
            $locked = Order::with('items')->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::Completed) {
                throw new BusinessException('Ulasan hanya dapat diberikan setelah service selesai.');
            }

            if (Review::where('order_id', $locked->id)->where('user_id', $user->id)->exists()) {
                throw new BusinessException('Anda sudah memberi ulasan untuk pesanan ini.', 409);
            }

            $expected = $locked->items->pluck('product_id')->filter()
                ->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();

            $given = collect($data['products'])->pluck('product_id')
                ->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();

            if ($expected !== $given) {
                throw new BusinessException(
                    'Beri rating untuk semua produk pada pesanan ini.',
                    422,
                    ['products' => ['Beri rating untuk semua produk pada pesanan ini.']]
                );
            }

            if ($locked->technician_id) {
                Review::create([
                    'order_id' => $locked->id,
                    'user_id' => $user->id,
                    'target_type' => 'technician',
                    'target_id' => $locked->technician_id,
                    'rating' => (int) $data['technician']['rating'],
                    'comment' => $this->clean($data['technician']['comment'] ?? null),
                ]);
            }

            foreach ($data['products'] as $row) {
                Review::create([
                    'order_id' => $locked->id,
                    'user_id' => $user->id,
                    'target_type' => 'product',
                    'target_id' => (int) $row['product_id'],
                    'rating' => (int) $row['rating'],
                    'comment' => $this->clean($row['comment'] ?? null),
                ]);
            }

            if ($locked->technician_id) {
                $this->refreshTechnicianRating((int) $locked->technician_id);
            }
        });
    }

    /** Hitung ulang rata-rata dan jumlah rating teknisi dari tabel reviews. */
    public function refreshTechnicianRating(int $technicianId): void
    {
        $stats = Review::where('target_type', 'technician')
            ->where('target_id', $technicianId)
            ->selectRaw('avg(rating) as avg_rating, count(*) as total')
            ->first();

        TechnicianProfile::where('user_id', $technicianId)->update([
            'avg_rating' => round((float) ($stats->avg_rating ?? 0), 2),
            'rating_count' => (int) ($stats->total ?? 0),
        ]);
    }

    private function clean(?string $comment): ?string
    {
        $text = trim(strip_tags((string) $comment));

        return $text === '' ? null : $text;
    }
}