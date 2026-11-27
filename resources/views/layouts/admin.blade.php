<!DOCTYPE html>
<html lang="id">
<head>@include('layouts.head')</head>
<body class="min-h-screen bg-gray-50 font-sans text-brand-ink antialiased">
  <a href="#main" class="sr-only rounded bg-white px-3 py-2 focus:not-sr-only focus:absolute focus:left-3 focus:top-3 focus:z-50">Lewati ke konten</a>
  @php
    $links = [
      ['admin.dashboard', 'Dashboard', 'admin/dashboard'],
      ['admin.categories.index', 'Kategori', 'admin/categories*'],
      ['admin.products.index', 'Produk', 'admin/products*'],
      ['admin.banners.index', 'Banner', 'admin/banners*'],
      ['admin.orders.index', 'Pesanan', 'admin/orders*'],
      ['admin.payments.index', 'Pembayaran', 'admin/payments*'],
      ['admin.email.edit', 'Email', 'admin/email*'],
    ];
  @endphp

  <div class="md:flex">
    <aside class="border-b border-violet-100 bg-white md:min-h-screen md:w-60 md:shrink-0 md:border-b-0 md:border-r">
      <div class="flex items-center justify-between px-4 py-4 md:block">
        <a href="{{ route('admin.dashboard') }}" class="text-xl font-extrabold tracking-tight text-brand">WARTIKET <span class="text-sm font-semibold text-gray-400">admin</span></a>
      </div>
      <nav class="flex gap-1 overflow-x-auto px-3 pb-3 md:block md:space-y-1 md:overflow-visible" aria-label="Admin">
        @foreach ($links as [$route, $label, $pattern])
          <a href="{{ route($route) }}" @if (request()->is($pattern)) aria-current="page" @endif
             class="block whitespace-nowrap rounded-xl px-3 py-2 text-sm font-medium {{ request()->is($pattern) ? 'bg-brand-surface text-brand' : 'text-gray-600 hover:bg-gray-50' }}">{{ $label }}</a>
        @endforeach
      </nav>
      <div class="hidden space-y-2 border-t border-violet-100 p-4 text-sm md:block">
        <a href="{{ route('home') }}" class="block text-gray-600 hover:text-brand" target="_blank" rel="noopener">Lihat situs</a>
        <form action="{{ route('logout') }}" method="POST">@csrf<button class="text-gray-600 hover:text-brand">Keluar</button></form>
      </div>
    </aside>

    <main id="main" class="min-w-0 flex-1 p-4 md:p-8">
      @include('layouts.messages')
      @yield('content')
    </main>
  </div>
</body>
</html>
