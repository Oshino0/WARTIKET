@if (session('status'))
  <div role="status" class="mb-6 rounded-xl border border-violet-200 bg-brand-surface px-4 py-3 text-sm text-brand">{{ session('status') }}</div>
@endif
@foreach (['items', 'order', 'category', 'ticket_type', 'quantity'] as $key)
  @error($key)
    <div role="alert" class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>
  @enderror
@endforeach
