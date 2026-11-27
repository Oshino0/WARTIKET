@php $dt = fn ($d) => $d?->format('Y-m-d\TH:i'); @endphp
<x-field label="Nama tipe tiket" name="name" :value="$type->name" placeholder="Reguler, VIP, VVIP…" required />
<div class="grid gap-4 sm:grid-cols-2">
  <x-field label="Harga (Rp)" name="price" type="number" step="1" min="0" :value="$type->exists ? (float) $type->price : null" required />
  <x-field label="Kuota" name="quota" type="number" min="0" :value="$type->quota" hint="Total tiket yang dijual untuk tipe ini." required />
  <x-field label="Minimal per pesanan" name="min_per_order" type="number" min="1" :value="$type->min_per_order" required />
  <x-field label="Maksimal per pesanan" name="max_per_order" type="number" min="1" :value="$type->max_per_order" required />
  <x-field label="Penjualan dibuka" name="sale_start" type="datetime-local" :value="$dt($type->sale_start)" hint="Kosongkan untuk langsung dibuka." />
  <x-field label="Penjualan ditutup" name="sale_end" type="datetime-local" :value="$dt($type->sale_end)" hint="Kosongkan untuk tanpa batas." />
</div>
<div>
  <label for="status" class="mb-1 block text-sm font-medium">Status</label>
  <select id="status" name="status" class="input max-w-xs">
    <option value="active" @selected(old('status', $type->status) === 'active')>Aktif</option>
    <option value="inactive" @selected(old('status', $type->status) === 'inactive')>Nonaktif</option>
  </select>
  @error('status')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>
