@extends('layouts.app')
@section('title', 'Profil')
@section('content')
  <div class="mx-auto max-w-md">
    <h1 class="text-2xl font-bold">Profil</h1>

    <form action="{{ route('profile.update') }}" method="POST" class="mt-6 space-y-4">
      @csrf @method('PUT')
      <x-field label="Nama lengkap" name="name" :value="$user->name" required />
      <div>
        <label class="mb-1 block text-sm font-medium">Email</label>
        <input value="{{ $user->email }}" class="input bg-gray-50 text-gray-500" disabled>
      </div>
      <x-field label="Nomor HP" name="phone" type="tel" :value="$user->phone" />

      <fieldset class="space-y-4 border-t border-violet-100 pt-4">
        <legend class="text-sm font-semibold">Ganti kata sandi (opsional)</legend>
        <x-field label="Kata sandi saat ini" name="current_password" type="password" autocomplete="current-password" />
        <x-field label="Kata sandi baru" name="password" type="password" hint="Minimal 8 karakter." autocomplete="new-password" />
        <x-field label="Ulangi kata sandi baru" name="password_confirmation" type="password" autocomplete="new-password" />
      </fieldset>

      <button class="btn btn-primary">Simpan profil</button>
    </form>
  </div>
@endsection
