<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviews)
    {
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'target' => ['nullable', 'in:all,technician,product'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'status' => ['nullable', 'in:all,replied,unreplied'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $target = $filters['target'] ?? 'all';
        $status = $filters['status'] ?? 'all';
        $rating = $filters['rating'] ?? '';
        $term = trim((string) ($filters['q'] ?? ''));

        $reviews = Review::with(['user:id,name,email', 'order:id,order_code'])
            ->when($target !== 'all', fn ($q) => $q->where('target_type', $target))
            ->when($rating !== '', fn ($q) => $q->where('rating', $rating))
            ->when($status === 'replied', fn ($q) => $q->whereNotNull('reply'))
            ->when($status === 'unreplied', fn ($q) => $q->whereNull('reply'))
            ->when($term !== '', fn ($q) => $q->where(
                fn ($w) => $w->where('comment', 'like', "%{$term}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%"))
            ))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $rows = $reviews->getCollection();
        $techNames = User::whereIn('id', $rows->where('target_type', 'technician')->pluck('target_id'))->pluck('name', 'id');
        $productNames = Product::whereIn('id', $rows->where('target_type', 'product')->pluck('target_id'))->pluck('name', 'id');

        $stats = Review::toBase()
            ->selectRaw('target_type, avg(rating) as avg_rating, count(*) as total')
            ->groupBy('target_type')
            ->get()
            ->keyBy('target_type');

        $summary = [];
        foreach (['technician', 'product'] as $type) {
            $row = $stats->get($type);
            $summary[$type] = ['avg' => round((float) ($row->avg_rating ?? 0), 1), 'total' => (int) ($row->total ?? 0)];
        }

        $unreplied = Review::whereNull('reply')->count();
        $lowRating = Review::where('rating', '<=', 2)->count();

        return view('admin.reviews.index', compact(
            'reviews', 'techNames', 'productNames', 'summary', 'unreplied', 'lowRating',
            'target', 'status', 'rating', 'term'
        ));
    }

    /** Balasan admin (menimpa balasan teknisi jika sudah ada). */
    public function reply(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'reply' => ['required', 'string', 'min:2', 'max:500'],
        ], [
            'reply.required' => 'Balasan tidak boleh kosong.',
            'reply.min' => 'Balasan terlalu singkat.',
            'reply.max' => 'Balasan maksimal 500 karakter.',
        ]);

        $review = Review::findOrFail($id);

        $review->update([
            'reply' => trim(strip_tags($data['reply'])),
            'replied_at' => now(),
        ]);

        return back()->with('success', 'Balasan disimpan.');
    }

    /** Moderasi: hapus ulasan, lalu hitung ulang rating teknisi. */
    public function destroy(int $id): RedirectResponse
    {
        DB::transaction(function () use ($id) {
            $review = Review::lockForUpdate()->findOrFail($id);
            $technicianId = $review->target_type === 'technician' ? (int) $review->target_id : null;

            $review->delete();

            if ($technicianId) {
                $this->reviews->refreshTechnicianRating($technicianId);
            }
        });

        return back()->with('success', 'Ulasan dihapus.');
    }
}