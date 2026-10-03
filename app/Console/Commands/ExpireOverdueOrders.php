<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

class ExpireOverdueOrders extends Command
{
    protected $signature = 'orders:expire';

    protected $description = 'Gagalkan pesanan yang melewati batas bayar, kembalikan stok dan bebaskan slot teknisi.';

    public function handle(OrderService $orders): int
    {
        $count = $orders->expireOverdue();

        $this->info("{$count} pesanan kedaluwarsa diproses.");

        return self::SUCCESS;
    }
}