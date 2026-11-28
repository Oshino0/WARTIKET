@php
  $accent = $template['accent_color'];
  $fill = fn (string $text) => \App\Services\EmailTemplate::fill($text, $order);
@endphp
<div style="font-family: Arial, Helvetica, sans-serif; color: #1F2937; max-width: 600px; margin: 0 auto;">
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
    @foreach ($item->tickets as $ticket)
      <div style="margin: 20px 0; text-align: center;">
        <img src="{{ $message->embedData($ticketImages[$ticket->id], 'tiket-'.$ticket->ticket_code.'.png', 'image/png') }}"
             width="560" alt="E-tiket {{ $item->product_name_snapshot }} - {{ $item->ticket_type_name_snapshot }} - {{ $ticket->ticket_code }}"
             style="display: block; width: 100%; max-width: 560px; height: auto; border: 0; margin: 0 auto;">
        <p style="margin: 8px 0 0; font-size: 13px; color: #6B7280;">
          {{ $item->product_name_snapshot }} &middot; {{ $item->ticket_type_name_snapshot }} &middot; Kode tiket: <strong>{{ $ticket->ticket_code }}</strong>
        </p>
      </div>
    @endforeach
  @endforeach

  @if (filled($template['footer']))
    <p>{!! nl2br(e($fill($template['footer']))) !!}</p>
  @endif
</div>
