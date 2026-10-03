<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class BannerController extends Controller
{
    /** GET /api/v1/banners  (carousel di Home) */
    public function index(): JsonResponse
    {
        $banners = Banner::active()->get();

        return ApiResponse::success(BannerResource::collection($banners)->resolve());
    }
}