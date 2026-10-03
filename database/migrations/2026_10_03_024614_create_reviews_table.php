<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('target_type', 20); // technician | product
            $table->unsignedBigInteger('target_id'); // users.id (teknisi) atau products.id
            $table->unsignedTinyInteger('rating'); // 1-5
            $table->text('comment')->nullable();
            $table->text('reply')->nullable();     // balasan teknisi/admin
            $table->dateTime('replied_at')->nullable();
            $table->timestamps();

            $table->index(['target_type', 'target_id']);
            $table->unique(['order_id', 'user_id', 'target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};