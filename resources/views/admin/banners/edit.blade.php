@extends('layouts.admin')
@section('title', 'Ubah banner')
@section('content')
  <div class="max-w-2xl">
    <h1 class="text-2xl font-bold">Ubah banner</h1>
    <p class="mt-1 text-sm text-gray-600">Atur detail, posisi gambar, atau urutan tampil banner.</p>
    @include('admin.banners._form', ['banner' => $banner, 'submitLabel' => 'Simpan perubahan'])
  </div>
@endsection
