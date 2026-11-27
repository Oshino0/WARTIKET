<?php

namespace Tests\Feature;

use App\Mail\GuestOrderTickets;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Services\EmailTemplate;
use App\Services\TicketQrImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmailSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_open_or_change_the_email_settings(): void
    {
        $customer = $this->user(User::ROLE_CUSTOMER, 'customer@example.test');

        $this->get(route('admin.email.edit'))->assertRedirect();
        $this->actingAs($customer)->get(route('admin.email.edit'))->assertForbidden();
        $this->actingAs($customer)->put(route('admin.email.update'), $this->payload())->assertForbidden();
    }

    public function test_admin_wording_is_used_in_the_ticket_email(): void
    {
        Storage::fake('public');

        $this->actingAs($this->user(User::ROLE_ADMIN, 'admin@example.test'))
            ->put(route('admin.email.update'), $this->payload([
                'subject' => 'Tiket kamu {nomor_pesanan}',
                'heading' => 'Terima kasih {nama}!',
                'greeting' => 'Halo {nama}, ini tiketmu.',
                'intro' => "Baris satu\nBaris dua",
                'footer' => 'Sampai jumpa di acara.',
                'accent_color' => '#ff5500',
            ]))
            ->assertRedirect(route('admin.email.edit'))
            ->assertSessionHasNoErrors();

        $order = $this->orderWithTicket();
        $mail = new GuestOrderTickets($order);
        $html = $mail->render();

        $this->assertSame('Tiket kamu '.$order->order_number, $mail->envelope()->subject);
        $this->assertStringContainsString('Terima kasih Ayu!', $html);
        $this->assertStringContainsString('Halo Ayu, ini tiketmu.', $html);
        $this->assertStringContainsString('Baris satu', $html);
        $this->assertStringContainsString('Sampai jumpa di acara.', $html);
        $this->assertStringContainsString('#FF5500', $html);
        $this->assertStringNotContainsString('Tunjukkan QR code tiket saat', $html, 'the default footer was replaced');
    }

    public function test_a_blank_intro_and_footer_stay_blank(): void
    {
        $this->actingAs($this->user(User::ROLE_ADMIN, 'admin@example.test'))
            ->put(route('admin.email.update'), $this->payload(['intro' => '', 'footer' => '']))
            ->assertSessionHasNoErrors();

        $html = (new GuestOrderTickets($this->orderWithTicket()))->render();

        $this->assertStringNotContainsString('Tunjukkan QR code tiket saat', $html);
    }

    public function test_invalid_colour_is_rejected(): void
    {
        $this->actingAs($this->user(User::ROLE_ADMIN, 'admin@example.test'))
            ->put(route('admin.email.update'), $this->payload(['accent_color' => 'red; background:url(x)']))
            ->assertSessionHasErrors('accent_color');
    }

    public function test_qr_is_placed_on_the_uploaded_background_and_removing_it_restores_the_plain_qr(): void
    {
        Storage::fake('public');
        $admin = $this->user(User::ROLE_ADMIN, 'admin@example.test');

        $this->actingAs($admin)
            ->put(route('admin.email.update'), $this->payload([
                'qr_background' => UploadedFile::fake()->image('latar.png', 800, 600),
            ]))
            ->assertSessionHasNoErrors();

        $path = EmailTemplate::all()['qr_background'];
        Storage::disk('public')->assertExists($path);

        [$width, $height] = getimagesizefromstring(TicketQrImage::png('TOKEN-123'));
        $this->assertSame(640, $width);
        $this->assertSame(640, $height);

        $this->actingAs($admin)
            ->put(route('admin.email.update'), $this->payload(['remove_qr_background' => '1']))
            ->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing($path);
        $this->assertNull(EmailTemplate::all()['qr_background']);
        $this->assertNotSame(640, getimagesizefromstring(TicketQrImage::png('TOKEN-123'))[0]);
    }

    public function test_a_broken_background_file_never_blocks_the_qr(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('email/broken.png', 'this is not an image');
        EmailTemplate::save(array_merge(EmailTemplate::DEFAULTS, ['qr_background' => 'email/broken.png']));

        $png = TicketQrImage::png('TOKEN-123');

        $this->assertSame("\x89PNG\r\n\x1a\n", substr($png, 0, 8));
    }

    public function test_a_broken_background_does_not_remove_ticket_details_from_the_image(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('email/broken.png', 'this is not an image');
        $template = array_merge(EmailTemplate::DEFAULTS, ['qr_background' => 'email/broken.png']);

        $png = TicketQrImage::png('TOKEN-123', $template, [
            'event' => 'Festival Senja',
            'ticket_type' => 'Reguler',
            'date' => '12 Des 2026, 19.00',
            'location' => 'Jakarta',
            'holder' => 'Ayu Pembeli',
            'phone' => '081234567890',
            'ticket_code' => 'WTK-001',
            'order_number' => 'WTK-TEST-001',
        ]);

        $this->assertSame([800, 1000], array_slice(getimagesizefromstring($png), 0, 2));
    }

    public function test_reset_restores_the_default_wording_and_deletes_the_background(): void
    {
        Storage::fake('public');
        $admin = $this->user(User::ROLE_ADMIN, 'admin@example.test');

        $this->actingAs($admin)->put(route('admin.email.update'), $this->payload([
            'heading' => 'Judul khusus',
            'qr_background' => UploadedFile::fake()->image('latar.png', 800, 800),
        ]));
        $path = EmailTemplate::all()['qr_background'];

        $this->actingAs($admin)->post(route('admin.email.reset'))->assertRedirect(route('admin.email.edit'));

        $this->assertSame(EmailTemplate::DEFAULTS['heading'], EmailTemplate::all()['heading']);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_preview_renders_the_saved_template_with_a_sample_ticket(): void
    {
        $this->actingAs($this->user(User::ROLE_ADMIN, 'admin@example.test'))
            ->get(route('admin.email.preview'))
            ->assertOk()
            ->assertSee('WTK-PRATINJAU')
            ->assertSee('data:image/png;base64,', false);
    }

    // ------------------------------------------------------------------

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'subject' => 'E-tiket pesanan {nomor_pesanan}',
            'heading' => 'E-tiket WARTIKET',
            'greeting' => 'Halo {nama}, pesananmu sudah dikonfirmasi.',
            'intro' => '',
            'footer' => 'Catatan bawah.',
            'accent_color' => '#6D28D9',
            'qr_scale' => 55,
        ], $overrides);
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

    private function orderWithTicket(): Order
    {
        $category = Category::create(['name' => 'Konser', 'slug' => 'konser', 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Festival', 'slug' => 'festival', 'status' => Product::PUBLISHED]);
        $type = TicketType::create([
            'product_id' => $product->id, 'name' => 'Reguler', 'price' => 100000, 'quota' => 10,
            'min_per_order' => 1, 'max_per_order' => 10, 'status' => TicketType::ACTIVE,
        ]);

        $order = Order::create([
            'order_number' => 'TXR-TEST-0001', 'buyer_name' => 'Ayu', 'buyer_email' => 'ayu@example.test',
            'buyer_phone' => '081234567890', 'subtotal' => 100000, 'admin_fee' => 0, 'total' => 100000,
            'status' => Order::SENT,
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id, 'ticket_type_id' => $type->id, 'product_name_snapshot' => 'Festival',
            'ticket_type_name_snapshot' => 'Reguler', 'unit_price' => 100000, 'quantity' => 1, 'line_total' => 100000,
        ]);
        Ticket::create([
            'order_item_id' => $item->id, 'ticket_code' => 'TXR-TEST-0001-001', 'qr_token' => str_repeat('A', 48),
        ]);

        return $order;
    }
}
