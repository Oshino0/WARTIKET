@php
  $accent = $template['accent_color'];
  $fill = fn (string $text) => \App\Services\EmailTemplate::fill($text, $order);
@endphp
<div style="font-family: Arial, Helvetica, sans-serif; color: #1F2937; max-width: 560px; margin: 0 auto;">
  <h1 style="font-size: 22px; color: {{ $accent }};">{{ $fill($template['heading']) }}</h1>
  <p>{{ $fill($template['greeting']) }}</p>
  @if (filled($template['intro']))
    <p>{!! nl2br(e($fill($template['intro']))) !!}</p>
  @endif
  <p>
    Nomor pesanan: <strong>{{ $order->order_number }}</strong><br>
    Total: <strong>{{ \App\Support\Money::rp($order->total) }}</strong>
  </p>

  @foreach ($order->items as $item)
    <h2 style="font-size: 17px; margin-top: 24px;">{{ $item->product_name_snapshot }} — {{ $item->ticket_type_name_snapshot }}</h2>
    @if ($item->ticketType?->product?->start_at)
      <p style="margin: 0 0 8px;">Jadwal: {{ $item->ticketType->product->start_at->translatedFormat('d F Y, H:i') }}</p>
    @endif
    @php $frame = ($itemTemplates[$item->id] ?? $template)['accent_color']; @endphp
    @foreach ($item->tickets as $ticket)
      <div style="margin: 16px 0; padding: 16px; border: 1px solid {{ $frame }}; border-radius: 12px; text-align: center;">
        <p style="margin: 0 0 12px;">Kode tiket: <strong>{{ $ticket->ticket_code }}</strong></p>
        <img src="{{ $message->embedData($qrCodes[$ticket->id], 'ticket-'.$ticket->id.'.png', 'image/png') }}"
             width="520" alt="E-ticket {{ $item->product_name_snapshot }} — {{ $item->ticket_type_name_snapshot }} — {{ $ticket->ticket_code }}"
             style="display: block; margin: 0 auto; width: 520px; max-width: 100%; height: auto; border: 0;">
      </div>
    @endforeach
  @endforeach

  @if (filled($template['footer']))
    <p>{!! nl2br(e($fill($template['footer']))) !!}</p>
  @endif
</div>
