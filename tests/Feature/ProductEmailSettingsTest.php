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

class ProductEmailSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_event_can_have_its_own_email_while_others_keep_the_global_one(): void
    {
        Storage::fake('public');
        $concert = $this->ticketType('Konser Senja');
        $seminar = $this->ticketType('Seminar Laravel');

        $this->actingAs($this->admin())
            ->put(route('admin.products.email.update', $concert->product), $this->payload([
                'enabled' => '1',
                'subject' => 'Tiket konser {nomor_pesanan}',
                'heading' => 'Sampai jumpa di konser, {nama}!',
                'accent_color' => '#E11D48',
            ]))
            ->assertRedirect(route('admin.products.email.edit', $concert->product))
            ->assertSessionHasNoErrors();

        $concertOrder = $this->orderFor([$concert]);
        $concertMail = new GuestOrderTickets($concertOrder);
        $this->assertSame('Tiket konser '.$concertOrder->order_number, $concertMail->envelope()->subject);
        $this->assertStringContainsString('Sampai jumpa di konser, Ayu!', $concertMail->render());
        $this->assertStringContainsString('#E11D48', $concertMail->render());

        $seminarOrder = $this->orderFor([$seminar], 'TXR-TEST-0002');
        $seminarMail = new GuestOrderTickets($seminarOrder);
        $this->assertSame('E-tiket pesanan TXR-TEST-0002', $seminarMail->envelope()->subject);
        $this->assertStringContainsString(EmailTemplate::DEFAULTS['heading'], $seminarMail->render());
        $this->assertStringNotContainsString('#E11D48', $seminarMail->render());
    }

    public function test_event_settings_are_ignored_until_enabled(): void
    {
        $concert = $this->ticketType('Konser Senja');

        $this->actingAs($this->admin())
            ->put(route('admin.products.email.update', $concert->product), $this->payload([
                'enabled' => '0',
                'heading' => 'Judul khusus konser',
            ]))
            ->assertSessionHasNoErrors();

        $html = (new GuestOrderTickets($this->orderFor([$concert])))->render();

        $this->assertStringNotContainsString('Judul khusus konser', $html);
        $this->assertSame('Judul khusus konser', $concert->product->fresh()->email_template['heading'], 'saved for later');
    }

    public function test_each_ticket_in_an_order_follows_its_own_events_frame_colour_and_qr_background(): void
    {
        Storage::fake('public');
        $concert = $this->ticketType('Konser Senja');
        $seminar = $this->ticketType('Seminar Laravel');
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.products.email.update', $concert->product), $this->payload([
            'enabled' => '1',
            'accent_color' => '#E11D48',
            'qr_background_mode' => 'custom',
            'qr_background' => UploadedFile::fake()->image('konser.png', 800, 800),
        ]))->assertSessionHasNoErrors();

        $html = (new GuestOrderTickets($this->orderFor([$concert, $seminar])))->render();

        $this->assertStringContainsString('border: 1px solid #E11D48', $html);
        $this->assertStringContainsString('border: 1px solid #6D28D9', $html);

        preg_match_all('/data:image\/png;base64,([A-Za-z0-9+\/=\s]+?)"/', $html, $matches);
        $this->assertCount(2, $matches[1]);
        $sizes = array_map(fn ($b64) => getimagesizefromstring(base64_decode(preg_replace('/\s+/', '', $b64)))[0], $matches[1]);
        $this->assertContains(640, $sizes, 'the concert ticket sits on its background image');
        $this->assertNotSame([640, 640], $sizes, 'the seminar ticket stays a plain QR');
    }

    public function test_background_modes_global_custom_and_none(): void
    {
        Storage::fake('public');
        $concert = $this->ticketType('Konser Senja');
        $admin = $this->admin();

        // global image set in the general settings
        $this->actingAs($admin)->put(route('admin.email.update'), $this->payload([
            'qr_background' => UploadedFile::fake()->image('umum.png', 800, 800),
        ]))->assertSessionHasNoErrors();
        $globalPath = EmailTemplate::all()['qr_background'];

        $save = fn (array $extra) => $this->actingAs($admin)
            ->put(route('admin.products.email.update', $concert->product), $this->payload(['enabled' => '1'] + $extra));

        $save(['qr_background_mode' => 'global'])->assertSessionHasNoErrors();
        $this->assertSame($globalPath, EmailTemplate::forProduct($concert->product->fresh())['qr_background']);

        $save(['qr_background_mode' => 'none'])->assertSessionHasNoErrors();
        $this->assertNull(EmailTemplate::forProduct($concert->product->fresh())['qr_background']);
        $this->assertNotSame(640, getimagesizefromstring(
            TicketQrImage::png('TOKEN-1', EmailTemplate::forProduct($concert->product->fresh()))
        )[0]);

        $save(['qr_background_mode' => 'custom', 'qr_background' => UploadedFile::fake()->image('khusus.png', 700, 700)])
            ->assertSessionHasNoErrors();
        $customPath = $concert->product->fresh()->email_template['qr_background'];
        $this->assertNotNull($customPath);
        $this->assertNotSame($globalPath, $customPath);
        Storage::disk('public')->assertExists($customPath);

        // switching away from "custom" cleans up the file
        $save(['qr_background_mode' => 'none'])->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($customPath);
    }

    public function test_choosing_custom_background_without_an_image_is_an_error(): void
    {
        $concert = $this->ticketType('Konser Senja');

        $this->actingAs($this->admin())
            ->put(route('admin.products.email.update', $concert->product), $this->payload([
                'enabled' => '1',
                'qr_background_mode' => 'custom',
            ]))
            ->assertSessionHasErrors('qr_background');
    }

    public function test_removing_event_settings_returns_to_global_and_deletes_the_image(): void
    {
        Storage::fake('public');
        $concert = $this->ticketType('Konser Senja');
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.products.email.update', $concert->product), $this->payload([
            'enabled' => '1',
            'heading' => 'Judul khusus konser',
            'qr_background_mode' => 'custom',
            'qr_background' => UploadedFile::fake()->image('khusus.png', 700, 700),
        ]));
        $path = $concert->product->fresh()->email_template['qr_background'];

        $this->actingAs($admin)->post(route('admin.products.email.reset', $concert->product))
            ->assertRedirect(route('admin.products.email.edit', $concert->product));

        $this->assertNull($concert->product->fresh()->email_template);
        Storage::disk('public')->assertMissing($path);
        $this->assertStringNotContainsString('Judul khusus konser', (new GuestOrderTickets($this->orderFor([$concert])))->render());
    }

    public function test_only_admins_can_manage_event_email_and_pages_render(): void
    {
        $concert = $this->ticketType('Konser Senja');
        $customer = User::create(['name' => 'C', 'email' => 'c@example.test', 'password' => 'password12', 'role' => 'customer', 'is_active' => true]);

        $this->actingAs($customer)->get(route('admin.products.email.edit', $concert->product))->assertForbidden();

        $this->actingAs($this->admin());
        $this->get(route('admin.products.email.edit', $concert->product))->assertOk()->assertSee('Konser Senja');
        $this->get(route('admin.products.email.preview', $concert->product))
            ->assertOk()
            ->assertSee('Konser Senja')
            ->assertSee('data:image/png;base64,', false);
        $this->get(route('admin.products.edit', $concert->product))->assertOk()->assertSee('Email tiket');
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
            'qr_background_mode' => 'global',
        ], $overrides);
    }

    private function admin(): User
    {
        return User::firstOrCreate(
            ['email' => 'admin@example.test'],
            ['name' => 'Admin', 'password' => 'password12', 'role' => User::ROLE_ADMIN, 'is_active' => true],
        );
    }

    private function ticketType(string $event): TicketType
    {
        $category = Category::firstOrCreate(['slug' => 'umum'], ['name' => 'Umum', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => $event,
            'slug' => str($event)->slug()->toString(),
            'status' => Product::PUBLISHED,
        ]);

        return TicketType::create([
            'product_id' => $product->id, 'name' => 'Reguler', 'price' => 100000, 'quota' => 10,
            'min_per_order' => 1, 'max_per_order' => 10, 'status' => TicketType::ACTIVE,
        ]);
    }

    /** @param  TicketType[]  $types  one ticket is created per type */
    private function orderFor(array $types, string $number = 'TXR-TEST-0001'): Order
    {
        $order = Order::create([
            'order_number' => $number, 'buyer_name' => 'Ayu', 'buyer_email' => 'ayu@example.test',
            'buyer_phone' => '081234567890', 'subtotal' => 100000 * count($types), 'admin_fee' => 0,
            'total' => 100000 * count($types), 'status' => Order::SENT,
        ]);

        foreach ($types as $index => $type) {
            $item = OrderItem::create([
                'order_id' => $order->id, 'ticket_type_id' => $type->id,
                'product_name_snapshot' => $type->product->name, 'ticket_type_name_snapshot' => $type->name,
                'unit_price' => 100000, 'quantity' => 1, 'line_total' => 100000,
            ]);
            Ticket::create([
                'order_item_id' => $item->id,
                'ticket_code' => $number.'-'.($index + 1),
                'qr_token' => str_pad($number.$index, 48, 'A'),
            ]);
        }

        return $order;
    }
}
