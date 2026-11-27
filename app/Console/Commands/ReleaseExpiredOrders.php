<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

class ReleaseExpiredOrders extends Command
{
    protected $signature = 'tixora:release-expired';

    protected $description = 'Expire unpaid orders past their deadline and return reserved stock';

    public function handle(OrderService $orders): int
    {
        $this->info('Released ' . $orders->expireDue() . ' expired order(s).');

        return self::SUCCESS;
    }
}
