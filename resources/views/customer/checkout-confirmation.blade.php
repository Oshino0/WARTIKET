@extends('layouts.app')
@section('title', 'Pesanan diterima')
@section('content')
  <div class="mx-auto max-w-2xl">
    <div class="rounded-2xl border border-violet-100 bg-white p-6 sm:p-8">
      <p class="text-sm font-semibold uppercase tracking-wide text-brand">Pesanan diterima</p>
      <h1 class="mt-2 text-2xl font-extrabold">
        @if ($order->status === \App\Models\Order::SENT) E-tiket sudah dikirim
        @elseif ($order->status === \App\Models\Order::REJECTED) Pesanan tidak dikonfirmasi
        @else Menunggu verifikasi admin
        @endif
      </h1>
      @if ($order->status === \App\Models\Order::SENT)
        <p class="mt-3 text-gray-600">
          Pesananmu sudah dikonfirmasi. E-tiket telah dikirim ke <strong>{{ $order->buyer_email }}</strong>.
        </p>
      @elseif ($order->status === \App\Models\Order::REJECTED)
        <p class="mt-3 text-gray-600">Pesanan ini tidak dapat dikonfirmasi. Hubungi panitia acara jika membutuhkan bantuan.</p>
      @else
        <p class="mt-3 text-gray-600">
          Kami akan mengirim e-tiket ke <strong>{{ $order->buyer_email }}</strong> setelah pesanan dikonfirmasi.
        </p>
      @endif
      <p class="mt-2 text-sm text-gray-500">Simpan nomor pesanan ini untuk referensi.</p>
      <p class="mt-4 rounded-xl bg-brand-surface p-4 text-lg font-bold text-brand">{{ $order->order_number }}</p>

      <div class="mt-6 divide-y divide-violet-100 rounded-xl border border-violet-100">
        @foreach ($order->items as $item)
          <div class="flex justify-between gap-4 p-4 text-sm">
            <div>
              <p class="font-semibold">{{ $item->product_name_snapshot }}</p>
              <p class="text-gray-500">{{ $item->ticket_type_name_snapshot }} × {{ $item->quantity }}</p>
            </div>
            <p class="whitespace-nowrap font-semibold">{{ \App\Support\Money::rp($item->line_total) }}</p>
          </div>
        @endforeach
        <div class="flex justify-between gap-4 p-4 font-bold">
          <span>Total</span>
          <span>{{ \App\Support\Money::rp($order->total) }}</span>
        </div>
      </div>

      <a href="{{ route('explore') }}" class="btn btn-primary mt-6">Kembali jelajahi acara</a>
    </div>
  </div>
@endsection
