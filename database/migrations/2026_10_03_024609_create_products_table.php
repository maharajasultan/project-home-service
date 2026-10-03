<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type', 20)->index(); // sparepart | matot | unlock_imei
            $table->text('description')->nullable();
            $table->json('images')->nullable();
            // Biaya dasar per item: ongkir/pengecekan (dipakai Matot). Sparepart umumnya 0.
            $table->unsignedInteger('base_fee')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};