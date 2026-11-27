@extends('layouts.admin')
@section('title', $product->exists ? 'Kelola produk' : 'Tambah produk')
@section('content')
  @php
    $keys = old('meta_keys');
    $vals = old('meta_values');
    $rows = $keys !== null
      ? collect($keys)->map(fn ($k, $i) => [$k, $vals[$i] ?? ''])->values()->all()
      : collect($product->metadata ?? [])->map(fn ($v, $k) => [$k, $v])->values()->all();
    $dt = fn ($d) => $d?->format('Y-m-d\TH:i');
  @endphp

  <div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-bold">{{ $product->exists ? $product->name : 'Tambah produk' }}</h1>
    @if ($product->exists)
      <a href="{{ route('admin.products.email.edit', $product) }}" class="btn btn-ghost">
        Email tiket @if (data_get($product->email_template, 'enabled')) <span class="rounded-full bg-brand-surface px-2 py-0.5 text-xs">khusus</span> @endif
      </a>
    @endif
  </div>

  <form action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}"
        method="POST" enctype="multipart/form-data" class="mt-6 max-w-3xl space-y-8 rounded-2xl border border-violet-100 bg-white p-6">
    @csrf
    @if ($product->exists) @method('PUT') @endif

    <section class="space-y-4">
      <h2 class="font-bold">Informasi umum</h2>
      <x-field label="Nama produk" name="name" :value="$product->name" required />

      <div>
        <label for="category_id" class="mb-1 block text-sm font-medium">Kategori</label>
        <select id="category_id" name="category_id" class="input" required>
          <option value="">Pilih kategori</option>
          @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected((int) old('category_id', $product->category_id) === $category->id)>{{ $category->name }}</option>
          @endforeach
        </select>
        @error('category_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        @if ($categories->isEmpty())<p class="mt-1 text-sm text-amber-700">Buat kategori dulu di menu Kategori.</p>@endif
      </div>

      <div>
        <label for="description" class="mb-1 block text-sm font-medium">Deskripsi</label>
        <textarea id="description" name="description" rows="5" class="input">{{ old('description', $product->description) }}</textarea>
        @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
      </div>

      <div>
        @if ($product->image_url)<img src="{{ $product->image_url }}" alt="" class="mb-2 h-24 rounded-xl object-cover">@endif
        <x-field label="Gambar utama" name="image" type="file" accept="image/*" hint="JPG atau PNG, maksimal 2 MB." />
      </div>

      <x-field label="Lokasi atau venue" name="location" :value="$product->location" />
    </section>

    <section class="space-y-4">
      <h2 class="font-bold">Jadwal</h2>
      <div class="grid gap-4 sm:grid-cols-2">
        <x-field label="Mulai" name="start_at" type="datetime-local" :value="$dt($product->start_at)" />
        <x-field label="Selesai" name="end_at" type="datetime-local" :value="$dt($product->end_at)" />
      </div>
    </section>

    <section>
      <h2 class="font-bold">Atribut tambahan</h2>
      <p class="mt-1 text-sm text-gray-500">Isi sesuai jenis produk, misalnya "Nama artis", "Tribun", "Studio", atau "Pembicara". Tampil di halaman detail.</p>
      <div id="meta-rows" class="mt-3 space-y-2">
        @foreach ($rows as [$k, $v])
          <div class="flex gap-2">
            <input name="meta_keys[]" value="{{ $k }}" placeholder="Nama atribut" class="input" aria-label="Nama atribut">
            <input name="meta_values[]" value="{{ $v }}" placeholder="Nilai" class="input" aria-label="Nilai atribut">
            <button type="button" class="btn btn-ghost" data-remove>Hapus</button>
          </div>
        @endforeach
      </div>
      <button type="button" id="add-meta" class="btn btn-ghost mt-3">Tambah atribut</button>
    </section>

    <section class="space-y-4">
      <h2 class="font-bold">Publikasi</h2>
      @if ($product->exists)
        <div>
          <label for="status" class="mb-1 block text-sm font-medium">Status</label>
          <select id="status" name="status" class="input max-w-xs">
            @foreach (\App\Models\Product::STATUSES as $value => $label)
              <option value="{{ $value }}" @selected(old('status', $product->status) === $value)>{{ $label }}</option>
            @endforeach
          </select>
          @error('status')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
      @else
        <p class="text-sm text-gray-500">Produk baru disimpan sebagai draft. Setelah menambahkan tipe tiket, kamu bisa mempublikasikannya.</p>
      @endif
      <label class="flex items-center gap-2 text-sm">
        <input type="hidden" name="is_featured" value="0">
        <input type="checkbox" name="is_featured" value="1" class="rounded border-gray-300 text-brand focus:ring-brand" @checked(old('is_featured', $product->is_featured))>
        Tampilkan di "Tiket populer" pada homepage
      </label>
    </section>

    <div class="flex gap-3">
      <button class="btn btn-primary">{{ $product->exists ? 'Simpan perubahan' : 'Simpan sebagai draft' }}</button>
      <a href="{{ route('admin.products.index') }}" class="btn btn-ghost">Kembali</a>
    </div>
  </form>

  @if ($product->exists)
    <section class="mt-10 max-w-5xl">
      <h2 class="text-lg font-bold">Tipe tiket</h2>

      <div class="mt-3 overflow-x-auto rounded-2xl border border-violet-100 bg-white">
        <table class="w-full min-w-[44rem]">
          <thead class="bg-brand-surface"><tr>
            <th class="th">Tipe</th><th class="th text-right">Harga</th><th class="th text-right">Terjual / dicadangkan / kuota</th>
            <th class="th text-right">Per pesanan</th><th class="th">Periode jual</th><th class="th">Status</th><th class="th"></th>
          </tr></thead>
          <tbody class="divide-y divide-violet-100">
            @forelse ($product->ticketTypes as $type)
              <tr>
                <td class="td font-medium">{{ $type->name }}</td>
                <td class="td text-right">{{ \App\Support\Money::rp($type->price) }}</td>
                <td class="td text-right">{{ $type->sold }} / {{ $type->reserved }} / {{ $type->quota }}</td>
                <td class="td text-right">{{ $type->min_per_order }}–{{ $type->max_per_order }}</td>
                <td class="td text-gray-500">
                  {{ $type->sale_start?->translatedFormat('d M Y H:i') ?? 'Langsung' }} → {{ $type->sale_end?->translatedFormat('d M Y H:i') ?? 'Tanpa batas' }}
                </td>
                <td class="td">{{ $type->status === 'active' ? 'Aktif' : 'Nonaktif' }}</td>
                <td class="td">
                  <div class="flex justify-end gap-2">
                    <a href="{{ route('admin.ticket-types.edit', $type) }}" class="btn btn-ghost !py-1">Ubah</a>
                    <form action="{{ route('admin.ticket-types.destroy', $type) }}" method="POST" onsubmit="return confirm('Hapus tipe tiket {{ addslashes($type->name) }}?')">
                      @csrf @method('DELETE')
                      <button class="btn btn-danger !py-1">Hapus</button>
                    </form>
                  </div>
                </td>
              </tr>
            @empty
              <tr><td class="td text-center text-gray-500" colspan="7">Belum ada tipe tiket. Tambahkan di bawah.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <form action="{{ route('admin.ticket-types.store', $product) }}" method="POST" class="mt-6 max-w-3xl space-y-4 rounded-2xl border border-violet-100 bg-white p-6">
        @csrf
        <h3 class="font-bold">Tambah tipe tiket</h3>
        @include('admin.ticket-types._fields', ['type' => new \App\Models\TicketType(['min_per_order' => 1, 'max_per_order' => 10, 'status' => 'active'])])
        <button class="btn btn-primary">Tambah tipe tiket</button>
      </form>
    </section>
  @endif

  <script>
    (function () {
      var rows = document.getElementById('meta-rows');
      document.getElementById('add-meta').addEventListener('click', function () {
        var d = document.createElement('div');
        d.className = 'flex gap-2';
        d.innerHTML = '<input name="meta_keys[]" placeholder="Nama atribut" class="input" aria-label="Nama atribut">' +
          '<input name="meta_values[]" placeholder="Nilai" class="input" aria-label="Nilai atribut">' +
          '<button type="button" class="btn btn-ghost" data-remove>Hapus</button>';
        rows.appendChild(d);
        d.querySelector('input').focus();
      });
      rows.addEventListener('click', function (e) {
        if (e.target.matches('[data-remove]')) e.target.closest('div').remove();
      });
    })();
  </script>
@endsection
