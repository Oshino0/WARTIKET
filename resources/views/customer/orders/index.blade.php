@extends('layouts.app')
@section('title', 'Pesanan saya')
@section('content')
  <h1 class="text-2xl font-bold">Pesanan saya</h1>

  @if ($orders->isEmpty())
    <div class="mt-6 rounded-2xl border border-dashed border-violet-200 p-10 text-center">
      <p class="text-gray-500">Kamu belum punya pesanan.</p>
      <a href="{{ route('explore') }}" class="btn btn-primary mt-4">Cari tiket</a>
    </div>
  @else
    <ul class="mt-6 space-y-3">
      @foreach ($orders as $order)
        <li>
          <a href="{{ route('orders.show', $order->order_number) }}" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-violet-100 p-4 hover:border-brand-accent">
            <div>
              <p class="font-semibold">{{ $order->order_number }}</p>
              <p class="text-sm text-gray-500">
                {{ $order->created_at->translatedFormat('d M Y, H:i') }} • {{ $order->items->sum('quantity') }} tiket
                • {{ $order->items->pluck('product_name_snapshot')->unique()->implode(', ') }}
              </p>
            </div>
            <div class="flex items-center gap-4">
              <span class="font-bold">{{ \App\Support\Money::rp($order->total) }}</span>
              <x-status-badge :order="$order" />
            </div>
          </a>
        </li>
      @endforeach
    </ul>
    <div class="mt-6">{{ $orders->links() }}</div>
  @endif
@endsection
