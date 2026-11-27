@extends('layouts.app')
@section('title', 'Jelajahi tiket')
@section('content')
  <h1 class="text-2xl font-bold">Jelajahi tiket</h1>

  <form action="{{ route('explore') }}" method="GET" aria-label="Filter tiket" class="mt-3 flex flex-col items-start gap-3 sm:flex-row sm:items-end">
    @if ($search !== '') <input type="hidden" name="q" value="{{ $search }}"> @endif
    <div class="w-full sm:max-w-xs">
      <label for="category" class="mb-1 block text-sm font-medium text-gray-700">Kategori acara</label>
      <select id="category" name="category" class="input">
        <option value="">Semua kategori</option>
        @foreach ($categories as $category)
          <option value="{{ $category->slug }}" @selected($activeCategory === $category->slug)>{{ $category->name }}</option>
        @endforeach
      </select>
    </div>
    <button class="btn btn-primary">Filter</button>
    @if ($activeCategory)
      <a href="{{ route('explore', $search !== '' ? ['q' => $search] : []) }}" class="btn btn-ghost">Hapus filter</a>
    @endif
  </form>

  @if ($products->isEmpty())
    <p class="mt-8 rounded-2xl border border-dashed border-violet-200 p-10 text-center text-gray-500">
      Tidak ada tiket yang cocok. Coba kata kunci lain di pencarian atas atau pilih kategori lain.
    </p>
  @else
    <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
      @foreach ($products as $product) <x-product-card :product="$product" /> @endforeach
    </div>
    <div class="mt-8">{{ $products->links() }}</div>
  @endif
@endsection
