<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $orders = Order::with(['user', 'items'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($matches) use ($search): void {
                    $matches->where('order_number', 'like', "%$search%")
                        ->orWhere('buyer_name', 'like', "%$search%")
                        ->orWhere('buyer_email', 'like', "%$search%")
                        ->orWhere('buyer_phone', 'like', "%$search%")
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%$search%")
                            ->orWhere('email', 'like', "%$search%"));
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.orders.index', compact('orders', 'search'));
    }

    public function show(Order $order): View
    {
        $order->load(['user', 'items.tickets', 'items.ticketType.product']);

        return view('admin.orders.show', compact('order'));
    }

    public function verify(Order $order, OrderVerificationService $verification): RedirectResponse
    {
        $verification->confirmAndSend($order);

        return redirect()->route('admin.orders.show', $order)->with('status', 'Tiket dibuat dan email berhasil dikirim.');
    }

    public function resend(Order $order, OrderVerificationService $verification): RedirectResponse
    {
        $verification->resend($order);

        return redirect()->route('admin.orders.show', $order)->with('status', 'E-tiket berhasil dikirim ulang.');
    }

    public function reject(Order $order, OrderVerificationService $verification): RedirectResponse
    {
        $verification->reject($order);

        return redirect()->route('admin.orders.show', $order)->with('status', 'Pesanan ditolak dan stok tiket dikembalikan.');
    }
}
