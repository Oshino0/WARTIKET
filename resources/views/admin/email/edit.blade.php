@extends('layouts.admin')
@section('title', 'Pengaturan email')
@section('content')
  <h1 class="text-2xl font-bold">Pengaturan email tiket</h1>
  <p class="mt-1 text-sm text-gray-500">Pengaturan umum untuk email e-tiket. Gambar tiket berisi nama acara, tipe tiket, jadwal, lokasi, nama pembeli, nomor pesanan, kode tiket, dan QR code. Berlaku untuk semua acara, kecuali acara yang punya pengaturan sendiri (menu Produk > Kelola > Email tiket). Perubahan berlaku untuk email berikutnya, termasuk kirim ulang.</p>

  <div class="mt-6 grid gap-8 xl:grid-cols-[minmax(0,1fr)_28rem]">
    <div>
      <form action="{{ route('admin.email.update') }}" method="POST" enctype="multipart/form-data" class="space-y-5 rounded-2xl border border-violet-100 bg-white p-6">
        @csrf @method('PUT')

        @include('admin.email._text-fields')

        <fieldset class="space-y-4 border-t border-violet-100 pt-5">
          <legend class="text-sm font-bold">Gambar latar tiket</legend>

          @if ($template['qr_background'])
            <div class="flex items-center gap-4">
              <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($template['qr_background']) }}" alt="Latar QR saat ini" class="h-24 w-24 rounded-xl border border-violet-100 object-cover">
              <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remove_qr_background" value="1" class="rounded border-gray-300 text-brand focus:ring-brand">
                Hapus latar ini (QR tampil polos)
              </label>
            </div>
          @endif

          <x-field :label="$template['qr_background'] ? 'Ganti gambar latar' : 'Unggah gambar latar'" name="qr_background" type="file"
                   accept="image/png,image/jpeg,image/webp"
                   hint="JPG, PNG, atau WebP. Minimal 300×300 px, maksimal 3 MB. Gambar dipotong mengikuti bentuk tiket dan digelapkan otomatis agar tulisan terbaca." />
        </fieldset>

        <div class="flex flex-wrap gap-3">
          <button class="btn btn-primary">Simpan pengaturan</button>
        </div>
      </form>

      <form action="{{ route('admin.email.reset') }}" method="POST" class="mt-4" onsubmit="return confirm('Kembalikan semua pengaturan email ke awal? Gambar latar juga dihapus.')">
        @csrf
        <button class="btn btn-danger">Kembalikan ke pengaturan awal</button>
      </form>
    </div>

    <aside class="xl:sticky xl:top-6 xl:self-start">
      <h2 class="mb-2 text-sm font-bold">Pratinjau (pengaturan yang sudah disimpan)</h2>
      <iframe src="{{ route('admin.email.preview') }}" title="Pratinjau email e-tiket" class="h-[44rem] w-full rounded-2xl border border-violet-100 bg-white"></iframe>
    </aside>
  </div>
@endsection
