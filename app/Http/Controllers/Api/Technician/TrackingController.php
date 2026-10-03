<?php

namespace App\Http\Controllers\Api\Technician;

use App\Http\Controllers\Controller;
use App\Http\Requests\Technician\UpdateLocationRequest;
use App\Services\TechnicianJobService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TrackingController extends Controller
{
    public function __construct(private readonly TechnicianJobService $jobs)
    {
    }

    /** POST /api/v1/technician/jobs/{id}/location  { latitude, longitude }  (dikirim tiap ±10 detik) */
    public function update(UpdateLocationRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();

        $location = $this->jobs->updateLocation(
            $request->user(),
            $id,
            (float) $data['latitude'],
            (float) $data['longitude'],
        );

        return ApiResponse::success([
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'updated_at' => $location->updated_at->toIso8601String(),
        ], 'Lokasi diperbarui.');
    }
}