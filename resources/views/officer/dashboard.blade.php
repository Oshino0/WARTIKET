@extends('layouts.app')
@section('title', 'Dashboard petugas')
@section('content')
  <div class="mx-auto max-w-lg text-center">
    <h1 class="text-2xl font-bold">Halo, {{ auth()->user()->name }}</h1>
    <p class="mt-3 text-gray-600">Pemindai QR dan check-in tiket belum tersedia di versi ini. Akun petugasmu sudah aktif dan akan langsung bisa dipakai saat fitur itu dirilis.</p>
  </div>
@endsection
