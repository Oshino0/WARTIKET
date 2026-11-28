<?php

namespace Tests\Feature;

use App\Services\EmailTemplate;
use App\Services\TicketImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketImageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('gd') || ! function_exists('imagettftext')) {
            $this->markTestSkipped('PHP GD with FreeType is required to draw tickets.');
        }
    }

    public function test_the_whole_ticket_is_drawn_as_one_png_with_rounded_transparent_corners(): void
    {
        $png = TicketImage::compose($this->details(), EmailTemplate::DEFAULTS);

        $this->assertSame("\x89PNG\r\n\x1a\n", substr($png, 0, 8));
        [$width, $height] = getimagesizefromstring($png);
        $this->assertSame(1320, $width);
        $this->assertSame(560, $height);

        $image = imagecreatefromstring($png);
        $alphaAt = fn (int $x, int $y) => (imagecolorat($image, $x, $y) >> 24) & 0x7F;

        $this->assertSame(127, $alphaAt(0, 0), 'rounded corner is transparent');
        $this->assertSame(127, $alphaAt(840, 4), 'notch on the perforation is transparent');
        $this->assertSame(0, $alphaAt(20, 280), 'ticket body is solid');
    }

    public function test_a_background_image_changes_the_ticket(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('email/latar.jpg', file_get_contents(UploadedFile::fake()->image('latar.jpg', 1000, 700)->getRealPath()));

        $plain = TicketImage::compose($this->details(), EmailTemplate::DEFAULTS);
        $withBackground = TicketImage::compose($this->details(), ['qr_background' => 'email/latar.jpg'] + EmailTemplate::DEFAULTS);

        $this->assertSame(1320, getimagesizefromstring($withBackground)[0]);
        $this->assertNotSame($plain, $withBackground);
    }

    public function test_very_long_texts_still_produce_a_ticket(): void
    {
        $png = TicketImage::compose($this->details([
            'event' => 'Festival Musik Akbar Nusantara Raya Purwokerto Tahun Ini Edisi Spesial Dua Ribu Dua Puluh Enam',
            'type' => 'Tribun VVIP Presiden Dengan Akses Belakang Panggung',
            'where' => 'Gedung Olahraga Satria, Jl. Jenderal Sudirman No. 1, Purwokerto, Jawa Tengah, Indonesia',
            'buyer' => 'Muhammad Abdurrahman Al-Fatih bin Abdullah',
        ]), EmailTemplate::DEFAULTS);

        $this->assertSame([1320, 560], array_slice(getimagesizefromstring($png), 0, 2));
    }

    public function test_every_qr_size_setting_works(): void
    {
        foreach ([40, 55, 70] as $scale) {
            $png = TicketImage::compose($this->details(), ['qr_scale' => $scale] + EmailTemplate::DEFAULTS);
            $this->assertSame(1320, getimagesizefromstring($png)[0]);
        }
    }

    private function details(array $overrides = []): array
    {
        return array_merge([
            'brand' => 'JualTiket',
            'event' => 'Senja di Alun-Alun Fest',
            'type' => 'VIP',
            'when' => '28 November 2026 | 19:00',
            'where' => 'Alun-Alun Purwokerto',
            'buyer' => 'Ayu Pembeli',
            'order' => 'TXR-261128-0CCFVL',
            'code' => 'TXR-261128-0CCFVL-001',
            'qr_token' => str_repeat('A', 48),
        ], $overrides);
    }
}
