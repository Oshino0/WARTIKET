<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\EmailTemplate;
use App\Services\TicketImage;
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
                'ticketImages' => $this->ticketImages($itemTemplates),
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
     * One finished e-ticket picture (PNG) per ticket, keyed by ticket id. PNG because e-mail apps do not
     * show SVG; the whole ticket is drawn into the image because they also ignore CSS backgrounds.
     *
     * @return array<int, string> raw PNG bytes
     */
    private function ticketImages(array $itemTemplates): array
    {
        $images = [];

        foreach ($this->order->items as $item) {
            foreach ($item->tickets as $ticket) {
                $template = $itemTemplates[$item->id];
                $images[$ticket->id] = TicketImage::render(
                    TicketImage::detailsFor($this->order, $item, $ticket, $template),
                    $template,
                );
            }
        }

        return $images;
    }
}
