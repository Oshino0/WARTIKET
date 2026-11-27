<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $products = Product::with('category')
            ->withCount('ticketTypes')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%$search%"))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.products.index', compact('products', 'search'));
    }

    public function create()
    {
        return view('admin.products.form', [
            'product' => new Product(['status' => Product::DRAFT]),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = Product::uniqueSlug($data['name']);
        $data['status'] = Product::DRAFT; // publish after at least one ticket type exists

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($data);
        AuditLog::record('product.created', $product);

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('status', 'Produk dibuat sebagai draft. Tambahkan tipe tiket, lalu publikasikan.');
    }

    public function edit(Product $product)
    {
        return view('admin.products.form', [
            'product' => $product->load('ticketTypes'),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validated($request);

        if ($data['status'] !== Product::DRAFT && ! $product->ticketTypes()->where('status', 'active')->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Tambahkan minimal satu tipe tiket aktif sebelum mempublikasikan produk.',
            ]);
        }

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);
        AuditLog::record('product.updated', $product, ['status' => $product->status]);

        return redirect()->route('admin.products.edit', $product)->with('status', 'Produk disimpan.');
    }

    public function destroy(Product $product)
    {
        // Soft delete: order history keeps its snapshots and references.
        $product->delete();
        AuditLog::record('product.deleted', $product);

        return redirect()->route('admin.products.index')->with('status', 'Produk dihapus.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:10000'],
            'image' => ['nullable', 'image', 'max:2048'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'status' => ['nullable', Rule::in(array_keys(Product::STATUSES))],
            'is_featured' => ['nullable', 'boolean'],
            'meta_keys' => ['nullable', 'array', 'max:30'],
            'meta_keys.*' => ['nullable', 'string', 'max:60'],
            'meta_values' => ['nullable', 'array', 'max:30'],
            'meta_values.*' => ['nullable', 'string', 'max:500'],
        ]);

        $metadata = [];
        foreach (($data['meta_keys'] ?? []) as $i => $key) {
            $key = trim((string) $key);
            if ($key !== '') {
                $metadata[$key] = trim((string) ($data['meta_values'][$i] ?? ''));
            }
        }

        unset($data['image'], $data['meta_keys'], $data['meta_values']);

        $data['metadata'] = $metadata ?: null;
        $data['is_featured'] = $request->boolean('is_featured');
        $data['status'] = $data['status'] ?? Product::DRAFT;

        return $data;
    }
}
