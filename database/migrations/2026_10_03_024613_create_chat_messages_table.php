<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();
            // Terisi = chat pesanan (user <-> teknisi). Kosong = chat umum (user <-> admin)
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->text('message');
            $table->dateTime('read_at')->nullable();
            $table->timestamps();

            $table->index(['sender_id', 'receiver_id']);
            $table->index(['order_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};