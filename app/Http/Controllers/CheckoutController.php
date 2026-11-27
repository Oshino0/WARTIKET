<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(private OrderService $orders) {}

    /**
     * Step 1: the ticket picker sends the chosen amounts here as ?items[ticket_type_id]=qty.
     * Shows a server-calculated summary and the buyer form. Nothing is saved or reserved yet.
     */
    public function review(Request $request): View|RedirectResponse
    {
        $quantities = $this->selection($request->query('items'));

        if ($quantities === []) {
            return redirect()->route('explore')->with('status', 'Pilih tiket dulu sebelum checkout.');
        }

        // Throws a validation error (shown on the page the visitor came from) when stock,
        // sale period or per-order limits do not allow this selection.
        $quote = $this->orders->quote($quantities);

        return view('customer.checkout', [
            'quote' => $quote,
            'quantities' => $quantities,
            'product' => $quote['lines'][0]['ticket_type']->product,
        ]);
    }

    /** Step 2: re-validate under lock, create the order and reserve stock. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'buyer_name' => ['required', 'string', 'min:3', 'max:100'],
            'buyer_email' => ['required', 'email', 'max:150'],
            'buyer_phone' => [
                'required',
                'string',
                'max:30',
                'regex:/^\+?[0-9][0-9\s().-]{7,29}$/',
                function (string $attribute, string $value, \Closure $fail): void {
                    $digits = preg_replace('/\D/', '', $value);
                    if (strlen($digits) < 8 || strlen($digits) > 15) {
                        $fail('Nomor WhatsApp harus berisi 8 sampai 15 digit.');
                    }
                },
            ],
            'items' => ['required', 'array'],
            'items.*' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $order = $this->orders->createGuest(
            $data['buyer_name'],
            $data['buyer_email'],
            $data['buyer_phone'],
            $this->selection($data['items']),
        );

        $request->session()->put('guest_order_number', $order->order_number);

        return redirect()->route('checkout.confirmation', $order->order_number);
    }

    public function confirmation(Request $request, string $orderNumber): View
    {
        abort_unless($request->session()->get('guest_order_number') === $orderNumber, 404);

        $order = Order::with(['items.tickets'])
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        return view('customer.checkout-confirmation', compact('order'));
    }

    /** @return array<int,int> ticket_type_id => quantity, only quantities above zero */
    private function selection(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $clean = [];
        foreach ($items as $id => $quantity) {
            $quantity = (int) $quantity;
            if ((int) $id > 0 && $quantity > 0) {
                $clean[(int) $id] = min($quantity, 100);
            }
        }

        ksort($clean);

        return $clean;
    }
}
