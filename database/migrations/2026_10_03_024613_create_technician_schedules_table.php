<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('time_slot');
            $table->timestamps();

            // Mencegah double booking di level database
            $table->unique(['technician_id', 'date', 'time_slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_schedules');
    }
};