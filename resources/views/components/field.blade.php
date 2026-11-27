@props(['label', 'name', 'type' => 'text', 'value' => null, 'hint' => null])
<div>
  <label for="{{ $name }}" class="mb-1 block text-sm font-medium">{{ $label }}</label>
  <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
         @if (! in_array($type, ['password', 'file'])) value="{{ old($name, $value) }}" @endif
         {{ $attributes->merge(['class' => 'input']) }}>
  @if ($hint)<p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>@endif
  @error($name)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>
