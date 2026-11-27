<!DOCTYPE html>
<html lang="id">
<head>@include('layouts.head')</head>
<body class="min-h-screen bg-white font-sans text-brand-ink antialiased">
  <a href="#main" class="sr-only rounded bg-white px-3 py-2 focus:not-sr-only focus:absolute focus:left-3 focus:top-3 focus:z-50">Lewati ke konten</a>

  <header class="border-b border-violet-100">
    <div class="mx-auto grid max-w-6xl grid-cols-[1fr_auto] items-center gap-x-4 gap-y-3 px-4 pb-3 pt-6 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,28rem)_minmax(0,1fr)] xl:grid-cols-[minmax(0,1fr)_minmax(20rem,34rem)_minmax(0,1fr)]">
      <div class="flex items-center gap-4">
        <a href="{{ route('home') }}" class="text-xl font-extrabold tracking-tight text-brand">WARTIKET</a>
        <nav class="hidden items-center gap-5 text-sm font-semibold lg:flex" aria-label="Utama">
          <a href="{{ route('explore') }}" class="text-gray-700 transition hover:text-brand">Jelajahi</a>
        </nav>
      </div>

      <form action="{{ route('explore') }}" method="GET" role="search"
        class="col-span-2 row-start-2 flex h-11 w-full items-center gap-2 rounded-full border-2 border-violet-300 bg-brand-surface p-1 shadow-sm transition focus-within:border-brand focus-within:ring-4 focus-within:ring-violet-100 lg:col-span-1 lg:col-start-2 lg:row-start-1 lg:justify-self-center">
        <label for="header-q" class="sr-only">Cari tiket</label>
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="ml-3 h-5 w-5 shrink-0 text-brand">
          <circle cx="11" cy="11" r="7" />
          <path stroke-linecap="round" d="m16 16 4 4" />
        </svg>
        <input id="header-q" type="search" name="q" value="{{ request('q') }}" placeholder="Cari acara, konser, tiket..." class="h-full min-w-0 flex-1 border-0 bg-transparent text-sm outline-none placeholder:text-gray-500 focus:ring-0">
        <button class="btn btn-primary !h-full !rounded-full !px-5">Cari</button>
      </form>

    </div>
  </header>

  <main id="main" class="mx-auto max-w-6xl px-4 pb-10 pt-6">
    @include('layouts.messages')
    @yield('content')
  </main>

  <footer class="mt-16 border-t border-violet-100">
    <div class="mx-auto max-w-6xl px-4 py-8 text-sm text-gray-500">WARTIKET – tiket konser, olahraga, bioskop, kuliner, seminar, dan wisata.</div>
  </footer>
</body>
</html>