<?php

namespace Tests\Feature;

use App\Mail\GuestOrderTickets;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GuestCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_checkout_validates_contact_information(): void
    {
        $type = $this->ticketType();
        $this->get(route('checkout.review', ['items' => [$type->id => 1]]))->assertOk();

        $this->post(route('checkout.store'), [
            'buyer_name' => 'Al',
            'buyer_email' => 'not-an-email',
            'buyer_phone' => '123',
            'items' => [$type->id => 1],
        ])
            ->assertRedirect()
            ->assertSessionHasErrors(['buyer_name', 'buyer_email', 'buyer_phone']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(0, $type->fresh()->reserved);
    }

    public function test_guest_order_is_private_and_requires_no_customer_account(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Login')
            ->assertDontSee('Daftar')
            ->assertDontSee('Profil')
            ->assertDontSee('/admin');

        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('Masuk admin');
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));

        [$order] = $this->placeGuestOrder();

        $this->get(route('checkout.confirmation', $order->order_number))
            ->assertOk()
            ->assertSee($order->buyer_email);

        $this->withSession(['guest_order_number' => 'different-order'])
            ->get(route('checkout.confirmation', $order->order_number))
            ->assertNotFound();
    }

    public function test_only_admins_can_authenticate_through_admin_portal(): void
    {
        $customer = $this->user(User::ROLE_CUSTOMER, 'customer@example.test');

        $this->post(route('admin.authenticate'), [
            'email' => $customer->email,
            'password' => 'password12',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $admin = $this->user(User::ROLE_ADMIN, 'admin@example.test');
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.authenticate'), [
            'email' => $admin->email,
            'password' => 'password12',
        ])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);

        $this->actingAs($customer)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_admin_login_always_lands_on_the_dashboard(): void
    {
        $admin = $this->user(User::ROLE_ADMIN, 'admin@example.test');

        $this->withSession(['url.intended' => 'http://localhost/tickets/1'])
            ->post(route('admin.authenticate'), ['email' => $admin->email, 'password' => 'password12'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_admin_confirmation_creates_tickets_and_emails_embedded_qr_codes(): void
    {
        Mail::fake();
        config([
            'mail.default' => 'resend',
            'services.resend.key' => 're_test_key',
            'mail.from.address' => 'tickets@example.test',
        ]);
        [$order, $type] = $this->placeGuestOrder(quantity: 2);
        $admin = $this->user(User::ROLE_ADMIN, 'admin@example.test');

        $this->actingAs($admin)
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee($order->buyer_name)
            ->assertSee($order->buyer_phone)
            ->assertSee('Menunggu verifikasi');

        $this->post(route('admin.orders.verify', $order))
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHas('status', 'Tiket dibuat dan email berhasil dikirim.');

        $this->assertSame(Order::SENT, $order->fresh()->status);
        $this->assertDatabaseCount('tickets', 2);
        $this->assertSame(2, $type->fresh()->sold);
        $this->assertSame(0, $type->fresh()->reserved);

        $ticketCodes = $order->items()->with('tickets')->get()->flatMap(
            fn ($item) => $item->tickets->pluck('ticket_code'),
        )->all();

        Mail::assertSent(GuestOrderTickets::class);
        $mail = Mail::sent(GuestOrderTickets::class)->sole();
        $this->assertTrue($mail->hasTo($order->buyer_email));
        $html = $mail->render();
        // PNG, not SVG: Gmail/Outlook/Apple Mail show SVG images as broken.
        $this->assertStringNotContainsString('image/svg+xml', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
        preg_match_all('/data:image\/png;base64,([A-Za-z0-9+\/=\s]+?)"/', $html, $ticketImages);
        $encodedQr = preg_replace('/\s+/', '', $ticketImages[1][0] ?? '');
        $this->assertNotSame('', $encodedQr);
        $decodedQr = base64_decode($encodedQr, true);
        $this->assertIsString($decodedQr);
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($decodedQr, 0, 8));
        $this->assertCount(2, $ticketImages[1], 'one e-ticket image per ticket');
        $dimensions = array_map(
            fn ($image) => array_slice(getimagesizefromstring(base64_decode(preg_replace('/\s+/', '', $image))), 0, 2),
            $ticketImages[1],
        );
        $this->assertSame([[1320, 560], [1320, 560]], $dimensions);
        foreach ($ticketCodes as $ticketCode) {
            $this->assertStringContainsString($ticketCode, $html);
        }

        $this->post(route('admin.orders.resend', $order))
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHas('status', 'E-tiket berhasil dikirim ulang.');

        Mail::assertSent(GuestOrderTickets::class, 2);
        $this->assertDatabaseCount('tickets', 2);
    }

    public function test_admin_cannot_mark_mail_as_sent_when_log_transport_is_active(): void
    {
        Mail::fake();
        config(['mail.default' => 'log']);
        [$order, $type] = $this->placeGuestOrder();
        $admin = $this->user(User::ROLE_ADMIN, 'admin@example.test');

        $this->actingAs($admin)
            ->post(route('admin.orders.verify', $order))
            ->assertSessionHasErrors('order');

        $this->assertSame(Order::PENDING_VERIFICATION, $order->fresh()->status);
        $this->assertSame(1, $type->fresh()->reserved);
        $this->assertSame(0, $type->fresh()->sold);
        $this->assertDatabaseCount('tickets', 0);
        Mail::assertNothingSent();
    }

    public function test_admin_cannot_send_with_resend_until_api_key_is_configured(): void
    {
        Mail::fake();
        config([
            'mail.default' => 'resend',
            'services.resend.key' => null,
        ]);
        [$order, $type] = $this->placeGuestOrder();
        $admin = $this->user(User::ROLE_ADMIN, 'admin@example.test');

        $this->actingAs($admin)
            ->post(route('admin.orders.verify', $order))
            ->assertSessionHasErrors('order');

        $this->assertSame(Order::PENDING_VERIFICATION, $order->fresh()->status);
        $this->assertSame(1, $type->fresh()->reserved);
        $this->assertDatabaseCount('tickets', 0);
        Mail::assertNothingSent();
    }

    public function test_admin_cannot_send_from_an_unverified_default_sender(): void
    {
        Mail::fake();
        config([
            'mail.default' => 'resend',
            'services.resend.key' => 're_test_key',
            'mail.from.address' => 'hello@example.com',
        ]);
        [$order, $type] = $this->placeGuestOrder();
        $admin = $this->user(User::ROLE_ADMIN, 'admin@example.test');

        $this->actingAs($admin)
            ->post(route('admin.orders.verify', $order))
            ->assertSessionHasErrors('order');

        $this->assertSame(Order::PENDING_VERIFICATION, $order->fresh()->status);
        $this->assertSame(1, $type->fresh()->reserved);
        $this->assertDatabaseCount('tickets', 0);
        Mail::assertNothingSent();
    }

    public function test_admin_can_reject_guest_order_and_release_reserved_stock(): void
    {
        [$order, $type] = $this->placeGuestOrder(quantity: 2);
        $admin = $this->user(User::ROLE_ADMIN, 'admin@example.test');

        $this->actingAs($admin)
            ->post(route('admin.orders.reject', $order))
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHas('status', 'Pesanan ditolak dan stok tiket dikembalikan.');

        $this->assertSame(Order::REJECTED, $order->fresh()->status);
        $this->assertSame(0, $type->fresh()->reserved);
        $this->assertSame(0, $type->fresh()->sold);
        $this->assertDatabaseCount('tickets', 0);
    }

    private function placeGuestOrder(int $quantity = 1): array
    {
        $type = $this->ticketType();
        $this->post(route('checkout.store'), [
            'buyer_name' => 'Ayu Pembeli',
            'buyer_email' => 'buyer@example.test',
            'buyer_phone' => '+6281234567890',
            'items' => [$type->id => $quantity],
        ])->assertRedirect();

        return [Order::sole(), $type];
    }

    private function ticketType(int $quota = 8): TicketType
    {
        $category = Category::create([
            'name' => 'Konser',
            'slug' => 'konser',
            'is_active' => true,
        ]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Festival',
            'slug' => 'festival',
            'status' => Product::PUBLISHED,
        ]);

        return TicketType::create([
            'product_id' => $product->id,
            'name' => 'Reguler',
            'price' => 100000,
            'quota' => $quota,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'status' => TicketType::ACTIVE,
        ]);
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => $email,
            'password' => 'password12',
            'role' => $role,
            'is_active' => true,
        ]);
    }
}
