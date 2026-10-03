<?php

namespace App\Http\Controllers\Api\Technician;

use App\Http\Controllers\Controller;
use App\Http\Requests\Technician\UploadPhotoRequest;
use App\Http\Resources\TechnicianJobResource;
use App\Services\TechnicianJobService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhotoController extends Controller
{
    public function __construct(private readonly TechnicianJobService $jobs)
    {
    }

    /** POST /api/v1/technician/jobs/{id}/photos  (multipart: type=before|after, photo=file) */
    public function store(UploadPhotoRequest $request, int $id): JsonResponse
    {
        $this->jobs->addPhoto(
            $request->user(),
            $id,
            $request->validated('type'),
            $request->file('photo'),
        );

        return ApiResponse::success($this->payload($request, $id), 'Foto berhasil diunggah.', 201);
    }

    /** DELETE /api/v1/technician/jobs/{id}/photos/{photoId} */
    public function destroy(Request $request, int $id, int $photoId): JsonResponse
    {
        $this->jobs->removePhoto($request->user(), $id, $photoId);

        return ApiResponse::success($this->payload($request, $id), 'Foto dihapus.');
    }

    private function payload(Request $request, int $id): array
    {
        return (new TechnicianJobResource($this->jobs->find($request->user(), $id)))->resolve();
    }
}