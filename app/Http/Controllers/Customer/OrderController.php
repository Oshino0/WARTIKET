<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = $request->user()->orders()->with('items')->latest('id')->paginate(10);

        return view('customer.orders.index', compact('orders'));
    }

    public function show(Request $request, string $orderNumber)
    {
        // Scoped to the signed-in customer: other people's orders are simply not found.
        $order = $request->user()->orders()->with(['items', 'payments'])->where('order_number', $orderNumber)->firstOrFail();

        return view('customer.orders.show', compact('order'));
    }

    public function cancel(Request $request, string $orderNumber, OrderService $orders)
    {
        $order = $request->user()->orders()->where('order_number', $orderNumber)->firstOrFail();

        if (! $orders->release($order)) {
            return back()->withErrors(['order' => 'Pesanan ini sudah tidak bisa dibatalkan.']);
        }

        return redirect()->route('orders.show', $order->order_number)->with('status', 'Pesanan dibatalkan.');
    }
}
