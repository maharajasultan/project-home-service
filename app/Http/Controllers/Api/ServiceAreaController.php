<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AddressValidatorService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ServiceAreaController extends Controller
{
    /** GET /api/v1/service-areas  (dropdown kecamatan di form alamat) */
    public function index(AddressValidatorService $address): JsonResponse
    {
        $label = config('reaple.service_area.label');

        return ApiResponse::success([
            'area' => $label,
            'districts' => $address->districts(),
            'note' => "Layanan home service saat ini hanya tersedia di wilayah {$label}.",
        ]);
    }
}