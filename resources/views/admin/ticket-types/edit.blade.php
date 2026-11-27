@extends('layouts.admin')
@section('title', 'Ubah tipe tiket')
@section('content')
  <a href="{{ route('admin.products.edit', $ticketType->product_id) }}" class="text-sm font-semibold text-brand">← {{ $ticketType->product->name }}</a>
  <h1 class="mt-2 text-2xl font-bold">Ubah tipe tiket</h1>
  <p class="mt-1 text-sm text-gray-500">Terjual {{ $ticketType->sold }} • dicadangkan {{ $ticketType->reserved }}. Pesanan lama tetap memakai harga saat dibeli.</p>

  <form action="{{ route('admin.ticket-types.update', $ticketType) }}" method="POST" class="mt-6 max-w-3xl space-y-4 rounded-2xl border border-violet-100 bg-white p-6">
    @csrf @method('PUT')
    @include('admin.ticket-types._fields', ['type' => $ticketType])
    <div class="flex gap-3">
      <button class="btn btn-primary">Simpan tipe tiket</button>
      <a href="{{ route('admin.products.edit', $ticketType->product_id) }}" class="btn btn-ghost">Batal</a>
    </div>
  </form>
@endsection
