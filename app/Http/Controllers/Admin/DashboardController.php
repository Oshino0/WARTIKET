<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\TicketType;

class DashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard', [
            'stats' => [
                'revenue' => Order::whereIn('status', [Order::PAID, Order::SENT])->sum('total'),
                'orders' => Order::count(),
                'pending' => Order::pending()->count(),
                'pending_verification' => Order::where('status', Order::PENDING_VERIFICATION)->count(),
                'tickets_sold' => TicketType::withTrashed()->sum('sold'),
                'reserved' => TicketType::withTrashed()->sum('reserved'),
                'products' => Product::where('status', Product::PUBLISHED)->count(),
                'review' => Payment::where('needs_review', true)->count(),
            ],
            'recent' => Order::with('user')->latest('id')->limit(8)->get(),
        ]);
    }
}
