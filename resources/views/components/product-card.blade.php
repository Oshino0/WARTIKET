@props(['product'])
@php
  $active = $product->ticketTypes->where('status', 'active');
  $soldOut = $active->isNotEmpty() && $active->sum(fn ($t) => $t->available()) === 0;
  $closed = $product->status !== \App\Models\Product::PUBLISHED;
@endphp
<a href="{{ route('products.show', $product) }}"
   class="group block overflow-hidden rounded-2xl border border-violet-100 bg-white transition hover:border-brand-accent focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">
  <x-cover :item="$product" />
  <div class="perf"></div>
  <div class="p-4 pt-3">
    <p class="text-xs font-medium text-brand">{{ $product->category->name }}</p>
    <h3 class="mt-1 line-clamp-2 font-semibold leading-snug">{{ $product->name }}</h3>
    <p class="mt-2 text-sm text-gray-500">
      {{ $product->start_at?->translatedFormat('d M Y') ?? 'Jadwal menyusul' }}
      @if ($product->location) • {{ $product->location }} @endif
    </p>
    <p class="mt-3 text-sm">
      @if ($closed)
        <span class="font-semibold text-gray-500">Penjualan ditutup</span>
      @elseif ($soldOut)
        <span class="font-semibold text-red-700">Habis</span>
      @elseif ($product->starting_price !== null)
        <span class="text-gray-500">Mulai</span> <span class="font-bold">{{ \App\Support\Money::rp($product->starting_price) }}</span>
      @endif
    </p>
  </div>
</a>
