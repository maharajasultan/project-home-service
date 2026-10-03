<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('iphone_model_id')->nullable()->constrained()->nullOnDelete();
            $table->string('grade', 20)->nullable();            // standar | premium | original
            $table->unsignedSmallInteger('duration_months')->nullable(); // khusus Unlock IMEI
            $table->unsignedInteger('price')->default(0);
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'iphone_model_id', 'grade']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};