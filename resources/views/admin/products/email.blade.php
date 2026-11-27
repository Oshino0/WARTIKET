@extends('layouts.admin')
@section('title', 'Email tiket – '.$product->name)
@section('content')
  <a href="{{ route('admin.products.edit', $product) }}" class="text-sm font-semibold text-brand">← {{ $product->name }}</a>
  <h1 class="mt-2 text-2xl font-bold">Email tiket untuk acara ini</h1>
  <p class="mt-1 text-sm text-gray-500">
    Atur tampilan email e-tiket khusus untuk <strong>{{ $product->name }}</strong>. Acara lain tetap memakai
    <a href="{{ route('admin.email.edit') }}" class="font-semibold text-brand">pengaturan umum</a>.
  </p>

  <div class="mt-6 grid gap-8 xl:grid-cols-[minmax(0,1fr)_28rem]">
    <div>
      <form action="{{ route('admin.products.email.update', $product) }}" method="POST" enctype="multipart/form-data" class="space-y-5 rounded-2xl border border-violet-100 bg-white p-6">
        @csrf @method('PUT')

        <label class="flex items-start gap-3 rounded-xl border border-violet-200 bg-brand-surface p-4 text-sm">
          <input type="hidden" name="enabled" value="0">
          <input type="checkbox" name="enabled" value="1" class="mt-0.5 rounded border-gray-300 text-brand focus:ring-brand" @checked(old('enabled', $enabled))>
          <span>
            <strong>Pakai pengaturan khusus untuk acara ini</strong><br>
            <span class="text-gray-600">Jika tidak dicentang, tiket acara ini memakai pengaturan email umum. Isian di bawah tetap disimpan.</span>
          </span>
        </label>

        @include('admin.email._text-fields')

        <fieldset class="space-y-3 border-t border-violet-100 pt-5">
          <legend class="text-sm font-bold">Gambar header e-tiket</legend>

          @php $currentMode = old('qr_background_mode', $mode); @endphp
          <label class="flex items-start gap-2 text-sm">
            <input type="radio" name="qr_background_mode" value="global" class="mt-0.5 border-gray-300 text-brand focus:ring-brand" @checked($currentMode === 'global')>
            <span>Pakai gambar header dari pengaturan umum @unless ($globalBackground) <span class="text-gray-500">(saat ini belum ada, pakai warna acara)</span> @endunless</span>
          </label>
          <label class="flex items-start gap-2 text-sm">
            <input type="radio" name="qr_background_mode" value="custom" class="mt-0.5 border-gray-300 text-brand focus:ring-brand" @checked($currentMode === 'custom')>
            <span>Pakai gambar header khusus acara ini</span>
          </label>
          <label class="flex items-start gap-2 text-sm">
            <input type="radio" name="qr_background_mode" value="none" class="mt-0.5 border-gray-300 text-brand focus:ring-brand" @checked($currentMode === 'none')>
            <span>Tanpa gambar header</span>
          </label>
          @error('qr_background_mode')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

          @if ($customBackground)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($customBackground) }}" alt="Gambar header acara ini" class="h-24 w-24 rounded-xl border border-violet-100 object-cover">
          @endif

          <x-field :label="$customBackground ? 'Ganti gambar header acara ini' : 'Unggah gambar header acara ini'" name="qr_background" type="file"
                   accept="image/png,image/jpeg,image/webp"
                   hint="JPG, PNG, atau WebP. Minimal 300×300 px, maksimal 3 MB. Gambar potret ditampilkan utuh di panel tiket; gambar persegi atau lanskap menjadi banner. Mengunggahnya otomatis memilih opsi khusus acara." />
        </fieldset>

        <button class="btn btn-primary">Simpan pengaturan acara</button>
      </form>

      <form action="{{ route('admin.products.email.reset', $product) }}" method="POST" class="mt-4" onsubmit="return confirm('Hapus pengaturan khusus acara ini? Acara kembali memakai pengaturan umum dan gambar header khusus dihapus.')">
        @csrf
        <button class="btn btn-danger">Hapus pengaturan khusus</button>
      </form>
    </div>

    <aside class="xl:sticky xl:top-6 xl:self-start">
      <h2 class="mb-2 text-sm font-bold">Pratinjau (yang berlaku saat ini untuk acara ini)</h2>
      <iframe src="{{ route('admin.products.email.preview', $product) }}" title="Pratinjau email e-tiket {{ $product->name }}" class="h-[44rem] w-full rounded-2xl border border-violet-100 bg-white"></iframe>
      <p class="mt-2 text-xs text-gray-500">Simpan dulu untuk melihat perubahan. Jika pengaturan khusus belum dicentang, pratinjau menampilkan pengaturan umum.</p>
    </aside>
  </div>
@endsection
