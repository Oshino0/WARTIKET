<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;

class HomeController extends Controller
{
    public function index()
    {
        $visible = fn () => Product::visible()->upcoming()->with(['category', 'ticketTypes']);

        return view('customer.home', [
            'banners' => Banner::where('is_active', true)->orderBy('sort_order')->orderBy('id')->limit(3)->get(),
            'categories' => Category::active()
                ->withCount(['products' => fn ($q) => $q->visible()->upcoming()])
                ->orderBy('name')->get(),
            'featured' => $visible()->where('is_featured', true)->latest('id')->limit(6)->get(),
            'latest' => $visible()->latest('id')->limit(8)->get(),
        ]);
    }
}
