<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\EmailTemplate;
use App\Services\TicketQrImage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GuestOrderTickets extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
        $this->order->loadMissing(['items.tickets', 'items.ticketType.product']);
    }

    public function envelope(): Envelope
    {
        $subject = EmailTemplate::fill($this->primaryTemplate()['subject'], $this->order);

        return new Envelope(
            subject: trim(str_replace(["\r", "\n"], ' ', $subject)) ?: 'E-tiket pesanan '.$this->order->order_number,
        );
    }

    public function content(): Content
    {
        $itemTemplates = $this->itemTemplates();

        return new Content(
            view: 'emails.orders.guest-tickets',
            with: [
                'template' => $this->primaryTemplate(),
                'itemTemplates' => $itemTemplates,
                'qrCodes' => $this->qrCodes($itemTemplates),
            ],
        );
    }

    /** Template per order item, from that item's event (its own settings, else the global ones). */
    private function itemTemplates(): array
    {
        $templates = [];

        foreach ($this->order->items as $item) {
            $templates[$item->id] = EmailTemplate::forProduct($item->ticketType?->product);
        }

        return $templates;
    }

    /**
     * Subject, heading, greeting and notes are per e-mail, so they come from the first event in the order.
     * (A normal order holds tickets of one event; ticket frames and QR backgrounds follow each ticket's event.)
     */
    private function primaryTemplate(): array
    {
        $first = $this->order->items->first();

        return EmailTemplate::forProduct($first?->ticketType?->product);
    }

    /**
     * One PNG per ticket, keyed by ticket id (PNG because e-mail apps do not show SVG).
     *
     * @return array<int, string> raw PNG bytes
     */
    private function qrCodes(array $itemTemplates): array
    {
        $qrCodes = [];

        foreach ($this->order->items as $item) {
            $product = $item->ticketType?->product;

            foreach ($item->tickets as $ticket) {
                $qrCodes[$ticket->id] = TicketQrImage::png($ticket->qr_token, $itemTemplates[$item->id], [
                    'event' => $item->product_name_snapshot,
                    'ticket_type' => $item->ticket_type_name_snapshot,
                    'date' => $product?->start_at?->translatedFormat('d M Y, H:i') ?? 'Jadwal belum ditentukan',
                    'location' => $product?->location ?: 'Lokasi belum ditentukan',
                    'holder' => $this->order->buyer_name,
                    'phone' => $this->order->buyer_phone,
                    'ticket_code' => $ticket->ticket_code,
                    'order_number' => $this->order->order_number,
                ]);
            }
        }

        return $qrCodes;
    }
}
