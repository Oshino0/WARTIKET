@extends('layouts.admin')
@section('title', 'Dashboard')
@section('content')
  <h1 class="text-2xl font-bold">Dashboard</h1>

  <dl class="mt-6 grid gap-px overflow-hidden rounded-2xl border border-violet-100 bg-violet-100 sm:grid-cols-4 lg:grid-cols-8">
    @foreach ([
      ['Omzet (dibayar)', \App\Support\Money::rp($stats['revenue'])],
      ['Total pesanan', number_format($stats['orders'], 0, ',', '.')],
      ['Menunggu bayar', number_format($stats['pending'], 0, ',', '.')],
      ['Menunggu verifikasi', number_format($stats['pending_verification'], 0, ',', '.')],
      ['Tiket terjual', number_format($stats['tickets_sold'], 0, ',', '.')],
      ['Tiket dicadangkan', number_format($stats['reserved'], 0, ',', '.')],
      ['Produk aktif', number_format($stats['products'], 0, ',', '.')],
      ['Perlu ditinjau', number_format($stats['review'], 0, ',', '.')],
    ] as [$label, $value])
      <div class="bg-white p-4">
        <dt class="text-xs text-gray-500">{{ $label }}</dt>
        <dd class="mt-1 text-xl font-bold">{{ $value }}</dd>
      </div>
    @endforeach
  </dl>

  <h2 class="mt-10 text-lg font-bold">Pesanan terbaru</h2>
  <div class="mt-3 overflow-x-auto rounded-2xl border border-violet-100 bg-white">
    <table class="w-full min-w-[32rem]">
      <thead class="bg-brand-surface"><tr><th class="th">Pesanan</th><th class="th">Pembeli</th><th class="th text-right">Total</th><th class="th">Status</th></tr></thead>
      <tbody class="divide-y divide-violet-100">
        @forelse ($recent as $order)
          <tr>
            <td class="td font-medium"><a href="{{ route('admin.orders.show', $order) }}" class="hover:text-brand">{{ $order->order_number }}</a></td>
            <td class="td">{{ $order->buyer_name ?? $order->user?->name }}</td>
            <td class="td text-right">{{ \App\Support\Money::rp($order->total) }}</td>
            <td class="td"><x-status-badge :order="$order" /></td>
          </tr>
        @empty
          <tr><td class="td text-center text-gray-500" colspan="4">Belum ada pesanan.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
@endsection
