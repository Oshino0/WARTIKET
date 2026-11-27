@extends('layouts.app')
@section('title', 'Tiket konser, olahraga, bioskop, dan lainnya')
@section('content')
  <section class="rounded-3xl bg-brand-surface px-5 pb-6 pt-8 sm:px-8 sm:pb-8 sm:pt-10">
    <h1 class="max-w-2xl text-3xl font-extrabold leading-tight tracking-tight sm:text-5xl">Semua tiket, satu tempat.</h1>
    <p class="mt-3 max-w-xl text-gray-600">Cari acara, pilih tipe tiket, bayar, dan e-ticket langsung ada di akunmu.</p>

    <div class="mt-4 flex flex-wrap gap-2">
      @foreach ($categories as $category)
        <a href="{{ route('explore', ['category' => $category->slug]) }}"
           class="rounded-full border border-violet-200 bg-white px-4 py-1.5 text-xs font-medium transition hover:border-brand hover:text-brand">
          {{ $category->name }}
          @if ($category->products_count) <span class="ml-1 text-[11px] text-gray-400">{{ $category->products_count }}</span> @endif
        </a>
      @endforeach
    </div>
  </section>

  @if ($banners->isNotEmpty())
    <section class="relative mt-2 overflow-hidden rounded-2xl border border-violet-100" aria-label="Promosi" data-banner-carousel>
      <div class="flex transition-transform duration-500 ease-in-out" data-banner-track>
        @foreach ($banners as $banner)
          @php $tag = $banner->link_url ? 'a' : 'div'; @endphp
          <{{ $tag }} @if ($banner->link_url) href="{{ $banner->link_url }}" @endif
            class="w-full shrink-0" aria-label="{{ $banner->title }}">
            <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}" class="aspect-[3/1] w-full object-cover" style="object-position: {{ $banner->image_position_x }}% {{ $banner->image_position_y }}%;" loading="{{ $loop->first ? 'eager' : 'lazy' }}">
          </{{ $tag }}>
        @endforeach
      </div>

      @if ($banners->count() > 1)
        <button type="button"
          class="absolute left-3 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-xl text-brand shadow-md transition hover:bg-white"
          data-banner-prev aria-label="Banner sebelumnya">
          <span aria-hidden="true">‹</span>
        </button>
        <button type="button"
          class="absolute right-3 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-xl text-brand shadow-md transition hover:bg-white"
          data-banner-next aria-label="Banner berikutnya">
          <span aria-hidden="true">›</span>
        </button>

        <div class="absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-2 rounded-full bg-black/20 px-3 py-2" data-banner-dots>
          @foreach ($banners as $banner)
            <button type="button"
              class="h-2.5 w-2.5 rounded-full bg-white/60 transition-all {{ $loop->first ? '!w-6 !bg-white' : '' }}"
              data-banner-dot="{{ $loop->index }}"
              aria-label="Tampilkan banner {{ $loop->iteration }}"
              aria-current="{{ $loop->first ? 'true' : 'false' }}"></button>
          @endforeach
        </div>
      @endif
    </section>

    @if ($banners->count() > 1)
      <script>
        (() => {
          const carousel = document.querySelector('[data-banner-carousel]');
          if (!carousel) return;

          const track = carousel.querySelector('[data-banner-track]');
          const slides = Array.from(track.children);
          const dots = Array.from(carousel.querySelectorAll('[data-banner-dot]'));
          let current = 0;
          let timer;
          let touchStartX = 0;

          const show = (index) => {
            current = (index + slides.length) % slides.length;
            track.style.transform = `translateX(-${current * 100}%)`;

            dots.forEach((dot, dotIndex) => {
              dot.classList.toggle('!w-6', dotIndex === current);
              dot.classList.toggle('!bg-white', dotIndex === current);
              dot.classList.toggle('bg-white/60', dotIndex !== current);
              dot.setAttribute('aria-current', dotIndex === current ? 'true' : 'false');
            });
          };

          const stop = () => window.clearInterval(timer);
          const start = () => {
            stop();
            timer = window.setInterval(() => show(current + 1), 5000);
          };

          carousel.querySelector('[data-banner-prev]')?.addEventListener('click', () => {
            show(current - 1);
            start();
          });
          carousel.querySelector('[data-banner-next]')?.addEventListener('click', () => {
            show(current + 1);
            start();
          });
          dots.forEach((dot) => dot.addEventListener('click', () => {
            show(Number(dot.dataset.bannerDot));
            start();
          }));

          carousel.addEventListener('mouseenter', stop);
          carousel.addEventListener('mouseleave', start);
          carousel.addEventListener('focusin', stop);
          carousel.addEventListener('focusout', start);
          carousel.addEventListener('touchstart', (event) => {
            touchStartX = event.changedTouches[0].clientX;
          }, { passive: true });
          carousel.addEventListener('touchend', (event) => {
            const distance = event.changedTouches[0].clientX - touchStartX;
            if (Math.abs(distance) > 50) show(current + (distance < 0 ? 1 : -1));
            start();
          }, { passive: true });

          show(0);
          start();
        })();
      </script>
    @endif
  @endif

  @if ($featured->isNotEmpty())
    <section class="mt-10">
      <h2 class="text-xl font-bold">Tiket populer</h2>
      <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @foreach ($featured as $product) <x-product-card :product="$product" /> @endforeach
      </div>
    </section>
  @endif

  <section class="mt-10">
    <div class="flex items-end justify-between gap-4">
      <h2 class="text-xl font-bold">Baru ditambahkan</h2>
      <a href="{{ route('explore') }}" class="shrink-0 text-sm font-semibold text-brand transition hover:opacity-80">Lihat semua</a>
    </div>

    @if ($latest->isEmpty())
      <p class="mt-5 rounded-2xl border border-dashed border-violet-200 p-8 text-center text-gray-500">Belum ada tiket yang dijual. Coba lagi nanti.</p>
    @else
      <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @foreach ($latest as $product) <x-product-card :product="$product" /> @endforeach
      </div>
    @endif
  </section>
@endsection