<?php

namespace App\Events;

use App\Models\Order;

/**
 * Fired (after the DB commit) once an order becomes paid.
 * Phase 5 listens to this to issue tickets and QR codes.
 */
class OrderPaid
{
    public function __construct(public Order $order)
    {
    }
}
