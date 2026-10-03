<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            // Snapshot supaya riwayat tidak berubah saat harga/nama diubah admin
            $table->string('product_name');
            $table->string('variant_label')->nullable(); // contoh: "iPhone 13 - Premium"
            $table->unsignedInteger('price');
            $table->unsignedInteger('base_fee')->default(0);
            $table->unsignedSmallInteger('qty')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};