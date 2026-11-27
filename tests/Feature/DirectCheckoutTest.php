<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_picker_goes_straight_to_checkout_without_a_cart(): void
    {
        $type = $this->ticketType('Festival', 'Reguler', 100000);

        $this->get(route('products.show', $type->product))
            ->assertOk()
            ->assertSee(route('checkout.review'), false)
            ->assertSee('Checkout')
            ->assertDontSee('Keranjang')
            ->assertDontSee('Tambah ke keranjang');

        $this->get(route('home'))->assertOk()->assertDontSee('Keranjang');
        $this->get('/cart')->assertNotFound();
    }

    public function test_checkout_without_a_selection_sends_the_visitor_back_to_browse(): void
    {
        $this->get(route('checkout.review'))->assertRedirect(route('explore'));
        $this->get(route('checkout.review', ['items' => [1 => 0]]))->assertRedirect(route('explore'));
    }

    public function test_checkout_shows_the_summary_and_buyer_form_for_the_selected_amount(): void
    {
        $type = $this->ticketType('Festival', 'Reguler', 100000);

        $this->get(route('checkout.review', ['items' => [$type->id => 2]]))
            ->assertOk()
            ->assertSee('Festival')
            ->assertSee('Rp 200.000')
            ->assertSee('Nama pembeli')
            ->assertSee('Alamat email')
            ->assertSee('Nomor WhatsApp');

        $this->assertSame(0, $type->fresh()->reserved, 'nothing is reserved until the order is placed');
    }

    public function test_checkout_rejects_more_tickets_than_are_left(): void
    {
        $type = $this->ticketType('Small Show', 'Reguler', 50000, quota: 1);

        $this->from(route('products.show', $type->product))
            ->get(route('checkout.review', ['items' => [$type->id => 2]]))
            ->assertRedirect(route('products.show', $type->product))
            ->assertSessionHasErrors('items');
    }

    public function test_placing_the_order_reserves_stock_and_shows_the_confirmation(): void
    {
        $type = $this->ticketType('Festival', 'Reguler', 100000);

        $this->post(route('checkout.store'), [
            'buyer_name' => 'Ayu Pembeli',
            'buyer_email' => 'ayu@example.test',
            'buyer_phone' => '+6281234567890',
            'items' => [$type->id => 2],
        ])->assertRedirect();

        $order = Order::sole();
        $this->assertNull($order->user_id);
        $this->assertSame(Order::PENDING_VERIFICATION, $order->status);
        $this->assertSame(2, $type->fresh()->reserved);

        $this->get(route('checkout.confirmation', $order->order_number))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Menunggu verifikasi admin');
    }

    public function test_product_urls_use_numeric_ids_and_legacy_slugs_redirect(): void
    {
        $type = $this->ticketType('Festival', 'Reguler', 100000);
        $product = $type->product;
        $productUrl = route('products.show', $product);

        $this->assertSame('/tickets/'.$product->id, parse_url($productUrl, PHP_URL_PATH));
        $this->get($productUrl)->assertOk();
        $this->get(route('explore'))->assertOk()->assertSee($productUrl, false);
        $this->get(route('products.show', $product->slug))->assertRedirect($productUrl);
    }

    private function ticketType(string $event, string $name, int $price, int $quota = 10): TicketType
    {
        $category = Category::create([
            'name' => 'Konser',
            'slug' => 'konser-'.strtolower(str_replace(' ', '-', $event)),
            'is_active' => true,
        ]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => $event,
            'slug' => strtolower(str_replace(' ', '-', $event)),
            'status' => Product::PUBLISHED,
        ]);

        return TicketType::create([
            'product_id' => $product->id,
            'name' => $name,
            'price' => $price,
            'quota' => $quota,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'status' => TicketType::ACTIVE,
        ]);
    }
}
