<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * All pricing, stock and eligibility checks live here (business rule 14).
 * The browser only ever sends ticket_type_id => quantity.
 */
class OrderService
{
    /** Price preview for the checkout review page. No locks, no writes. */
    public function quote(array $quantities): array
    {
        $quantities = $this->normalise($quantities);

        $types = TicketType::with('product')
            ->whereIn('id', array_keys($quantities))
            ->get()
            ->keyBy('id');

        return $this->buildLines($types, $quantities);
    }

    /**
     * Validate again under row locks, create a pending order and reserve stock atomically.
     */
    public function create(User $user, array $quantities): Order
    {
        return $this->createOrder($quantities, [
            'user_id' => $user->id,
            'status' => Order::PENDING,
            'expires_at' => now()->addMinutes($this->paymentWindowMinutes()),
        ]);
    }

    public function createGuest(string $name, string $email, string $phone, array $quantities): Order
    {
        return $this->createOrder($quantities, [
            'buyer_name' => $name,
            'buyer_email' => $email,
            'buyer_phone' => $phone,
            'status' => Order::PENDING_VERIFICATION,
            'expires_at' => null,
        ]);
    }

    private function createOrder(array $quantities, array $orderDetails): Order
    {
        $quantities = $this->normalise($quantities);

        return DB::transaction(function () use ($quantities, $orderDetails) {
            // Lock in a stable order (by id) so concurrent checkouts cannot deadlock each other.
            $types = TicketType::with('product')
                ->whereIn('id', array_keys($quantities))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $quote = $this->buildLines($types, $quantities);

            $order = Order::create(array_merge([
                'order_number' => $this->newOrderNumber(),
                'subtotal' => $quote['subtotal'],
                'admin_fee' => $quote['admin_fee'],
                'total' => $quote['total'],
            ], $orderDetails));

            foreach ($quote['lines'] as $line) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'ticket_type_id' => $line['ticket_type']->id,
                    'product_name_snapshot' => $line['ticket_type']->product->name,
                    'ticket_type_name_snapshot' => $line['ticket_type']->name,
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'line_total' => $line['line_total'],
                ]);

                $line['ticket_type']->increment('reserved', $line['quantity']);
            }

            AuditLog::record('order.created', $order, ['total' => $order->total]);

            return $order;
        });
    }

    /**
     * Release reserved stock and close a pending order. Idempotent: calling it
     * again on an order that is no longer pending does nothing.
     */
    public function release(Order $order, string $newStatus = Order::CANCELLED): bool
    {
        return DB::transaction(function () use ($order, $newStatus) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();

            if (! $locked || ! $locked->isPending()) {
                return false;
            }

            foreach ($locked->items()->orderBy('ticket_type_id')->get() as $item) {
                TicketType::withTrashed()
                    ->whereKey($item->ticket_type_id)
                    ->where('reserved', '>=', $item->quantity)
                    ->decrement('reserved', $item->quantity);
            }

            $locked->update(['status' => $newStatus]);
            AuditLog::record('order.'.$newStatus, $locked);

            return true;
        });
    }

    /** Expire every pending order past its deadline. Returns how many were released. */
    public function expireDue(): int
    {
        $count = 0;

        Order::pending()->where('expires_at', '<=', now())->orderBy('id')->each(function (Order $order) use (&$count) {
            if ($this->release($order, Order::EXPIRED)) {
                $count++;
            }
        });

        return $count;
    }

    public function paymentWindowMinutes(): int
    {
        return max(5, (int) Setting::get('payment_window_minutes', 30));
    }

    // ------------------------------------------------------------------

    /** @return array<int,int> ticket_type_id => quantity, zero quantities removed */
    private function normalise(array $quantities): array
    {
        $clean = [];

        foreach ($quantities as $id => $qty) {
            $qty = (int) $qty;
            if ((int) $id > 0 && $qty > 0) {
                $clean[(int) $id] = $qty;
            }
        }

        if (! $clean) {
            throw ValidationException::withMessages(['items' => 'Pilih minimal satu tiket.']);
        }

        return $clean;
    }

    private function buildLines(Collection $types, array $quantities): array
    {
        if ($types->count() !== count($quantities)) {
            throw ValidationException::withMessages(['items' => 'Ada tipe tiket yang tidak ditemukan. Muat ulang halaman dan coba lagi.']);
        }

        $lines = [];
        $subtotal = 0.0;

        foreach ($quantities as $id => $qty) {
            /** @var TicketType $type */
            $type = $types[$id];
            $label = $type->product->name.' – '.$type->name;

            if ($type->product->status !== Product::PUBLISHED || $type->status !== TicketType::ACTIVE) {
                $this->fail("$label tidak tersedia untuk dibeli.");
            }
            if ($type->product->end_at && $type->product->end_at->isPast()) {
                $this->fail("Acara $label sudah berakhir.");
            }
            if (! $type->inSalePeriod()) {
                $this->fail("Penjualan $label sedang tidak dibuka.");
            }
            if ($qty < $type->min_per_order) {
                $this->fail("Minimal pembelian $label adalah {$type->min_per_order} tiket.");
            }
            if ($qty > $type->max_per_order) {
                $this->fail("Maksimal pembelian $label adalah {$type->max_per_order} tiket per pesanan.");
            }
            if ($qty > $type->available()) {
                $this->fail($type->available() > 0
                    ? "Sisa tiket $label hanya {$type->available()}."
                    : "Tiket $label sudah habis.");
            }

            $unit = round((float) $type->price, 2);
            $lineTotal = round($unit * $qty, 2);
            $subtotal += $lineTotal;

            $lines[] = [
                'ticket_type' => $type,
                'quantity' => $qty,
                'unit_price' => $unit,
                'line_total' => $lineTotal,
            ];
        }

        $subtotal = round($subtotal, 2);
        $fee = round((float) Setting::get('admin_fee', 0), 2);

        return [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'admin_fee' => $fee,
            'total' => round($subtotal + $fee, 2),
        ];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['items' => $message]);
    }

    private function newOrderNumber(): string
    {
        do {
            $number = 'TXR-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
