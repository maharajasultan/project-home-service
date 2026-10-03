<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\IphoneModelController;
use App\Http\Controllers\Api\MidtransWebhookController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderTrackingController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\ServiceAreaController;
use App\Http\Controllers\Api\Technician\JobController;
use App\Http\Controllers\Api\Technician\PhotoController;
use App\Http\Controllers\Api\Technician\TrackingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ---------- Auth (publik) ----------
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
    });

    // ---------- Webhook Midtrans (publik, diamankan signature) ----------
    Route::post('midtrans/webhook', MidtransWebhookController::class)->middleware('throttle:120,1');

    // ---------- Katalog (publik) ----------
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('banners', [BannerController::class, 'index']);
        Route::get('iphone-models', [IphoneModelController::class, 'index']);
        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{id}', [ProductController::class, 'show'])->whereNumber('id');
        Route::get('products/{id}/reviews', [ProductController::class, 'reviews'])->whereNumber('id');
    });

    // ---------- Wajib login (semua role) ----------
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        // ---------- Khusus role User (pelanggan) ----------
        Route::middleware('role:user')->group(function () {
            // Keranjang (B3)
            Route::get('cart', [CartController::class, 'index']);
            Route::post('cart', [CartController::class, 'store']);
            Route::patch('cart/{id}', [CartController::class, 'update'])->whereNumber('id');
            Route::delete('cart/{id}', [CartController::class, 'destroy'])->whereNumber('id');
            Route::delete('cart', [CartController::class, 'clear']);

            // Alamat dan jadwal (B4)
            Route::get('service-areas', [ServiceAreaController::class, 'index']);
            Route::get('schedule/dates', [ScheduleController::class, 'dates']);
            Route::get('schedule/slots', [ScheduleController::class, 'slots']);

            // Pesanan dan riwayat (B4)
            Route::get('orders', [OrderController::class, 'index']);
            Route::post('orders', [OrderController::class, 'store'])->middleware('throttle:10,1');
            Route::get('orders/{id}', [OrderController::class, 'show'])->whereNumber('id');
            Route::post('orders/{id}/cancel', [OrderController::class, 'cancel'])->whereNumber('id');

            // Pembayaran (B5)
            Route::post('orders/{id}/pay', [PaymentController::class, 'pay'])
                ->whereNumber('id')->middleware('throttle:10,1');
            Route::post('orders/{id}/payment/sync', [PaymentController::class, 'sync'])
                ->whereNumber('id')->middleware('throttle:30,1');

            // Pelacakan teknisi (B6)
            Route::get('orders/{id}/tracking', [OrderTrackingController::class, 'show'])
                ->whereNumber('id')->middleware('throttle:60,1');

            // B7 (chat, review, garansi) menaruh route di sini.
        });

        // ---------- Khusus role Teknisi ----------
        Route::prefix('technician')->middleware('role:technician')->group(function () {
            // Pekerjaan (B6)
            Route::get('jobs', [JobController::class, 'index']);
            Route::get('jobs/{id}', [JobController::class, 'show'])->whereNumber('id');
            Route::post('jobs/{id}/start-trip', [JobController::class, 'startTrip'])->whereNumber('id');
            Route::post('jobs/{id}/complete', [JobController::class, 'complete'])->whereNumber('id');

            // Lokasi live (B6)
            Route::post('jobs/{id}/location', [TrackingController::class, 'update'])
                ->whereNumber('id')->middleware('throttle:30,1');

            // Foto sebelum dan sesudah (B6)
            Route::post('jobs/{id}/photos', [PhotoController::class, 'store'])
                ->whereNumber('id')->middleware('throttle:20,1');
            Route::delete('jobs/{id}/photos/{photoId}', [PhotoController::class, 'destroy'])
                ->whereNumber('id')->whereNumber('photoId');

            // B7 (chat teknisi dan balas ulasan) menaruh route di sini.
        });
    });
});