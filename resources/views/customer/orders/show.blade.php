@extends('layouts.app')
@section('title', 'Pesanan ' . $order->order_number)
@section('content')
  @php
    $checking = request()->boolean('checking') && $order->isPending();
    $paidPayment = $order->payments->firstWhere('status', 'paid');
  @endphp

  <div class="mx-auto max-w-2xl">
    <a href="{{ route('orders.index') }}" class="text-sm font-semibold text-brand">← Semua pesanan</a>

    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-bold">{{ $order->order_number }}</h1>
      <x-status-badge :order="$order" />
    </div>
    <p class="mt-1 text-sm text-gray-500">Dibuat {{ $order->created_at->translatedFormat('d F Y, H:i') }}</p>

    @if ($order->status === 'paid')
      <div class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
        Pembayaran diterima. @if ($paidPayment?->payment_method) Metode: <strong>{{ $paidPayment->payment_method }}</strong>. @endif
        E-ticket akan muncul di halaman ini.
      </div>
    @elseif ($checking)
      <div role="status" class="mt-5 rounded-2xl border border-violet-200 bg-brand-surface p-4 text-sm text-brand">
        Kami sedang memastikan pembayaranmu ke iPaymu. Halaman ini diperbarui otomatis, biasanya hanya beberapa detik.
      </div>
      <script>setTimeout(function () { location.reload(); }, 8000);</script>
    @elseif ($order->isPending())
      <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        Tiketmu dicadangkan sampai <strong>{{ $order->expires_at->translatedFormat('d F Y, H:i') }}</strong>.
        Setelah itu pesanan dibatalkan otomatis dan tiket dikembalikan ke penjualan.
      </div>
    @endif

    <div class="mt-6 overflow-hidden rounded-2xl border border-violet-100">
      <table class="w-full">
        <thead class="bg-brand-surface"><tr><th class="th">Tiket</th><th class="th text-right">Jumlah</th><th class="th text-right">Subtotal</th></tr></thead>
        <tbody class="divide-y divide-violet-100">
          @foreach ($order->items as $item)
            <tr>
              <td class="td"><p class="font-semibold">{{ $item->product_name_snapshot }}</p>
                <p class="text-gray-500">{{ $item->ticket_type_name_snapshot }} • {{ \App\Support\Money::rp($item->unit_price) }}</p></td>
              <td class="td text-right">{{ $item->quantity }}</td>
              <td class="td text-right">{{ \App\Support\Money::rp($item->line_total) }}</td>
            </tr>
          @endforeach
        </tbody>
        <tfoot class="border-t border-violet-100">
          <tr><td class="td text-gray-500" colspan="2">Subtotal</td><td class="td text-right">{{ \App\Support\Money::rp($order->subtotal) }}</td></tr>
          @if ($order->admin_fee > 0)
            <tr><td class="td text-gray-500" colspan="2">Biaya layanan</td><td class="td text-right">{{ \App\Support\Money::rp($order->admin_fee) }}</td></tr>
          @endif
          <tr><td class="td text-base font-bold" colspan="2">Total</td><td class="td text-right text-base font-bold text-brand">{{ \App\Support\Money::rp($order->total) }}</td></tr>
        </tfoot>
      </table>
    </div>

    @if ($order->isPending())
      <div class="mt-6 flex flex-wrap gap-3">
        <form action="{{ route('orders.pay', $order->order_number) }}" method="POST" onsubmit="this.querySelector('button').disabled = true">
          @csrf
          <button class="btn btn-primary">Bayar sekarang</button>
        </form>
        <form action="{{ route('orders.cancel', $order->order_number) }}" method="POST" onsubmit="return confirm('Batalkan pesanan ini?')">
          @csrf
          <button class="btn btn-danger">Batalkan pesanan</button>
        </form>
      </div>
      <p class="mt-2 text-xs text-gray-500">Kamu akan diarahkan ke halaman pembayaran iPaymu (VA, QRIS, e-wallet, dan lainnya).</p>
    @endif
  </div>
@endsection
