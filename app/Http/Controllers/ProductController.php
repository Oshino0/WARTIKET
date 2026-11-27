<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $categorySlug = $request->query('category');

        $products = Product::visible()->upcoming()
            ->with(['category', 'ticketTypes'])
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%' . $search . '%'))
            ->when($categorySlug, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $categorySlug)))
            ->orderByRaw('start_at IS NULL')
            ->orderBy('start_at')
            ->paginate(12)
            ->withQueryString();

        return view('customer.explore', [
            'products' => $products,
            'categories' => Category::active()->orderBy('name')->get(),
            'search' => $search,
            'activeCategory' => $categorySlug,
        ]);
    }

    public function show(Request $request, string $identifier)
    {
        $product = Product::findByUrlIdentifier($identifier);

        if ((string) $product->getKey() !== $identifier) {
            return redirect()->route('products.show', $product, 301);
        }

        abort_unless(in_array($product->status, [Product::PUBLISHED, Product::CLOSED], true), 404);

        $product->load([
            'category',
            'ticketTypes' => fn ($q) => $q->where('status', 'active')->orderBy('price'),
        ]);

        return view('customer.product', ['product' => $product]);
    }
}
