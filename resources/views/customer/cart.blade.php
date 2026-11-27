@extends('layouts.app')
@section('title', 'Keranjang')
@section('content')
  <div class="mx-auto max-w-5xl">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <p class="text-sm font-semibold uppercase tracking-wide text-brand">Pilihan tiketmu</p>
        <h1 class="mt-1 text-3xl font-extrabold tracking-tight">Keranjang</h1>
      </div>
      <a href="{{ route('explore') }}" class="text-sm font-semibold text-brand hover:underline">Lanjut pilih tiket</a>
    </div>

    @if (empty($items))
      <div class="mt-6 rounded-2xl border border-dashed border-violet-200 bg-white px-6 py-14 text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-brand-surface text-brand">
          <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-7 w-7">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l2.2 11.1a2 2 0 0 0 2 1.6h8.9a2 2 0 0 0 2-1.6L22 7H6" />
            <circle cx="10" cy="20" r="1" /><circle cx="18" cy="20" r="1" />
          </svg>
        </div>
        <h2 class="mt-4 text-lg font-bold">Keranjangmu masih kosong</h2>
        <p class="mt-1 text-sm text-gray-600">Pilih tiket dari acara yang ingin kamu datangi.</p>
        <a href="{{ route('explore') }}" class="btn btn-primary mt-5">Jelajahi tiket</a>
      </div>
    @else
      @if (! $canCheckout)
        <div role="alert" class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
          Ada tiket yang jumlahnya perlu disesuaikan atau sudah tidak tersedia. Perbarui atau hapus tiket tersebut sebelum checkout.
        </div>
      @endif

      <div class="mt-6 grid items-start gap-6 lg:grid-cols-[1fr_20rem]">
        <section class="overflow-hidden rounded-2xl border border-violet-100 bg-white" aria-label="Isi keranjang">
          @foreach ($items as $item)
            <article class="border-b border-violet-100 p-5 last:border-b-0">
              <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                  @if ($item['product'])
                    <a href="{{ route('products.show', $item['product']) }}" class="font-bold leading-snug hover:text-brand">
                      {{ $item['product']->name }}
                    </a>
                  @else
                    <p class="font-bold">Acara tidak tersedia</p>
                  @endif
                  <p class="mt-1 text-sm text-gray-600">{{ $item['type']?->name ?? 'Tipe tiket tidak tersedia' }}</p>
                  @if ($item['type'])
                    <p class="mt-1 text-sm font-semibold text-brand">{{ \App\Support\Money::rp($item['type']->price) }} / tiket</p>
                  @endif
                  @if ($item['issue'])
                    <p class="mt-2 text-sm font-medium text-red-700">{{ $item['issue'] }}</p>
                  @endif
                </div>

                @if ($item['type'])
                  <p class="whitespace-nowrap text-right text-sm font-bold">
                    {{ \App\Support\Money::rp((float) $item['type']->price * $item['quantity']) }}
                    <span class="block font-normal text-gray-500">{{ $item['quantity'] }} tiket</span>
                  </p>
                @endif
              </div>

              <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                @if ($item['can_adjust'])
                  <form action="{{ route('cart.update', $item['id']) }}" method="POST" class="flex items-center gap-2">
                    @csrf
                    @method('PATCH')
                    <label for="quantity-{{ $item['id'] }}" class="text-sm text-gray-600">Jumlah</label>
                    <input id="quantity-{{ $item['id'] }}" type="number" name="quantity" value="{{ $item['quantity'] }}"
                      min="{{ $item['type']->min_per_order }}" max="{{ min($item['type']->max_per_order, $item['type']->available()) }}"
                      class="w-20 rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30">
                    <button class="btn btn-ghost !px-3 !py-1.5">Perbarui</button>
                  </form>
                @endif

                <form action="{{ route('cart.destroy', $item['id']) }}" method="POST">
                  @csrf
                  @method('DELETE')
                  <button class="text-sm font-semibold text-red-700 hover:underline">Hapus</button>
                </form>
              </div>
            </article>
          @endforeach
        </section>

        <aside class="rounded-2xl border border-violet-100 bg-white p-5 lg:sticky lg:top-6">
          <h2 class="text-lg font-bold">Ringkasan</h2>
          @if ($quote)
            <div class="mt-4 flex justify-between gap-4 text-sm">
              <span class="text-gray-600">Subtotal tiket</span>
              <span class="font-semibold">{{ \App\Support\Money::rp($quote['subtotal']) }}</span>
            </div>
            @if ($quote['admin_fee'] > 0)
              <div class="mt-2 flex justify-between gap-4 text-sm">
                <span class="text-gray-600">Biaya layanan</span>
                <span class="font-semibold">{{ \App\Support\Money::rp($quote['admin_fee']) }}</span>
              </div>
            @endif
            <div class="mt-4 flex justify-between gap-4 border-t border-violet-100 pt-4 font-bold">
              <span>Total</span>
              <span class="text-brand">{{ \App\Support\Money::rp($quote['total']) }}</span>
            </div>
            <p class="mt-3 text-xs leading-relaxed text-gray-500">Stok belum dicadangkan. Ketersediaan diperiksa kembali saat pesanan dibuat.</p>

            <a href="{{ route('checkout.review') }}" class="btn btn-primary mt-5 w-full">Lanjutkan tanpa akun</a>
            <p class="mt-2 text-center text-xs text-gray-500">Isi data pembeli untuk menyelesaikan pesanan.</p>
          @else
            <p class="mt-3 text-sm leading-relaxed text-gray-600">Total belum tersedia karena ada tiket yang tidak tersedia atau jumlahnya tidak valid.</p>
          @endif
        </aside>
      </div>
    @endif
  </div>
@endsection
