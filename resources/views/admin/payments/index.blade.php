@extends('layouts.admin')
@section('title', 'Pembayaran')
@section('content')
  <h1 class="text-2xl font-bold">Pembayaran</h1>

  <form method="GET" class="mt-6 flex flex-wrap items-center gap-3">
    <label for="status" class="sr-only">Status</label>
    <select id="status" name="status" class="input max-w-[14rem]">
      <option value="">Semua status</option>
      @foreach (\App\Models\Payment::LABELS as $value => $label)
        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
      @endforeach
    </select>
    <label class="flex items-center gap-2 text-sm">
      <input type="checkbox" name="review" value="1" class="rounded border-gray-300 text-brand focus:ring-brand" @checked(request()->boolean('review'))>
      Hanya yang perlu ditinjau
    </label>
    <button class="btn btn-ghost">Filter</button>
  </form>

  <div class="mt-4 overflow-x-auto rounded-2xl border border-violet-100 bg-white">
    <table class="w-full min-w-[52rem]">
      <thead class="bg-brand-surface"><tr>
        <th class="th">Referensi</th><th class="th">Customer</th><th class="th text-right">Jumlah</th>
        <th class="th">Metode</th><th class="th">Status</th><th class="th">Dibayar</th><th class="th">Catatan</th>
      </tr></thead>
      <tbody class="divide-y divide-violet-100">
        @forelse ($payments as $payment)
          <tr class="{{ $payment->needs_review ? 'bg-amber-50' : '' }}">
            <td class="td font-medium">{{ $payment->reference_id }}<br>
              <span class="text-xs font-normal text-gray-500">iPaymu #{{ $payment->transaction_id ?? '–' }} • pesanan {{ $payment->order->status }}</span></td>
            <td class="td">{{ $payment->order->buyer_name ?? $payment->order->user?->name }}</td>
            <td class="td text-right">{{ \App\Support\Money::rp($payment->amount) }}</td>
            <td class="td">{{ $payment->payment_method ?? '–' }}</td>
            <td class="td">{{ $payment->label() }}</td>
            <td class="td text-gray-500">{{ $payment->paid_at?->translatedFormat('d M Y, H:i') ?? '–' }}</td>
            <td class="td text-amber-900">{{ $payment->review_note }}</td>
          </tr>
        @empty
          <tr><td class="td text-center text-gray-500" colspan="7">Belum ada pembayaran.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-4">{{ $payments->links() }}</div>
@endsection
