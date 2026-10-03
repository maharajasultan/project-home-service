<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->string('category', 30)->nullable()->after('type')->index();
        });

        // Isi data lama.
        DB::table('finance_transactions')->where('type', 'in')->update(['category' => 'penjualan']);

        DB::table('finance_transactions')
            ->where('type', 'out')
            ->where('description', 'like', 'Pembelian stok%')
            ->update(['category' => 'pembelian_stok']);

        DB::table('finance_transactions')
            ->where('type', 'out')
            ->whereNull('category')
            ->update(['category' => 'lainnya']);
    }

    public function down(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }
};