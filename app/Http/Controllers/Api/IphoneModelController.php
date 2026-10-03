<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IphoneModel;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class IphoneModelController extends Controller
{
    /** GET /api/v1/iphone-models */
    public function index(): JsonResponse
    {
        $models = IphoneModel::active()->get(['id', 'name']);

        return ApiResponse::success($models);
    }
}