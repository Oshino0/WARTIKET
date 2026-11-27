@extends('layouts.admin')
@section('title', $category->exists ? 'Ubah kategori' : 'Tambah kategori')
@section('content')
  <h1 class="text-2xl font-bold">{{ $category->exists ? 'Ubah kategori' : 'Tambah kategori' }}</h1>

  <form action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}"
        method="POST" enctype="multipart/form-data" class="mt-6 max-w-xl space-y-4 rounded-2xl border border-violet-100 bg-white p-6">
    @csrf
    @if ($category->exists) @method('PUT') @endif

    <x-field label="Nama kategori" name="name" :value="$category->name" required />

    <div>
      <label for="description" class="mb-1 block text-sm font-medium">Deskripsi</label>
      <textarea id="description" name="description" rows="3" class="input">{{ old('description', $category->description) }}</textarea>
      @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
      @if ($category->image_url)
        <img src="{{ $category->image_url }}" alt="" class="mb-2 h-20 rounded-xl object-cover">
      @endif
      <x-field label="Gambar" name="image" type="file" accept="image/*" hint="JPG atau PNG, maksimal 2 MB." />
    </div>

    <label class="flex items-center gap-2 text-sm">
      <input type="hidden" name="is_active" value="0">
      <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-brand focus:ring-brand" @checked(old('is_active', $category->is_active))>
      Tampilkan kategori di website
    </label>

    <div class="flex gap-3">
      <button class="btn btn-primary">Simpan kategori</button>
      <a href="{{ route('admin.categories.index') }}" class="btn btn-ghost">Batal</a>
    </div>
  </form>
@endsection
