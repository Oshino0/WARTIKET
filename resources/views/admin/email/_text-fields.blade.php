{{-- Shared by the global and per-event e-mail forms. Needs: $template, $placeholders --}}
<x-field label="Subjek email" name="subject" :value="$template['subject']" required />
<x-field label="Judul di dalam email" name="heading" :value="$template['heading']" required />
<x-field label="Sapaan" name="greeting" :value="$template['greeting']" required />

<div>
  <label for="intro" class="mb-1 block text-sm font-medium">Pesan tambahan (opsional)</label>
  <textarea id="intro" name="intro" rows="3" class="input">{{ old('intro', $template['intro']) }}</textarea>
  @error('intro')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>

<div>
  <label for="footer" class="mb-1 block text-sm font-medium">Catatan di bagian bawah (opsional)</label>
  <textarea id="footer" name="footer" rows="3" class="input">{{ old('footer', $template['footer']) }}</textarea>
  @error('footer')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>

<p class="rounded-xl bg-brand-surface px-4 py-3 text-xs text-gray-600">
  Kata pengganti yang bisa dipakai di subjek, judul, sapaan, pesan, dan catatan:
  @foreach ($placeholders as $code => $label)
    <code class="rounded bg-white px-1.5 py-0.5 font-semibold text-brand">{{ $code }}</code> {{ $label }}@if (! $loop->last), @endif
  @endforeach
</p>

<div>
  <label for="accent_color" class="mb-1 block text-sm font-medium">Warna judul dan bingkai tiket</label>
  <input id="accent_color" name="accent_color" type="color" value="{{ old('accent_color', $template['accent_color']) }}"
         class="h-10 w-20 cursor-pointer rounded-lg border border-gray-300 bg-white p-1">
  @error('accent_color')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>

<div>
  <label for="qr_scale" class="mb-1 block text-sm font-medium">Ukuran QR di atas latar</label>
  <select id="qr_scale" name="qr_scale" class="input max-w-xs">
    @foreach ([45 => 'Kecil (45%)', 55 => 'Sedang (55%)', 65 => 'Besar (65%)', 70 => 'Sangat besar (70%)'] as $value => $label)
      <option value="{{ $value }}" @selected((int) old('qr_scale', $template['qr_scale']) === $value)>{{ $label }}</option>
    @endforeach
  </select>
  @error('qr_scale')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
  <p class="mt-1 text-xs text-gray-500">QR tetap memiliki bingkai putih agar mudah dipindai. Coba scan hasilnya dari layar HP sebelum dipakai.</p>
</div>
