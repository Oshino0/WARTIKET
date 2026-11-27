@extends('layouts.app')
@section('title', 'Ringkasan pesanan')
@section('content')
  <div class="mx-auto max-w-2xl">
    <h1 class="text-2xl font-bold">Data pembeli &amp; pesanan</h1>
    <p class="mt-1 text-sm text-gray-500">Isi data yang akan digunakan untuk mengirim e-tiket setelah pesanan diverifikasi.</p>

    <div class="mt-6 overflow-hidden rounded-2xl border border-violet-100">
      <table class="w-full">
        <thead class="bg-brand-surface"><tr><th class="th">Tiket</th><th class="th text-right">Jumlah</th><th class="th text-right">Subtotal</th></tr></thead>
        <tbody class="divide-y divide-violet-100">
          @foreach ($quote['lines'] as $line)
            <tr>
              <td class="td">
                <p class="font-semibold">{{ $line['ticket_type']->product->name }}</p>
                <p class="text-gray-500">{{ $line['ticket_type']->name }} • {{ \App\Support\Money::rp($line['unit_price']) }}</p>
              </td>
              <td class="td text-right">{{ $line['quantity'] }}</td>
              <td class="td text-right">{{ \App\Support\Money::rp($line['line_total']) }}</td>
            </tr>
          @endforeach
        </tbody>
        <tfoot class="border-t border-violet-100 text-sm">
          <tr><td class="td text-gray-500" colspan="2">Subtotal</td><td class="td text-right">{{ \App\Support\Money::rp($quote['subtotal']) }}</td></tr>
          @if ($quote['admin_fee'] > 0)
            <tr><td class="td text-gray-500" colspan="2">Biaya layanan</td><td class="td text-right">{{ \App\Support\Money::rp($quote['admin_fee']) }}</td></tr>
          @endif
          <tr><td class="td text-base font-bold" colspan="2">Total</td><td class="td text-right text-base font-bold text-brand">{{ \App\Support\Money::rp($quote['total']) }}</td></tr>
        </tfoot>
      </table>
    </div>

    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
      Pesanan akan menunggu verifikasi admin. E-tiket dikirim ke alamat email setelah pesanan dikonfirmasi.
    </div>

    <form action="{{ route('checkout.store') }}" method="POST" class="mt-6 space-y-4" onsubmit="this.querySelector('button').disabled = true">
      @csrf
      @foreach ($quantities as $id => $qty)
        <input type="hidden" name="items[{{ $id }}]" value="{{ $qty }}">
      @endforeach
      <x-field label="Nama pembeli" name="buyer_name" :value="old('buyer_name')" minlength="3" maxlength="100" autocomplete="name" required />
      <x-field label="Alamat email" name="buyer_email" type="email" :value="old('buyer_email')" maxlength="150" autocomplete="email" required />
      <x-field label="Nomor WhatsApp" name="buyer_phone" type="tel" :value="old('buyer_phone')" maxlength="30"
               pattern="[+]?[0-9][0-9 ()-]{6,28}[0-9]" autocomplete="tel" placeholder="+6281234567890" required
               hint="Gunakan nomor aktif dengan kode negara, misalnya +6281234567890." />
      <div class="flex flex-wrap gap-3">
        <button class="btn btn-primary">Checkout</button>
        <a href="{{ route('products.show', $product) }}" class="btn btn-ghost">Ubah pilihan tiket</a>
      </div>
    </form>
  </div>
@endsection
