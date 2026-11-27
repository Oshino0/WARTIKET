@props(['item', 'ratio' => 'aspect-[16/10]'])
<div {{ $attributes->merge(['class' => 'overflow-hidden bg-brand-surface ' . $ratio]) }}>
  @if ($item->image_url)
    <img src="{{ $item->image_url }}" alt="{{ $item->name }}" class="h-full w-full object-cover" loading="lazy">
  @else
    <div class="flex h-full w-full items-center justify-center bg-brand" aria-hidden="true">
      <span class="text-5xl font-extrabold text-white/90">{{ mb_strtoupper(mb_substr($item->name, 0, 1)) }}</span>
    </div>
  @endif
</div>
