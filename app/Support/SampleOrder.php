<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketQrImage;

/** In-memory order used only to preview the ticket e-mail. Nothing here is saved. */
class SampleOrder
{
    /** @return array<string, mixed> view data for admin.email.preview */
    public static function previewData(User $viewer, array $template, string $eventName = 'Nama Acara Contoh'): array
    {
        $order = new Order([
            'order_number' => 'WTK-PRATINJAU',
            'buyer_name' => 'Nama Pembeli',
            'buyer_email' => $viewer->email,
            'total' => 250000,
        ]);

        $ticket = new Ticket(['ticket_code' => 'WTK-PRATINJAU-001', 'qr_token' => 'PRATINJAU-'.str_repeat('X', 38)]);
        $ticket->id = 1;

        $item = new OrderItem([
            'product_name_snapshot' => $eventName,
            'ticket_type_name_snapshot' => 'Reguler',
            'quantity' => 1,
        ]);
        $item->setRelation('tickets', collect([$ticket]));
        $item->setRelation('ticketType', null);
        $order->setRelation('items', collect([$item]));

        return [
            'order' => $order,
            'template' => $template,
            'qrCodes' => [1 => TicketQrImage::png($ticket->qr_token, $template)],
            'message' => new PreviewMessage,
        ];
    }
}
