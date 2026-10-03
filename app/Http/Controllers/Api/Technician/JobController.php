<?php

namespace App\Http\Controllers\Api\Technician;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Technician\StartTripRequest;
use App\Http\Resources\TechnicianJobResource;
use App\Models\Order;
use App\Services\TechnicianJobService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JobController extends Controller
{
    public function __construct(private readonly TechnicianJobService $jobs)
    {
    }

    /** GET /api/v1/technician/jobs?tab=incoming|active|completed&per_page=&page= */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'tab' => ['nullable', Rule::in(['incoming', 'active', 'completed'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $tab = $filters['tab'] ?? 'incoming';
        $technicianId = $request->user()->id;

        $statuses = match ($tab) {
            'incoming' => [OrderStatus::Paid->value],
            'active' => [OrderStatus::OnTheWay->value, OrderStatus::InProgress->value],
            default => [OrderStatus::Completed->value],
        };

        $counts = Order::where('technician_id', $technicianId)
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $query = Order::where('technician_id', $technicianId)
            ->whereIn('status', $statuses)
            ->with(['user', 'items.product', 'photos']);

        $tab === 'completed'
            ? $query->orderByDesc('completed_at')->orderByDesc('id')
            : $query->orderBy('scheduled_at')->orderBy('id');

        $paginator = $query->paginate($filters['per_page'] ?? 10);

        return ApiResponse::success(
            TechnicianJobResource::collection($paginator->getCollection())->resolve(),
            'OK',
            200,
            [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'counts' => [
                    'incoming' => (int) ($counts[OrderStatus::Paid->value] ?? 0),
                    'active' => (int) (($counts[OrderStatus::OnTheWay->value] ?? 0) + ($counts[OrderStatus::InProgress->value] ?? 0)),
                    'completed' => (int) ($counts[OrderStatus::Completed->value] ?? 0),
                ],
            ]
        );
    }

    /** GET /api/v1/technician/jobs/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        return ApiResponse::success($this->payload($request, $id));
    }

    /** POST /api/v1/technician/jobs/{id}/start-trip  { latitude?, longitude? } */
    public function startTrip(StartTripRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();

        $this->jobs->startTrip(
            $request->user(),
            $id,
            isset($data['latitude']) ? (float) $data['latitude'] : null,
            isset($data['longitude']) ? (float) $data['longitude'] : null,
        );

        return ApiResponse::success($this->payload($request, $id), 'Perjalanan dimulai. Lokasi Anda dapat dipantau pelanggan.');
    }

    /** POST /api/v1/technician/jobs/{id}/complete */
    public function complete(Request $request, int $id): JsonResponse
    {
        $this->jobs->complete($request->user(), $id);

        return ApiResponse::success($this->payload($request, $id), 'Pesanan selesai. Terima kasih!');
    }

    private function payload(Request $request, int $id): array
    {
        return (new TechnicianJobResource($this->jobs->find($request->user(), $id)))->resolve();
    }
}