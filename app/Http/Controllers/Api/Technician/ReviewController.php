<?php

namespace App\Http\Controllers\Api\Technician;

use App\Http\Controllers\Controller;
use App\Http\Requests\Review\ReplyReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    /** GET /api/v1/technician/reviews?replied=0|1&per_page=&page= */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'replied' => ['nullable', Rule::in(['0', '1', 0, 1])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $technician = $request->user()->load('technicianProfile');

        $base = Review::where('target_type', 'technician')->where('target_id', $technician->id);

        $distribution = (clone $base)
            ->selectRaw('rating, count(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');

        $query = (clone $base)->with(['user:id,name,avatar', 'order:id,order_code'])->latest('id');

        if (isset($filters['replied'])) {
            (int) $filters['replied'] === 1
                ? $query->whereNotNull('reply')
                : $query->whereNull('reply');
        }

        $paginator = $query->paginate($filters['per_page'] ?? 10);

        return ApiResponse::success(
            ReviewResource::collection($paginator->getCollection())->resolve(),
            'OK',
            200,
            [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'summary' => [
                    'avg_rating' => (float) ($technician->technicianProfile?->avg_rating ?? 0),
                    'rating_count' => (int) ($technician->technicianProfile?->rating_count ?? 0),
                    'distribution' => collect([5, 4, 3, 2, 1])
                        ->mapWithKeys(fn ($star) => [(string) $star => (int) ($distribution[$star] ?? 0)])
                        ->all(),
                    'unreplied' => (clone $base)->whereNull('reply')->count(),
                ],
            ]
        );
    }

    /** POST /api/v1/technician/reviews/{id}/reply  { reply }  (boleh diperbarui) */
    public function reply(ReplyReviewRequest $request, int $id): JsonResponse
    {
        $review = Review::where('target_type', 'technician')
            ->where('target_id', $request->user()->id)
            ->findOrFail($id);

        $review->update([
            'reply' => $request->validated('reply'),
            'replied_at' => now(),
        ]);

        $review->load(['user:id,name,avatar', 'order:id,order_code']);

        return ApiResponse::success((new ReviewResource($review))->resolve(), 'Balasan terkirim.');
    }
}