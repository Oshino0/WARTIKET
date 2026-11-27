<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\TicketType;
use Illuminate\Database\Seeder;

/** Sample catalog (local environment only) showing one schema serving very different products. */
class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $cats = collect([
            'Konser' => 'Pertunjukan musik dan festival.',
            'Olahraga' => 'Pertandingan dan turnamen.',
            'Bioskop' => 'Tiket nonton dan screening spesial.',
            'Makanan' => 'Voucher makan dan paket kuliner.',
            'Seminar' => 'Workshop, talkshow, dan konferensi.',
            'Wisata' => 'Tiket masuk dan paket kunjungan.',
        ])->map(fn ($desc, $name) => Category::firstOrCreate(
            ['name' => $name],
            ['slug' => Category::uniqueSlug($name), 'description' => $desc, 'is_active' => true]
        ));

        $this->product($cats['Konser'], 'Senja di Alun-Alun Fest', 'Alun-Alun Purwokerto', now()->addDays(40), true,
            ['Nama artis' => 'Line-up akan diumumkan', 'Gerbang dibuka' => '15.00 WIB'],
            [['Reguler', 150000, 500, 6], ['VIP', 350000, 100, 4], ['VVIP', 750000, 30, 2]]);

        $this->product($cats['Olahraga'], 'Derby Banyumas Cup', 'Stadion Satria', now()->addDays(21), true,
            ['Tim' => 'Tuan rumah vs tamu', 'Tribun' => 'Utara, Selatan, VIP Barat'],
            [['Tribun Ekonomi', 50000, 800, 8], ['Tribun VIP', 200000, 120, 4]]);

        $this->product($cats['Bioskop'], 'Malam Film Pendek Nusantara', 'Studio 2, Cinema Hall', now()->addDays(10), false,
            ['Studio' => 'Studio 2', 'Durasi' => '120 menit'],
            [['Reguler', 45000, 90, 6]]);

        $this->product($cats['Makanan'], 'Voucher Makan Malam Soto Sokaraja', 'Seluruh cabang', now()->addDays(60), false,
            ['Varian' => 'Paket berdua', 'Waktu pengambilan' => '17.00–21.00'],
            [['Paket Berdua', 85000, 200, 4]]);

        $this->product($cats['Seminar'], 'Workshop Laravel untuk Pemula', 'Aula Kampus Teknik', now()->addDays(14), true,
            ['Pembicara' => 'Praktisi industri', 'Sertifikat' => 'Ya'],
            [['Peserta', 100000, 60, 3], ['Peserta + Sertifikat', 150000, 40, 3]]);

        $this->product($cats['Wisata'], 'Tiket Masuk Telaga Kembar', 'Baturraden', now()->addDays(90), false,
            ['Area' => 'Wisata alam', 'Paket' => 'Weekend'],
            [['Dewasa', 25000, 1000, 10], ['Anak-anak', 15000, 500, 10]]);
    }

    private function product(Category $cat, string $name, string $location, $startAt, bool $featured, array $meta, array $types): void
    {
        if (Product::where('name', $name)->exists()) {
            return;
        }

        $product = Product::create([
            'category_id' => $cat->id,
            'name' => $name,
            'slug' => Product::uniqueSlug($name),
            'description' => "Contoh produk untuk kategori {$cat->name}. Ganti dengan deskripsi asli dari dashboard admin.",
            'location' => $location,
            'metadata' => $meta,
            'start_at' => $startAt,
            'end_at' => $startAt->copy()->addHours(6),
            'status' => Product::PUBLISHED,
            'is_featured' => $featured,
        ]);

        foreach ($types as [$typeName, $price, $quota, $max]) {
            TicketType::create([
                'product_id' => $product->id,
                'name' => $typeName,
                'price' => $price,
                'quota' => $quota,
                'min_per_order' => 1,
                'max_per_order' => $max,
                'sale_start' => now()->subDay(),
                'sale_end' => $startAt,
                'status' => TicketType::ACTIVE,
            ]);
        }
    }
}
