@php
  $isEditing = $banner !== null;
  $positionX = old('image_position_x', $isEditing ? $banner->image_position_x : 50);
  $positionY = old('image_position_y', $isEditing ? $banner->image_position_y : 50);
@endphp

<form action="{{ $isEditing ? route('admin.banners.update', $banner) : route('admin.banners.store') }}"
      method="POST" enctype="multipart/form-data"
      class="mt-6 max-w-xl space-y-4 rounded-2xl border border-violet-100 bg-white p-6">
  @csrf
  @if ($isEditing)
    @method('PUT')
  @endif
  <x-field label="Judul" name="title" :value="$isEditing ? $banner->title : ''" required />
  <x-field label="Gambar" name="image" type="file" accept="image/*"
           hint="{{ $isEditing ? 'Pilih file hanya jika ingin mengganti gambar. Maksimal 3 MB.' : 'Disarankan rasio 3:1, maksimal 3 MB.' }}"
           :required="! $isEditing" />
  <x-field label="Tautan (opsional)" name="link_url" type="url" placeholder="https://"
           :value="$isEditing ? $banner->link_url : ''" />
  <x-field label="Urutan" name="sort_order" type="number" min="0" max="999"
           :value="$isEditing ? $banner->sort_order : 0"
           hint="Angka lebih kecil tampil lebih dulu. Jika sama, ID banner yang lebih kecil tampil lebih dulu. Homepage menampilkan 3 banner aktif." />

  <div data-banner-crop class="space-y-3">
    <p class="text-sm font-medium">Atur bagian gambar yang terlihat</p>
    <div class="relative aspect-[3/1] touch-none overflow-hidden rounded-xl border border-violet-200 bg-gray-100"
         data-crop-frame>
      <img data-crop-preview src="{{ $isEditing ? $banner->image_url : '' }}"
           alt="Pratinjau crop banner"
           class="h-full w-full select-none object-cover"
           style="object-position: {{ $positionX }}% {{ $positionY }}%; cursor: grab;"
           @if (! $isEditing) hidden @endif draggable="false">
      <div data-crop-empty class="absolute inset-0 flex items-center justify-center px-4 text-center text-sm text-gray-500"
           @if ($isEditing) hidden @endif>
        Pilih gambar untuk melihat pratinjau. Geser gambar untuk memilih bagian yang tampil.
      </div>
    </div>
    <div class="grid gap-3 sm:grid-cols-2">
      <label class="block text-xs text-gray-600">
        Posisi horizontal
        <input type="range" name="image_position_x" min="0" max="100" step="1" value="{{ $positionX }}"
               class="mt-1 w-full accent-violet-700" data-crop-x>
      </label>
      <label class="block text-xs text-gray-600">
        Posisi vertikal
        <input type="range" name="image_position_y" min="0" max="100" step="1" value="{{ $positionY }}"
               class="mt-1 w-full accent-violet-700" data-crop-y>
      </label>
    </div>
    @error('image_position_x')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
    @error('image_position_y')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
    <p class="text-xs text-gray-500">Posisi ini hanya mengatur tampilan crop, tidak mengubah file gambar asli.</p>
  </div>

  <div class="flex flex-wrap gap-2 pt-2">
    <button class="btn btn-primary">{{ $submitLabel }}</button>
    @if ($isEditing)
      <a href="{{ route('admin.banners.index') }}" class="btn btn-ghost">Batal</a>
    @endif
  </div>
</form>

<script>
  document.querySelectorAll('[data-banner-crop]').forEach((editor) => {
    const frame = editor.querySelector('[data-crop-frame]');
    const preview = editor.querySelector('[data-crop-preview]');
    const empty = editor.querySelector('[data-crop-empty]');
    const file = editor.closest('form').querySelector('[name="image"]');
    const positionX = editor.querySelector('[data-crop-x]');
    const positionY = editor.querySelector('[data-crop-y]');
    let objectUrl = null;
    let drag = null;

    const updatePosition = (x, y) => {
      positionX.value = Math.max(0, Math.min(100, Math.round(x)));
      positionY.value = Math.max(0, Math.min(100, Math.round(y)));
      preview.style.objectPosition = `${positionX.value}% ${positionY.value}%`;
    };

    positionX.addEventListener('input', () => updatePosition(positionX.value, positionY.value));
    positionY.addEventListener('input', () => updatePosition(positionX.value, positionY.value));

    file.addEventListener('change', () => {
      const selected = file.files[0];
      if (!selected) return;

      if (objectUrl) URL.revokeObjectURL(objectUrl);
      objectUrl = URL.createObjectURL(selected);
      preview.src = objectUrl;
      preview.hidden = false;
      empty.hidden = true;
      updatePosition(positionX.value, positionY.value);
    });

    frame.addEventListener('pointerdown', (event) => {
      if (!preview.naturalWidth) return;

      const scale = Math.max(
        frame.clientWidth / preview.naturalWidth,
        frame.clientHeight / preview.naturalHeight,
      );
      const overflowX = preview.naturalWidth * scale - frame.clientWidth;
      const overflowY = preview.naturalHeight * scale - frame.clientHeight;

      if (overflowX <= 0 && overflowY <= 0) return;

      drag = {
        pointerId: event.pointerId,
        startPointerX: event.clientX,
        startPointerY: event.clientY,
        startX: Number(positionX.value),
        startY: Number(positionY.value),
        overflowX,
        overflowY,
      };
      frame.setPointerCapture(event.pointerId);
      preview.style.cursor = 'grabbing';
      event.preventDefault();
    });

    frame.addEventListener('pointermove', (event) => {
      if (!drag || drag.pointerId !== event.pointerId) return;

      const x = drag.overflowX > 0
        ? drag.startX - ((event.clientX - drag.startPointerX) / drag.overflowX) * 100
        : drag.startX;
      const y = drag.overflowY > 0
        ? drag.startY - ((event.clientY - drag.startPointerY) / drag.overflowY) * 100
        : drag.startY;

      updatePosition(x, y);
    });

    const stopDragging = (event) => {
      if (!drag || drag.pointerId !== event.pointerId) return;

      drag = null;
      preview.style.cursor = 'grab';
    };

    frame.addEventListener('pointerup', stopDragging);
    frame.addEventListener('pointercancel', stopDragging);
  });
</script>
