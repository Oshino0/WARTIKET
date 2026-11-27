@extends('layouts.admin')
@section('title', 'Kategori')
@section('content')
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold">Kategori</h1>
    <a href="{{ route('admin.categories.create') }}" class="btn btn-primary">Tambah kategori</a>
  </div>

  <div class="mt-6 overflow-x-auto rounded-2xl border border-violet-100 bg-white">
    <table class="w-full min-w-[32rem]">
      <thead class="bg-brand-surface"><tr><th class="th">Nama</th><th class="th">Slug</th><th class="th text-right">Produk</th><th class="th">Status</th><th class="th"></th></tr></thead>
      <tbody class="divide-y divide-violet-100">
        @forelse ($categories as $category)
          <tr>
            <td class="td font-medium">{{ $category->name }}</td>
            <td class="td text-gray-500">{{ $category->slug }}</td>
            <td class="td text-right">{{ $category->products_count }}</td>
            <td class="td">{{ $category->is_active ? 'Aktif' : 'Nonaktif' }}</td>
            <td class="td">
              <div class="flex justify-end gap-2">
                <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-ghost !py-1">Ubah</a>
                <form action="{{ route('admin.categories.destroy', $category) }}" method="POST" onsubmit="return confirm('Hapus kategori {{ addslashes($category->name) }}?')">
                  @csrf @method('DELETE')
                  <button class="btn btn-danger !py-1">Hapus</button>
                </form>
              </div>
            </td>
          </tr>
        @empty
          <tr><td class="td text-center text-gray-500" colspan="5">Belum ada kategori. Tambahkan kategori pertama untuk mulai membuat produk.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="mt-4">{{ $categories->links() }}</div>
@endsection
