<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_code', 30)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending')->index();

            $table->text('address');
            $table->string('district', 100)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('phone_wa', 20);
            $table->text('notes')->nullable();

            $table->dateTime('scheduled_at');
            $table->unsignedInteger('subtotal')->default(0);
            $table->unsignedInteger('service_fee')->default(0);
            $table->unsignedInteger('total')->default(0);

            $table->dateTime('expired_at')->nullable();   // batas bayar 10 menit (dari server)
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['technician_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};