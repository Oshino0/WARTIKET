<?php

namespace App\Services;

use App\Events\OrderPaid;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    /** Callback fields worth keeping for audit. Buyer details and payment numbers are deliberately not stored. */
    private const KEEP = [
        'trx_id', 'sid', 'reference_id', 'status', 'status_code', 'status_desc', 'transaction_status_code',
        'via', 'channel', 'amount', 'fee', 'paid_off', 'paid_at', 'created_at', 'expired_at',
        'settlement_status', 'is_sandbox',
    ];

    public function __construct(private IpaymuService $ipaymu)
    {
    }

    /**
     * Create (or reuse) a payment attempt for a pending order and return the iPaymu checkout URL.
     */
    public function start(Order $order, User $buyer): string
    {
        $payment = DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending() || $locked->expires_at->isPast()) {
                throw ValidationException::withMessages(['order' => 'Pesanan ini sudah tidak bisa dibayar.']);
            }

            $open = $locked->payments()
                ->whereIn('status', [Payment::PENDING, Payment::PROCESSING])
                ->latest('id')->first();

            if ($open) {
                if ($open->payment_url) {
                    return $open; // same checkout page, no second charge session
                }
                if ($open->created_at->gt(now()->subMinute())) {
                    throw ValidationException::withMessages(['order' => 'Pembayaran sedang disiapkan. Coba lagi sebentar.']);
                }
                $open->update(['status' => Payment::FAILED]); // an earlier attempt died before getting a URL
            }

            return Payment::create([
                'order_id' => $locked->id,
                'reference_id' => $locked->order_number . '-' . ($locked->payments()->count() + 1),
                'provider' => 'ipaymu',
                'amount' => $locked->total,
                'status' => Payment::PENDING,
                'expires_at' => $locked->expires_at,
            ]);
        });

        if ($payment->payment_url) {
            return $payment->payment_url;
        }

        // The HTTP call happens outside the transaction so no row locks are held while waiting on iPaymu.
        $order->load('items');
        $hours = max(1, (int) ceil(now()->diffInMinutes($order->expires_at, false) / 60));

        try {
            $session = $this->ipaymu->createRedirectPayment($order, $payment, $buyer, $hours);
        } catch (\Throwable $e) {
            $payment->update(['status' => Payment::FAILED]);
            Log::error('iPaymu payment creation failed', ['order' => $order->order_number, 'error' => $e->getMessage()]);

            throw ValidationException::withMessages(['order' => 'Pembayaran belum bisa dibuat. Coba lagi sebentar lagi.']);
        }

        $payment->update(['session_id' => $session['session_id'], 'payment_url' => $session['url']]);

        return $session['url'];
    }

    /**
     * Process a callback whose signature has already been validated.
     *
     * Returns a short result string. Throws only when iPaymu could not be reached to confirm a
     * "paid" claim, so the controller answers 5xx and iPaymu retries later.
     */
    public function handleCallback(array $payload): string
    {
        $reference = $payload['reference_id'] ?? $payload['referenceId'] ?? null;

        $payment = $reference ? Payment::where('reference_id', $reference)->first() : null;
        if (! $payment && ! empty($payload['sid'])) {
            $payment = Payment::where('session_id', $payload['sid'])->first();
        }

        if (! $payment) {
            Log::warning('iPaymu callback for unknown reference', ['reference' => $reference]);

            return 'ignored';
        }

        $claimed = $this->outcome((int) ($payload['transaction_status_code'] ?? $payload['status_code'] ?? 0));

        if ($claimed !== 'paid') {
            return $this->apply($payment->id, $claimed, $payload);
        }

        // A "paid" claim grants tickets, so confirm status and amount with iPaymu itself (PRD 5 step 11).
        $tx = $this->ipaymu->checkTransaction((string) ($payload['trx_id'] ?? ''));

        $confirmed = $this->outcome((int) ($tx['Status'] ?? -99));
        $amountMatches = (int) round((float) ($tx['Amount'] ?? -1)) === (int) round((float) $payment->amount);
        $referenceMatches = empty($tx['ReferenceId']) || $tx['ReferenceId'] === $payment->reference_id;

        if ($confirmed === 'paid' && ! ($amountMatches && $referenceMatches)) {
            $payment->update([
                'needs_review' => true,
                'review_note' => 'Nominal atau referensi dari iPaymu tidak cocok dengan pesanan.',
            ]);
            AuditLog::record('payment.mismatch', $payment, ['expected' => $payment->amount, 'received' => $tx['Amount'] ?? null]);

            return 'mismatch'; // answered 200: retrying the same data will not change the outcome
        }

        return $this->apply($payment->id, $confirmed, $payload);
    }

    // ------------------------------------------------------------------

    private function apply(int $paymentId, string $outcome, array $payload): string
    {
        return DB::transaction(function () use ($paymentId, $outcome, $payload) {
            // Lock order: payment -> order -> ticket types (by id). OrderService::release uses order -> types.
            $payment = Payment::whereKey($paymentId)->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();

            // Business rule 9: a payment is never processed twice. Late "pending/failed" repeats are ignored too.
            if ($payment->status === Payment::PAID) {
                return 'duplicate';
            }

            $this->record($payment, $payload);

            if ($outcome === 'paid') {
                return $this->settle($payment, $order, $payload);
            }

            if (in_array($outcome, ['failed', 'expired'], true)) {
                if (in_array($payment->status, [Payment::PENDING, Payment::PROCESSING], true)) {
                    $payment->status = $outcome === 'expired' ? Payment::EXPIRED : Payment::FAILED;
                }
                $payment->save();

                return $outcome;
            }

            if ($payment->status === Payment::PENDING && (! empty($payload['via']) || ! empty($payload['channel']))) {
                $payment->status = Payment::PROCESSING; // customer picked a method, money not yet received
            }
            $payment->save();

            return 'pending';
        });
    }

    /** Verified money received: turn reservations into sales and mark the order paid. */
    private function settle(Payment $payment, Order $order, array $payload): string
    {
        $items = $order->items()->orderBy('ticket_type_id')->get();
        $types = TicketType::withTrashed()
            ->whereIn('id', $items->pluck('ticket_type_id'))
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        $payment->status = Payment::PAID;
        $payment->paid_at = $this->paidAt($payload);

        if ($order->status === Order::PAID) {
            // A second attempt for the same order was also paid.
            $this->flag($payment, 'Pembayaran ganda: pesanan sudah lunas lewat transaksi lain. Perlu refund manual.');
            $payment->save();
            AuditLog::record('payment.double_paid', $payment);

            return 'double_paid';
        }

        if ($order->status === Order::PENDING) {
            foreach ($items as $item) {
                $type = $types[$item->ticket_type_id];
                $type->sold += $item->quantity;
                $type->reserved = max(0, $type->reserved - $item->quantity); // reservation becomes a sale
                $type->save();
            }

            $order->update(['status' => Order::PAID]);
            $payment->save();
            AuditLog::record('payment.paid', $payment, ['order' => $order->order_number]);
            DB::afterCommit(fn () => event(new OrderPaid($order)));

            return 'paid';
        }

        if (in_array($order->status, [Order::EXPIRED, Order::CANCELLED], true)) {
            // Late payment policy: honour it if stock is still free, otherwise keep the money
            // flagged for a manual refund. Sold + reserved never exceeds quota either way (rule 13).
            $fits = $items->every(fn ($item) => $types[$item->ticket_type_id]->available() >= $item->quantity);

            if ($fits) {
                foreach ($items as $item) {
                    $type = $types[$item->ticket_type_id];
                    $type->sold += $item->quantity;
                    $type->save();
                }

                $order->update(['status' => Order::PAID]);
                $payment->save();
                AuditLog::record('payment.late_recovered', $payment, ['order' => $order->order_number]);
                DB::afterCommit(fn () => event(new OrderPaid($order)));

                return 'paid_late';
            }

            $this->flag($payment, 'Pembayaran masuk setelah pesanan kedaluwarsa dan stok sudah habis. Perlu refund manual.');
            $payment->save();
            AuditLog::record('payment.late_unfulfillable', $payment, ['order' => $order->order_number]);

            return 'paid_unfulfillable';
        }

        $this->flag($payment, 'Pembayaran masuk untuk pesanan berstatus ' . $order->status . '. Perlu ditinjau.');
        $payment->save();
        AuditLog::record('payment.unexpected_state', $payment, ['order_status' => $order->status]);

        return 'paid_unexpected_state';
    }

    private function record(Payment $payment, array $payload): void
    {
        $payment->callback_payload = Arr::only($payload, self::KEEP);

        $trx = (string) ($payload['trx_id'] ?? '');
        if ($trx !== '' && ! $payment->transaction_id
            && ! Payment::where('transaction_id', $trx)->where('id', '!=', $payment->id)->exists()) {
            $payment->transaction_id = $trx;
        }

        $method = trim(($payload['via'] ?? '') . ' ' . ($payload['channel'] ?? ''));
        if ($method !== '') {
            $payment->payment_method = str_replace(' ', '/', $method);
        }
    }

    private function flag(Payment $payment, string $note): void
    {
        $payment->needs_review = true;
        $payment->review_note = $note;
    }

    private function paidAt(array $payload): Carbon
    {
        try {
            return ! empty($payload['paid_at']) ? Carbon::parse($payload['paid_at']) : now();
        } catch (\Throwable) {
            return now();
        }
    }

    /** iPaymu status codes: 1 success, 6 success-unsettled, 0 pending, -2 expired, 2/4/5 cancelled/error/failed. */
    private function outcome(int $code): string
    {
        return match ($code) {
            1, 6 => 'paid',
            -2 => 'expired',
            2, 4, 5 => 'failed',
            default => 'pending', // 0 pending, 7 escrow, 3 refund (refunds are out of MVP scope)
        };
    }
}
