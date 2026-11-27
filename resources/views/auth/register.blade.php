@extends('layouts.app')
@section('title', 'Daftar')
@section('content')
  <div class="mx-auto max-w-md">
    <h1 class="text-2xl font-bold">Buat akun</h1>
    <p class="mt-1 text-sm text-gray-500">Akun dibutuhkan agar pesanan dan e-ticket tersimpan. Sudah punya akun? <a href="{{ route('login') }}" class="font-semibold text-brand">Masuk</a></p>

    <form action="{{ url('/register') }}" method="POST" class="mt-6 space-y-4">
      @csrf
      <x-field label="Nama lengkap" name="name" autocomplete="name" required autofocus />
      <x-field label="Email" name="email" type="email" autocomplete="email" required />
      <x-field label="Nomor HP (opsional)" name="phone" type="tel" autocomplete="tel" />
      <x-field label="Kata sandi" name="password" type="password" hint="Minimal 8 karakter." autocomplete="new-password" required />
      <x-field label="Ulangi kata sandi" name="password_confirmation" type="password" autocomplete="new-password" required />
      <button class="btn btn-primary w-full">Daftar</button>
    </form>
  </div>
@endsection
