<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\TechnicianController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WarrantyClaimController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\ChatController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductVariantController;
use App\Http\Controllers\Admin\ReviewController;

Route::get('/', function () {
    return redirect()->route('admin.login');
});

// Halaman tujuan setelah Snap selesai, ditangkap WebView Flutter (B5).
Route::get('/payment/finish', function () {
    return response(
        '<!doctype html><html lang="id"><head><meta charset="utf-8">'
        .'<meta name="viewport" content="width=device-width, initial-scale=1">'
        .'<title>Pembayaran</title></head>'
        .'<body style="font-family:sans-serif;text-align:center;padding:48px 24px">'
        .'<h2>Pembayaran sedang diproses</h2>'
        .'<p>Silakan kembali ke aplikasi reaple.id.</p></body></html>'
    );
});

// ======================= Admin Panel =======================
Route::prefix('admin')->name('admin.')->group(function () {

    Route::get('/', fn () => redirect()->route('admin.dashboard'));
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->middleware('throttle:5,1')->name('login.submit');

    Route::middleware('admin')->group(function () {
        Route::post('logout', [LoginController::class, 'logout'])->name('logout');

        Route::get('dashboard', DashboardController::class)->name('dashboard');

        // Transaksi
        Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('transactions/{id}', [TransactionController::class, 'show'])->whereNumber('id')->name('transactions.show');
        Route::post('transactions/{id}/cancel', [TransactionController::class, 'cancel'])->whereNumber('id')->name('transactions.cancel');

        // Klaim garansi
        Route::get('warranty-claims', [WarrantyClaimController::class, 'index'])->name('warranty.index');
        Route::patch('warranty-claims/{id}', [WarrantyClaimController::class, 'update'])->whereNumber('id')->name('warranty.update');

        // Pelanggan
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{id}/edit', [UserController::class, 'edit'])->whereNumber('id')->name('users.edit');
        Route::put('users/{id}', [UserController::class, 'update'])->whereNumber('id')->name('users.update');
        Route::post('users/{id}/toggle', [UserController::class, 'toggle'])->whereNumber('id')->name('users.toggle');
        Route::delete('users/{id}', [UserController::class, 'destroy'])->whereNumber('id')->name('users.destroy');

        // Teknisi
        Route::get('technicians', [TechnicianController::class, 'index'])->name('technicians.index');
        Route::get('technicians/create', [TechnicianController::class, 'create'])->name('technicians.create');
        Route::post('technicians', [TechnicianController::class, 'store'])->name('technicians.store');
        Route::get('technicians/{id}/edit', [TechnicianController::class, 'edit'])->whereNumber('id')->name('technicians.edit');
        Route::put('technicians/{id}', [TechnicianController::class, 'update'])->whereNumber('id')->name('technicians.update');
        Route::post('technicians/{id}/toggle', [TechnicianController::class, 'toggle'])->whereNumber('id')->name('technicians.toggle');
        Route::delete('technicians/{id}', [TechnicianController::class, 'destroy'])->whereNumber('id')->name('technicians.destroy');

        // B9 dan B10 menambahkan route di sini.
                // ---------- Barang & Stok (B9) ----------
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::get('products/{id}/edit', [ProductController::class, 'edit'])->whereNumber('id')->name('products.edit');
        Route::put('products/{id}', [ProductController::class, 'update'])->whereNumber('id')->name('products.update');
        Route::post('products/{id}/toggle', [ProductController::class, 'toggle'])->whereNumber('id')->name('products.toggle');
        Route::delete('products/{id}', [ProductController::class, 'destroy'])->whereNumber('id')->name('products.destroy');

        Route::get('products/{id}/variants', [ProductVariantController::class, 'index'])->whereNumber('id')->name('products.variants');
        Route::post('products/{id}/variants/generate', [ProductVariantController::class, 'generate'])->whereNumber('id')->name('products.variants.generate');
        Route::post('products/{id}/variants/duration', [ProductVariantController::class, 'storeDuration'])->whereNumber('id')->name('products.variants.duration');
        Route::patch('products/{id}/variants/prices', [ProductVariantController::class, 'updatePrices'])->whereNumber('id')->name('products.variants.prices');
        Route::post('products/{id}/variants/{variantId}/stock', [ProductVariantController::class, 'adjustStock'])
            ->whereNumber(['id', 'variantId'])->name('products.variants.stock');
        Route::delete('products/{id}/variants/{variantId}', [ProductVariantController::class, 'destroy'])
            ->whereNumber(['id', 'variantId'])->name('products.variants.destroy');

        // ---------- Banner (B9) ----------
        Route::get('banners', [BannerController::class, 'index'])->name('banners.index');
        Route::get('banners/create', [BannerController::class, 'create'])->name('banners.create');
        Route::post('banners', [BannerController::class, 'store'])->name('banners.store');
        Route::get('banners/{id}/edit', [BannerController::class, 'edit'])->whereNumber('id')->name('banners.edit');
        Route::put('banners/{id}', [BannerController::class, 'update'])->whereNumber('id')->name('banners.update');
        Route::post('banners/{id}/toggle', [BannerController::class, 'toggle'])->whereNumber('id')->name('banners.toggle');
        Route::delete('banners/{id}', [BannerController::class, 'destroy'])->whereNumber('id')->name('banners.destroy');

        // ---------- Ulasan (B9) ----------
        Route::get('reviews', [ReviewController::class, 'index'])->name('reviews.index');
        Route::patch('reviews/{id}/reply', [ReviewController::class, 'reply'])->whereNumber('id')->name('reviews.reply');
        Route::delete('reviews/{id}', [ReviewController::class, 'destroy'])->whereNumber('id')->name('reviews.destroy');

        // ---------- Chat (B9) ----------
        Route::get('chat', [ChatController::class, 'index'])->name('chat.index');
        Route::get('chat/{userId}/messages', [ChatController::class, 'messages'])->whereNumber('userId')->name('chat.messages');
        Route::post('chat/{userId}/messages', [ChatController::class, 'send'])
            ->whereNumber('userId')->middleware('throttle:60,1')->name('chat.send');

        // B10 menambahkan route laporan di sini.
    });
});