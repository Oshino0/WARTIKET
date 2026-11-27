<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Support\Money;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

/**
 * Ticket e-mail wording and look.
 *
 * - Global template: one JSON value in the `settings` table (key: email_template).
 * - Per-event template: optional JSON on the product (products.email_template). When enabled it replaces
 *   the global one for that event's tickets. Nothing set = the global template is used.
 *
 * Blank fields saved by the admin stay blank (they do not fall back to a default).
 */
class EmailTemplate
{
    public const KEY = 'email_template';

    /** Fields an event can override as text/colour/size. */
    public const TEXT_KEYS = ['subject', 'heading', 'greeting', 'intro', 'footer', 'accent_color', 'qr_scale'];

    public const DEFAULTS = [
        'subject' => 'E-tiket pesanan {nomor_pesanan}',
        'heading' => 'E-tiket WARTIKET',
        'greeting' => 'Halo {nama}, pesananmu sudah dikonfirmasi.',
        'intro' => '',
        'footer' => 'Tunjukkan QR code tiket saat memasuki acara. Jika gambar tidak tampil, tunjukkan kode tiket di atas kepada petugas.',
        'accent_color' => '#6D28D9',
        'qr_background' => null, // path on the public disk
        'qr_scale' => 55,        // QR size as % of the background image
    ];

    public const PLACEHOLDERS = [
        '{nama}' => 'Nama pembeli',
        '{nomor_pesanan}' => 'Nomor pesanan',
        '{total}' => 'Total pembayaran',
        '{email}' => 'Email pembeli',
    ];

    /** The global template. */
    public static function all(): array
    {
        $saved = json_decode((string) Setting::get(self::KEY, '{}'), true);

        return self::normalise(array_merge(self::DEFAULTS, is_array($saved) ? $saved : []));
    }

    /**
     * The template that applies to one event's tickets: its own when enabled, otherwise the global one.
     * The QR background follows the event's choice: the global image, its own image, or none.
     */
    public static function forProduct(?Product $product): array
    {
        $global = self::all();
        $own = $product?->email_template;

        if (! is_array($own) || empty($own['enabled'])) {
            return $global;
        }

        $merged = array_merge($global, Arr::only($own, self::TEXT_KEYS));
        $merged['qr_background'] = match ($own['qr_background_mode'] ?? 'global') {
            'custom' => $own['qr_background'] ?? null,
            'none' => null,
            default => $global['qr_background'],
        };

        return self::normalise($merged);
    }

    public static function save(array $values): void
    {
        Setting::put(self::KEY, json_encode($values));
    }

    /** Back to the built-in wording and look; also removes the uploaded background. */
    public static function reset(): void
    {
        $current = self::all();

        if ($current['qr_background']) {
            Storage::disk('public')->delete($current['qr_background']);
        }

        Setting::where('key', self::KEY)->delete();
    }

    /** Validation rules shared by the global and per-event forms. */
    public static function textRules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:150'],
            'heading' => ['required', 'string', 'max:100'],
            'greeting' => ['required', 'string', 'max:200'],
            'intro' => ['nullable', 'string', 'max:1000'],
            'footer' => ['nullable', 'string', 'max:1000'],
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'qr_scale' => ['required', 'integer', 'min:40', 'max:70'],
            'qr_background' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072', 'dimensions:min_width=300,min_height=300'],
        ];
    }

    /** Clean the validated text/colour/size fields for storage. */
    public static function textValues(array $data): array
    {
        return [
            'subject' => trim(str_replace(["\r", "\n"], ' ', $data['subject'])),
            'heading' => $data['heading'],
            'greeting' => $data['greeting'],
            'intro' => $data['intro'] ?? '',   // blank is a valid choice
            'footer' => $data['footer'] ?? '',
            'accent_color' => strtoupper($data['accent_color']),
            'qr_scale' => (int) $data['qr_scale'],
        ];
    }

    /** Replace {nama}, {nomor_pesanan}, {total}, {email}. Output is plain text: escape it when printing. */
    public static function fill(string $text, Order $order): string
    {
        return strtr($text, [
            '{nama}' => (string) $order->buyer_name,
            '{nomor_pesanan}' => (string) $order->order_number,
            '{total}' => Money::rp($order->total),
            '{email}' => (string) $order->buyer_email,
        ]);
    }

    /** Never let a bad stored value reach inline CSS or the image code. */
    private static function normalise(array $values): array
    {
        if (! preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $values['accent_color'])) {
            $values['accent_color'] = self::DEFAULTS['accent_color'];
        }
        $values['qr_scale'] = max(40, min(70, (int) $values['qr_scale']));

        return $values;
    }
}
