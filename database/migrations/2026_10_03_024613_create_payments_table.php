<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('midtrans_order_id')->unique();
            $table->string('method', 30)->nullable();       // bca_va, bri_va, indomaret, ...
            $table->string('status', 30)->default('pending'); // pending|settlement|expire|cancel|deny
            $table->unsignedInteger('gross_amount');
            $table->string('snap_token')->nullable();
            $table->text('redirect_url')->nullable();
            $table->json('payload')->nullable();             // notifikasi mentah dari Midtrans
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};