<?php

namespace App\Services;

use App\Mail\GuestOrderTickets;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderVerificationService
{
    public function confirmAndSend(Order $order): void
    {
        $this->ensureDeliverableMailer();

        DB::transaction(function () use ($order): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->status !== Order::PENDING_VERIFICATION) {
                throw ValidationException::withMessages([
                    'order' => 'Pesanan ini sudah diproses dan tidak dapat dikonfirmasi kembali.',
                ]);
            }

            $sequence = 0;
            foreach ($lockedOrder->items()->orderBy('ticket_type_id')->get() as $item) {
                $type = TicketType::withTrashed()->whereKey($item->ticket_type_id)->lockForUpdate()->firstOrFail();

                if ($type->reserved < $item->quantity) {
                    throw ValidationException::withMessages([
                        'order' => 'Stok tiket yang dicadangkan tidak sesuai. Periksa stok sebelum melanjutkan.',
                    ]);
                }

                $type->sold += $item->quantity;
                $type->reserved -= $item->quantity;
                $type->save();

                for ($i = 0; $i < $item->quantity; $i++) {
                    $sequence++;
                    Ticket::create([
                        'order_item_id' => $item->id,
                        'ticket_code' => $lockedOrder->order_number.'-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
                        'qr_token' => Str::random(48),
                    ]);
                }
            }

            $lockedOrder->load(['items.tickets', 'items.ticketType.product']);
            Mail::to($lockedOrder->buyer_email)->send(new GuestOrderTickets($lockedOrder));

            $lockedOrder->update(['status' => Order::SENT]);
            AuditLog::record('order.guest_sent', $lockedOrder);
        });
    }

    public function resend(Order $order): void
    {
        $this->ensureDeliverableMailer();

        $order->loadMissing(['items.tickets', 'items.ticketType.product']);

        if (
            $order->status !== Order::SENT
            || blank($order->buyer_email)
            || $order->items->flatMap(fn ($item) => $item->tickets)->isEmpty()
        ) {
            throw ValidationException::withMessages([
                'order' => 'E-tiket pesanan ini tidak dapat dikirim ulang.',
            ]);
        }

        Mail::to($order->buyer_email)->send(new GuestOrderTickets($order));
        AuditLog::record('order.guest_resent', $order);
    }

    public function reject(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->status !== Order::PENDING_VERIFICATION) {
                throw ValidationException::withMessages([
                    'order' => 'Hanya pesanan yang menunggu verifikasi yang dapat ditolak.',
                ]);
            }

            foreach ($lockedOrder->items()->orderBy('ticket_type_id')->get() as $item) {
                $type = TicketType::withTrashed()->whereKey($item->ticket_type_id)->lockForUpdate()->firstOrFail();

                if ($type->reserved < $item->quantity) {
                    throw ValidationException::withMessages([
                        'order' => 'Stok tiket yang dicadangkan tidak sesuai. Periksa stok sebelum menolak.',
                    ]);
                }

                $type->decrement('reserved', $item->quantity);
            }

            $lockedOrder->update(['status' => Order::REJECTED]);
            AuditLog::record('order.guest_rejected', $lockedOrder);
        });
    }

    private function ensureDeliverableMailer(): void
    {
        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            throw ValidationException::withMessages([
                'order' => 'Transport email hanya mencatat pesan. Atur MAIL_MAILER ke layanan email aktif sebelum memproses pesanan.',
            ]);
        }

        if (config('mail.default') === 'resend') {
            if (blank(config('services.resend.key'))) {
                throw ValidationException::withMessages([
                    'order' => 'RESEND_API_KEY belum diatur. Tambahkan API key Resend ke file .env sebelum memproses pesanan.',
                ]);
            }

            if (blank(config('mail.from.address')) || config('mail.from.address') === 'hello@example.com') {
                throw ValidationException::withMessages([
                    'order' => 'MAIL_FROM_ADDRESS harus menggunakan alamat pengirim dari domain yang sudah diverifikasi di Resend.',
                ]);
            }
        }
    }
}
