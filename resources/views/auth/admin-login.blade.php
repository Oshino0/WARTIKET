<!DOCTYPE html>
<html lang="id">
<head>@include('layouts.head')</head>
<body class="flex min-h-screen items-center justify-center bg-brand-surface px-4 font-sans text-brand-ink antialiased">
  <main class="w-full max-w-md rounded-2xl border border-violet-100 bg-white p-6 shadow-sm sm:p-8">
    <a href="{{ route('home') }}" class="text-xl font-extrabold tracking-tight text-brand">WARTIKET</a>
    <h1 class="mt-6 text-2xl font-bold">Masuk admin</h1>
    <p class="mt-1 text-sm text-gray-500">Gunakan akun admin untuk membuka panel pengelolaan.</p>

    <form action="{{ route('admin.authenticate') }}" method="POST" class="mt-6 space-y-4">
      @csrf
      <x-field label="Email" name="email" type="email" autocomplete="username" required autofocus />
      <x-field label="Kata sandi" name="password" type="password" autocomplete="current-password" required />
      <button class="btn btn-primary w-full">Masuk</button>
    </form>
  </main>
</body>
</html>
