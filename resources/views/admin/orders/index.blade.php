@extends('layouts.admin')
@section('title', 'Pesanan')
@section('content')
  <h1 class="text-2xl font-bold">Pesanan</h1>

  <form method="GET" class="mt-6 flex flex-wrap gap-3" role="search">
    <label for="q" class="sr-only">Cari pesanan</label>
    <input id="q" name="q" value="{{ $search }}" placeholder="Nomor pesanan, nama, atau email" class="input max-w-xs">
    <label for="status" class="sr-only">Status</label>
    <select id="status" name="status" class="input max-w-[14rem]">
      <option value="">Semua status</option>
      @foreach (\App\Models\Order::LABELS as $value => $label)
        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
      @endforeach
    </select>
    <button class="btn btn-ghost">Filter</button>
  </form>

  <div class="mt-4 overflow-x-auto rounded-2xl border border-violet-100 bg-white">
    <table class="w-full min-w-[44rem]">
      <thead class="bg-brand-surface"><tr><th class="th">Pesanan</th><th class="th">Pembeli</th><th class="th">Tiket</th><th class="th text-right">Total</th><th class="th">Dibuat</th><th class="th">Status</th><th class="th"></th></tr></thead>
      <tbody class="divide-y divide-violet-100">
        @forelse ($orders as $order)
          <tr>
            <td class="td font-medium"><a href="{{ route('admin.orders.show', $order) }}" class="hover:text-brand">{{ $order->order_number }}</a></td>
            <td class="td">{{ $order->buyer_name ?? $order->user?->name }}<br><span class="text-xs text-gray-500">{{ $order->buyer_email ?? $order->user?->email }}</span><br><span class="text-xs text-gray-500">{{ $order->buyer_phone ?? $order->user?->phone }}</span></td>
            <td class="td">{{ $order->items->sum('quantity') }}</td>
            <td class="td text-right">{{ \App\Support\Money::rp($order->total) }}</td>
            <td class="td text-gray-500">{{ $order->created_at->translatedFormat('d M Y, H:i') }}</td>
            <td class="td"><x-status-badge :order="$order" /></td>
            <td class="td text-right"><a href="{{ route('admin.orders.show', $order) }}" class="btn btn-ghost !py-1">Detail</a></td>
          </tr>
        @empty
          <tr><td class="td text-center text-gray-500" colspan="7">Tidak ada pesanan.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-4">{{ $orders->links() }}</div>
@endsection
