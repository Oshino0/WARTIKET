@extends('layouts.admin')
@section('title', 'Produk')
@section('content')
  <div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-bold">Produk tiket</h1>
    <a href="{{ route('admin.products.create') }}" class="btn btn-primary">Tambah produk</a>
  </div>

  <form method="GET" class="mt-6 flex flex-wrap gap-3" role="search">
    <label for="q" class="sr-only">Cari produk</label>
    <input id="q" name="q" value="{{ $search }}" placeholder="Cari nama produk" class="input max-w-xs">
    <label for="status" class="sr-only">Status</label>
    <select id="status" name="status" class="input max-w-[14rem]">
      <option value="">Semua status</option>
      @foreach (\App\Models\Product::STATUSES as $value => $label)
        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
      @endforeach
    </select>
    <button class="btn btn-ghost">Filter</button>
  </form>

  <div class="mt-4 overflow-x-auto rounded-2xl border border-violet-100 bg-white">
    <table class="w-full min-w-[40rem]">
      <thead class="bg-brand-surface"><tr><th class="th">Produk</th><th class="th">Kategori</th><th class="th">Jadwal</th><th class="th text-right">Tipe tiket</th><th class="th">Status</th><th class="th"></th></tr></thead>
      <tbody class="divide-y divide-violet-100">
        @forelse ($products as $product)
          <tr>
            <td class="td font-medium">{{ $product->name }}@if ($product->is_featured) <span class="ml-1 rounded bg-brand-surface px-1.5 py-0.5 text-xs text-brand">Populer</span>@endif</td>
            <td class="td">{{ $product->category->name }}</td>
            <td class="td text-gray-500">{{ $product->start_at?->translatedFormat('d M Y, H:i') ?? '–' }}</td>
            <td class="td text-right">{{ $product->ticket_types_count }}</td>
            <td class="td">{{ \App\Models\Product::STATUSES[$product->status] ?? $product->status }}</td>
            <td class="td">
              <div class="flex justify-end gap-2">
                <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-ghost !py-1">Kelola</a>
                <a href="{{ route('admin.products.email.edit', $product) }}" class="btn btn-ghost !py-1">Email</a>
                <form action="{{ route('admin.products.destroy', $product) }}" method="POST" onsubmit="return confirm('Hapus produk {{ addslashes($product->name) }}? Riwayat pesanan tetap tersimpan.')">
                  @csrf @method('DELETE')
                  <button class="btn btn-danger !py-1">Hapus</button>
                </form>
              </div>
            </td>
          </tr>
        @empty
          <tr><td class="td text-center text-gray-500" colspan="6">Belum ada produk.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-4">{{ $products->links() }}</div>
@endsection
