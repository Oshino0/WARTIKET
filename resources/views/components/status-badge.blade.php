@props(['order'])
@php
  $style = match ($order->status) {
    'pending', 'pending_verification' => 'bg-amber-50 text-amber-800 border-amber-200',
    'paid', 'sent' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
    'refunded' => 'bg-sky-50 text-sky-800 border-sky-200',
    'rejected' => 'bg-red-50 text-red-800 border-red-200',
    default => 'bg-gray-100 text-gray-700 border-gray-200',
  };
@endphp
<span class="inline-flex rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $style }}">{{ $order->label() }}</span>
