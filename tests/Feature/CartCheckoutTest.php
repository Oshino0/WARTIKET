<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_add_tickets_from_multiple_events_then_checkout_the_cart(): void
    {
        $first = $this->ticketType('Festival', 'Reguler', 100000);
        $second = $this->ticketType('Comedy Night', 'VIP', 150000);

        $this->post(route('cart.store', $first->product), [
            'items' => [$first->id => 2],
        ])->assertRedirect(route('cart.index'));

        $this->post(route('cart.store', $second->product), [
            'items' => [$second->id => 1],
        ])->assertRedirect(route('cart.index'))
            ->assertSessionHas('cart', [$first->id => 2, $second->id => 1]);

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Festival')
            ->assertSee('Comedy Night')
            ->assertSee('Lanjutkan tanpa akun');

        $this->get(route('checkout.review'))
            ->assertOk()
            ->assertSee('Data pembeli &amp; pesanan', false)
            ->assertSee('Nama pembeli')
            ->assertSee('Alamat email')
            ->assertSee('Nomor WhatsApp');

        $this->post(route('checkout.store'), [
            'buyer_name' => 'Ayu Pembeli',
            'buyer_email' => 'ayu@example.test',
            'buyer_phone' => '+6281234567890',
            'items' => [$first->id => 2, $second->id => 1],
        ])->assertRedirect();

        $this->assertDatabaseCount('orders', 1);
        $order = Order::sole();
        $this->assertNull($order->user_id);
        $this->assertSame(Order::PENDING_VERIFICATION, $order->status);
        $this->assertSame('Ayu Pembeli', $order->buyer_name);
        $this->assertSame('ayu@example.test', $order->buyer_email);
        $this->assertSame('+6281234567890', $order->buyer_phone);
        $this->assertSame(2, $first->fresh()->reserved);
        $this->assertSame(1, $second->fresh()->reserved);
        $this->assertFalse(session()->has('cart'));
        $this->get(route('checkout.confirmation', $order->order_number))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Menunggu verifikasi admin');
    }

    public function test_cart_rejects_quantities_above_available_stock(): void
    {
        $type = $this->ticketType('Small Show', 'Reguler', 50000, quota: 1);

        $this->post(route('cart.store', $type->product), [
            'items' => [$type->id => 2],
        ])->assertSessionHasErrors('items');

        $this->assertFalse(session()->has('cart'));
    }

    public function test_cart_can_be_updated_and_recovers_from_stock_changes(): void
    {
        $type = $this->ticketType('Theatre Night', 'Reguler', 75000, quota: 5);

        $this->post(route('cart.store', $type->product), [
            'items' => [$type->id => 2],
        ]);
        $this->patch(route('cart.update', $type->id), ['quantity' => 3])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('cart', [$type->id => 3]);

        $type->update(['quota' => 1]);
        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Stok tersisa 1 tiket')
            ->assertDontSee('Lanjut ke checkout');

        $this->delete(route('cart.destroy', $type->id))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('cart', []);
        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Keranjangmu masih kosong');
    }

    public function test_empty_cart_cannot_start_checkout(): void
    {
        $this->get(route('checkout.review'))
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('status', 'Keranjang masih kosong. Pilih tiket sebelum checkout.');

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Keranjangmu masih kosong');
    }

    public function test_product_urls_use_numeric_ids_and_legacy_slugs_redirect(): void
    {
        $type = $this->ticketType('Festival', 'Reguler', 100000);
        $product = $type->product;
        $productUrl = route('products.show', $product);
        $cartUrl = route('cart.store', $product);

        $this->assertSame('/tickets/'.$product->id, parse_url($productUrl, PHP_URL_PATH));
        $this->assertSame('/tickets/'.$product->id.'/cart', parse_url($cartUrl, PHP_URL_PATH));
        $this->get($productUrl)
            ->assertOk()
            ->assertSee($cartUrl, false);
        $this->get(route('explore'))
            ->assertOk()
            ->assertSee($productUrl, false);

        $this->get(route('products.show', $product->slug))
            ->assertRedirect($productUrl);

        $product->update(['name' => 'Festival Baru']);
        $this->get($productUrl)
            ->assertOk()
            ->assertSee('Festival Baru');
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
