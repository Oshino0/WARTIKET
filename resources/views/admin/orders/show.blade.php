@extends('layouts.admin')
@section('title', 'Pesanan ' . $order->order_number)
@section('content')
  <div class="mx-auto max-w-4xl">
    <a href="{{ route('admin.orders.index') }}" class="text-sm font-semibold text-brand">← Kembali ke pesanan</a>

    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold">{{ $order->order_number }}</h1>
        <p class="mt-1 text-sm text-gray-500">Dibuat {{ $order->created_at->translatedFormat('d F Y, H:i') }}</p>
      </div>
      <x-status-badge :order="$order" />
    </div>

    <section class="mt-6 rounded-2xl border border-violet-100 bg-white p-5">
      <h2 class="font-bold">Data pembeli</h2>
      <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
        <div><dt class="text-gray-500">Nama</dt><dd class="font-medium">{{ $order->buyer_name ?? $order->user?->name }}</dd></div>
        <div><dt class="text-gray-500">Email</dt><dd class="font-medium">{{ $order->buyer_email ?? $order->user?->email }}</dd></div>
        <div><dt class="text-gray-500">WhatsApp</dt><dd class="font-medium">{{ $order->buyer_phone ?? $order->user?->phone ?? '—' }}</dd></div>
      </dl>
    </section>

    <section class="mt-5 overflow-hidden rounded-2xl border border-violet-100 bg-white">
      <h2 class="px-5 pt-5 font-bold">Detail tiket</h2>
      <div class="mt-3 divide-y divide-violet-100">
        @foreach ($order->items as $item)
          <div class="flex flex-wrap justify-between gap-3 px-5 py-4">
            <div>
              <p class="font-semibold">{{ $item->product_name_snapshot }}</p>
              <p class="text-sm text-gray-500">{{ $item->ticket_type_name_snapshot }} × {{ $item->quantity }}</p>
              <p class="mt-1 text-xs text-gray-500">Harga satuan {{ \App\Support\Money::rp($item->unit_price) }}</p>
            </div>
            <p class="font-semibold">{{ \App\Support\Money::rp($item->line_total) }}</p>
          </div>
        @endforeach
      </div>
      <div class="flex justify-between border-t border-violet-100 px-5 py-4 font-bold">
        <span>Total</span>
        <span>{{ \App\Support\Money::rp($order->total) }}</span>
      </div>
    </section>

    @if ($order->status === \App\Models\Order::PENDING_VERIFICATION)
      <div class="mt-5 flex flex-wrap gap-3">
        <form action="{{ route('admin.orders.verify', $order) }}" method="POST"
              onsubmit="return confirm('Konfirmasi pesanan dan kirim e-tiket ke {{ addslashes($order->buyer_email) }}?')">
          @csrf
          <button class="btn btn-primary">Konfirmasi &amp; kirim tiket</button>
        </form>
        <form action="{{ route('admin.orders.reject', $order) }}" method="POST"
              onsubmit="return confirm('Tolak pesanan ini dan kembalikan stok tiket?')">
          @csrf
          <button class="btn btn-danger">Tolak pesanan</button>
        </form>
      </div>
    @elseif ($order->status === \App\Models\Order::SENT)
      <section class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
        <h2 class="font-bold text-emerald-900">Tiket telah dikirim</h2>
        <ul class="mt-3 space-y-2 text-sm text-emerald-900">
          @foreach ($order->items as $item)
            @foreach ($item->tickets as $ticket)
              <li>{{ $ticket->ticket_code }}</li>
            @endforeach
          @endforeach
        </ul>
        @if ($order->buyer_email)
          <form class="mt-4" action="{{ route('admin.orders.resend', $order) }}" method="POST"
                onsubmit="return confirm('Kirim ulang e-tiket ke {{ addslashes($order->buyer_email) }}?')">
            @csrf
            <button class="btn btn-primary">Kirim ulang e-tiket</button>
          </form>
        @endif
      </section>
    @endif
  </div>
@endsection
