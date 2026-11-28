<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_cart_routes_remain_disabled_for_the_direct_checkout_flow(): void
    {
        // The current ticket picker submits its selection directly to checkout.
        // Keep this regression test so an old cart implementation is not exposed accidentally.
        $this->get('/cart')->assertNotFound();
        $this->get('/cart/1')->assertNotFound();
    }
}
