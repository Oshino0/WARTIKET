@extends('layouts.app')
@section('title', 'Masuk')
@section('content')
  <div class="mx-auto max-w-md">
    <h1 class="text-2xl font-bold">Masuk ke WARTIKET</h1>
    <p class="mt-1 text-sm text-gray-500">Masuk dengan akun yang sudah terdaftar.</p>

    <form action="{{ url('/login') }}" method="POST" class="mt-6 space-y-4">
      @csrf
      <x-field label="Email" name="email" type="email" autocomplete="email" required autofocus />
      <x-field label="Kata sandi" name="password" type="password" autocomplete="current-password" required />
      <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1" class="rounded border-gray-300 text-brand focus:ring-brand"> Ingat saya</label>
      <button class="btn btn-primary w-full">Masuk</button>
    </form>
  </div>
@endsection
