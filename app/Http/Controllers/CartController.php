<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\TicketType;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function __construct(private OrderService $orders)
    {
    }

    public function index(Request $request)
    {
        $quantities = collect($request->session()->get('cart', []))
            ->map(fn ($quantity) => (int) $quantity)
            ->filter(fn ($quantity) => $quantity > 0)
            ->all();

        $types = TicketType::withTrashed()
            ->with('product')
            ->whereIn('id', array_keys($quantities))
            ->get()
            ->keyBy('id');

        $items = [];
        $canCheckout = $quantities !== [];

        foreach ($quantities as $id => $quantity) {
            $type = $types->get($id);
            $product = $type?->product;
            $issue = null;

            if (! $type || $type->trashed() || ! $product || $product->trashed()) {
                $issue = 'Tiket ini sudah tidak tersedia. Hapus dari keranjang untuk melanjutkan.';
            } elseif ($product->status !== Product::PUBLISHED || $type->status !== TicketType::ACTIVE) {
                $issue = 'Penjualan tiket ini sudah ditutup.';
            } elseif ($product->end_at && $product->end_at->isPast()) {
                $issue = 'Acara ini sudah berakhir.';
            } elseif (! $type->inSalePeriod()) {
                $issue = 'Tiket ini sedang tidak dalam periode penjualan.';
            } elseif ($quantity < $type->min_per_order) {
                $issue = "Minimal pembelian adalah {$type->min_per_order} tiket.";
            } elseif ($quantity > $type->max_per_order) {
                $issue = "Maksimal pembelian adalah {$type->max_per_order} tiket.";
            } elseif ($quantity > $type->available()) {
                $issue = $type->available() > 0
                    ? "Stok tersisa {$type->available()} tiket. Sesuaikan jumlah atau hapus tiket."
                    : 'Tiket ini sudah habis. Hapus dari keranjang untuk melanjutkan.';
            }

            if ($issue) {
                $canCheckout = false;
            }

            $canAdjust = $type
                && ! $type->trashed()
                && $product
                && ! $product->trashed()
                && $product->status === Product::PUBLISHED
                && $type->status === TicketType::ACTIVE
                && $type->inSalePeriod()
                && (! $product->end_at || $product->end_at->isFuture())
                && $type->available() > 0
                && min($type->max_per_order, $type->available()) >= $type->min_per_order;

            $items[] = [
                'id' => $id,
                'type' => $type,
                'product' => $product,
                'quantity' => $quantity,
                'issue' => $issue,
                'can_adjust' => $canAdjust,
            ];
        }

        if (! $request->user() && $quantities !== []) {
            $request->session()->put('url.intended', route('cart.index'));
        }

        $quote = null;
        if ($canCheckout) {
            $quote = $this->orders->quote($quantities);
        }

        return view('customer.cart', [
            'items' => $items,
            'quote' => $quote,
            'canCheckout' => $canCheckout,
        ]);
    }

    public function store(Request $request, string $identifier)
    {
        $product = Product::findByUrlIdentifier($identifier);

        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $selected = collect($data['items'])
            ->map(fn ($quantity) => (int) $quantity)
            ->filter(fn ($quantity) => $quantity > 0)
            ->all();

        if ($selected === []) {
            throw ValidationException::withMessages(['items' => 'Pilih minimal satu tiket sebelum menambahkannya ke keranjang.']);
        }

        $matchingTypes = TicketType::where('product_id', $product->id)
            ->whereIn('id', array_keys($selected))
            ->count();

        if ($matchingTypes !== count($selected)) {
            throw ValidationException::withMessages(['items' => 'Pilihan tiket tidak sesuai dengan acara ini. Muat ulang halaman dan coba lagi.']);
        }

        $cart = collect($request->session()->get('cart', []))
            ->map(fn ($quantity) => (int) $quantity)
            ->filter(fn ($quantity) => $quantity > 0)
            ->all();

        foreach ($selected as $id => $quantity) {
            $newQuantity = ($cart[$id] ?? 0) + $quantity;
            $this->orders->quote([$id => $newQuantity]);
            $cart[$id] = $newQuantity;
        }

        $request->session()->put('cart', $cart);

        return redirect()->route('cart.index')->with('status', 'Tiket berhasil ditambahkan ke keranjang.');
    }

    public function update(Request $request, int $ticketType)
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $cart = $request->session()->get('cart', []);
        $quantity = (int) $data['quantity'];

        if ($quantity === 0) {
            unset($cart[$ticketType]);
        } else {
            $this->orders->quote([$ticketType => $quantity]);
            $cart[$ticketType] = $quantity;
        }

        $request->session()->put('cart', $cart);

        return redirect()->route('cart.index')->with('status', 'Keranjang berhasil diperbarui.');
    }

    public function destroy(Request $request, int $ticketType)
    {
        $cart = $request->session()->get('cart', []);
        unset($cart[$ticketType]);
        $request->session()->put('cart', $cart);

        return redirect()->route('cart.index')->with('status', 'Tiket dihapus dari keranjang.');
    }
}
