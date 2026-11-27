@extends('layouts.app')
@section('title', $product->name)
@section('content')
  @php $user = auth()->user(); @endphp
  <div class="grid items-start gap-6 lg:grid-cols-[1fr_24rem]">
    <article>
      <x-cover :item="$product" class="rounded-2xl" ratio="aspect-[16/9]" />

      <p class="mt-5 text-sm font-medium text-brand">
        <a href="{{ route('explore', ['category' => $product->category->slug]) }}">{{ $product->category->name }}</a>
      </p>
      <h1 class="mt-1 text-3xl font-extrabold tracking-tight">{{ $product->name }}</h1>

      <dl class="mt-4 grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
        <div><dt class="text-gray-500">Jadwal</dt>
          <dd class="font-medium">
            @if ($product->start_at)
              {{ $product->start_at->translatedFormat('l, d F Y • H:i') }}
              @if ($product->end_at) – {{ $product->end_at->translatedFormat('H:i') }} @endif
            @else Jadwal menyusul @endif
          </dd></div>
        @if ($product->location)
          <div><dt class="text-gray-500">Lokasi</dt><dd class="font-medium">{{ $product->location }}</dd></div>
        @endif
      </dl>

    </article>

    <aside class="lg:sticky lg:top-6 lg:self-start">
      <div class="rounded-2xl border border-violet-100 p-5">
        <h2 class="text-lg font-bold">Pilih tiket</h2>

        @if ($product->status !== \App\Models\Product::PUBLISHED)
          <p class="mt-3 rounded-xl bg-gray-100 px-4 py-3 text-sm text-gray-600">Penjualan tiket untuk acara ini sudah ditutup.</p>
        @elseif ($product->ticketTypes->isEmpty())
          <p class="mt-3 text-sm text-gray-500">Belum ada tipe tiket yang dijual.</p>
        @else
          <form action="{{ route('checkout.review') }}" method="GET" id="ticket-form" class="mt-4">
            <ul class="divide-y divide-violet-100">
              @foreach ($product->ticketTypes as $type)
                @php
                  $left = $type->available();
                  $notOpen = $type->sale_start && $type->sale_start->isFuture();
                  $ended = $type->sale_end && $type->sale_end->isPast();
                  $disabled = $notOpen || $ended || $left === 0;
                  $max = min($type->max_per_order, $left);
                @endphp
                <li class="py-4 first:pt-0">
                  <div class="flex items-start justify-between gap-3">
                    <div>
                      <p class="font-semibold">{{ $type->name }}</p>
                      <p class="text-sm font-bold text-brand">{{ \App\Support\Money::rp($type->price) }}</p>
                      <p class="mt-1 text-xs text-gray-500">
                        @if ($notOpen) Dibuka {{ $type->sale_start->translatedFormat('d M Y, H:i') }}
                        @elseif ($ended) Penjualan berakhir
                        @elseif ($left === 0) Habis
                        @else
                          Sisa {{ $left }}
                          @if ($type->min_per_order > 1) • minimal {{ $type->min_per_order }} @endif
                          • maks. {{ $type->max_per_order }}/pesanan
                        @endif
                      </p>
                    </div>

                    @if (! $disabled)
                      <div class="flex shrink-0 items-center gap-1" data-stepper data-price="{{ (float) $type->price }}" data-min="{{ $type->min_per_order }}">
                        <button type="button" class="btn btn-ghost !px-3 !py-1" data-dec aria-label="Kurangi {{ $type->name }}">−</button>
                        <input type="number" name="items[{{ $type->id }}]" value="0" min="0" max="{{ $max }}"
                               class="w-12 rounded-lg border border-gray-300 py-1 text-center text-sm" aria-label="Jumlah {{ $type->name }}">
                        <button type="button" class="btn btn-ghost !px-3 !py-1" data-inc aria-label="Tambah {{ $type->name }}">+</button>
                      </div>
                    @endif
                  </div>
                </li>
              @endforeach
            </ul>

            <div class="mt-2 flex items-center justify-between border-t border-violet-100 pt-4 text-sm">
              <span class="text-gray-500">Perkiraan subtotal</span>
              <span id="subtotal" class="text-lg font-bold">Rp 0</span>
            </div>

            @if (! $user || $user->isCustomer())
              <button id="checkout-btn" class="btn btn-primary mt-4 w-full" disabled>Checkout</button>
              <p id="selection-hint" class="mt-2 text-center text-xs text-gray-500">Pilih jumlah tiket yang ingin dibeli.</p>
            @else
              <p class="mt-4 rounded-xl bg-gray-100 px-4 py-3 text-sm text-gray-600">Akun admin dan petugas tidak dapat membeli tiket.</p>
            @endif
          </form>
        @endif
      </div>
    </aside>

    @if ($product->description || ! empty($product->metadata))
      <section class="space-y-6 lg:col-span-2" aria-label="Detail acara">
        @if ($product->description)
          <div>
            <h2 class="text-lg font-bold">Tentang acara</h2>
            <p class="mt-2 max-w-prose whitespace-pre-line leading-relaxed text-gray-700">{{ $product->description }}</p>
          </div>
        @endif

        @if (! empty($product->metadata))
          <div>
            <h2 class="text-lg font-bold">Informasi tambahan</h2>
            <dl class="mt-2 max-w-prose divide-y divide-violet-100 rounded-2xl border border-violet-100 text-sm">
              @foreach ($product->metadata as $key => $value)
                <div class="flex justify-between gap-4 px-4 py-3"><dt class="text-gray-500">{{ $key }}</dt><dd class="text-right font-medium">{{ $value }}</dd></div>
              @endforeach
            </dl>
          </div>
        @endif
      </section>
    @endif
  </div>

  <script>
    (function () {
      var form = document.getElementById('ticket-form');
      if (!form) return;
      var fmt = new Intl.NumberFormat('id-ID');
      var subtotal = document.getElementById('subtotal');
      var checkoutBtn = document.getElementById('checkout-btn');
      var hint = document.getElementById('selection-hint');

      function refresh() {
        var sum = 0, count = 0, valid = true;
        form.querySelectorAll('[data-stepper]').forEach(function (s) {
          var qty = parseInt(s.querySelector('input').value, 10) || 0;
          var min = parseInt(s.dataset.min, 10) || 1;
          sum += qty * parseFloat(s.dataset.price);
          count += qty;
          if (qty > 0 && qty < min) valid = false;
        });
        subtotal.textContent = 'Rp ' + fmt.format(sum);
        if (checkoutBtn) checkoutBtn.disabled = count === 0 || !valid;
        if (hint) hint.textContent = !valid
          ? 'Jumlah tiket belum memenuhi minimum pembelian.'
          : count === 0
            ? 'Pilih jumlah tiket yang ingin dibeli.'
            : count + ' tiket dipilih. Stok dicadangkan saat pesanan dibuat.';
      }

      form.querySelectorAll('[data-stepper]').forEach(function (s) {
        var input = s.querySelector('input');
        var max = parseInt(input.max, 10);
        s.querySelector('[data-inc]').addEventListener('click', function () {
          input.value = Math.min(max, (parseInt(input.value, 10) || 0) + 1); refresh();
        });
        s.querySelector('[data-dec]').addEventListener('click', function () {
          input.value = Math.max(0, (parseInt(input.value, 10) || 0) - 1); refresh();
        });
        input.addEventListener('input', refresh);
      });
      // Keep the checkout URL short: only send ticket types that have a quantity.
      form.addEventListener('submit', function () {
        form.querySelectorAll('[data-stepper] input').forEach(function (input) {
          if ((parseInt(input.value, 10) || 0) === 0) input.disabled = true;
        });
      });
      refresh();
    })();
  </script>
@endsection
