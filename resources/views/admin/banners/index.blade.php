@extends('layouts.admin')
@section('title', 'Banner')
@section('content')
  <h1 class="text-2xl font-bold">Banner homepage</h1>

  @include('admin.banners._form', ['banner' => null, 'submitLabel' => 'Tambah banner'])

  <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    @forelse ($banners as $banner)
      <div class="overflow-hidden rounded-2xl border border-violet-100 bg-white">
        <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}" class="aspect-[3/1] w-full object-cover {{ $banner->is_active ? '' : 'opacity-40' }}" style="object-position: {{ $banner->image_position_x }}% {{ $banner->image_position_y }}%;">
        <div class="p-4">
          <p class="font-semibold">{{ $banner->title }}</p>
          <p class="text-xs text-gray-500">Urutan {{ $banner->sort_order }} • {{ $banner->is_active ? 'Aktif' : 'Nonaktif' }}</p>
          <div class="mt-3 flex gap-2">
            <a href="{{ route('admin.banners.edit', $banner) }}" class="btn btn-ghost !py-1">Ubah</a>
            <form action="{{ route('admin.banners.toggle', $banner) }}" method="POST">@csrf @method('PATCH')
              <button class="btn btn-ghost !py-1">{{ $banner->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
            <form action="{{ route('admin.banners.destroy', $banner) }}" method="POST" onsubmit="return confirm('Hapus banner ini?')">@csrf @method('DELETE')
              <button class="btn btn-danger !py-1">Hapus</button></form>
          </div>
        </div>
      </div>
    @empty
      <p class="text-gray-500">Belum ada banner.</p>
    @endforelse
  </div>
@endsection
