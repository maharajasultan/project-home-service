<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class TechnicianListController extends Controller
{
    /** GET /api/v1/technicians/top  (5 teknisi terbaik + 3 komentar terbaru masing-masing) */
    public function top(): JsonResponse
    {
        $technicians = $this->activeQuery()
            ->select('users.*')
            ->join('technician_profiles', 'technician_profiles.user_id', '=', 'users.id')
            ->orderByDesc('technician_profiles.avg_rating')
            ->orderByDesc('technician_profiles.rating_count')
            ->orderBy('users.name')
            ->with('technicianProfile')
            ->limit(5)
            ->get();

        $data = $technicians->values()->map(function (User $t, int $index) {
            $comments = Review::where('target_type', 'technician')
                ->where('target_id', $t->id)
                ->whereNotNull('comment')
                ->where('comment', '!=', '')
                ->with('user:id,name,avatar')
                ->latest('id')
                ->limit(3)
                ->get();

            return [
                'rank' => $index + 1,
                'id' => $t->id,
                'name' => $t->name,
                'avatar_url' => MediaUrl::resolve($t->avatar),
                'bio' => $t->technicianProfile?->bio,
                'avg_rating' => (float) ($t->technicianProfile?->avg_rating ?? 0),
                'rating_count' => (int) ($t->technicianProfile?->rating_count ?? 0),
                'latest_reviews' => ReviewResource::collection($comments)->resolve(),
            ];
        })->all();

        return ApiResponse::success($data);
    }

    /** GET /api/v1/technicians/{id}/reviews  (semua ulasan satu teknisi, berpaginasi) */
    public function reviews(int $id): JsonResponse
    {
        $technician = $this->activeQuery()->with('technicianProfile')->findOrFail($id);

        $reviews = Review::where('target_type', 'technician')
            ->where('target_id', $technician->id)
            ->with('user:id,name,avatar')
            ->latest('id')
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
                'technician' => [
                    'id' => $technician->id,
                    'name' => $technician->name,
                    'avg_rating' => (float) ($technician->technicianProfile?->avg_rating ?? 0),
                    'rating_count' => (int) ($technician->technicianProfile?->rating_count ?? 0),
                ],
            ]
        );
    }

    private function activeQuery(): Builder
    {
        return User::query()
            ->where('users.role', 'technician')
            ->where('users.is_active', true)
            ->whereHas('technicianProfile', fn ($q) => $q->where('is_active', true));
    }
}