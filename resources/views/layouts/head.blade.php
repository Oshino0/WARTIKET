<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@hasSection('title')@yield('title') – WARTIKET @else WARTIKET @endif</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
{{-- Tailwind via CDN keeps the overlay independent of the Laravel starter kit's Vite setup.
     For production, switch to a compiled build (npm run build) and drop these two tags. --}}
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: { extend: {
      fontFamily: { sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'] },
      colors: { brand: { DEFAULT: '#6D28D9', accent: '#A78BFA', surface: '#F5F3FF', ink: '#1F2937' } }
    } }
  }
</script>
<style type="text/tailwindcss">
  @layer components {
    .btn { @apply inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:cursor-not-allowed disabled:opacity-50; }
    .btn-primary { @apply bg-brand text-white hover:bg-violet-800; }
    .btn-ghost { @apply border border-violet-200 bg-white text-brand hover:bg-brand-surface; }
    .btn-danger { @apply border border-red-200 bg-white text-red-700 hover:bg-red-50; }
    .input { @apply w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/30; }
    .th { @apply px-4 py-3 text-left text-xs font-semibold text-gray-500; }
    .td { @apply px-4 py-3 text-sm; }
  }
</style>
<style>
  /* Ticket-stub perforation: the half-circles sit on the card edge, the dashes run between them. */
  .perf { position: relative; margin: 0 16px; border-top: 2px dashed #DDD6FE; }
  .perf::before, .perf::after {
    content: ""; position: absolute; top: -11px; width: 20px; height: 20px;
    border-radius: 9999px; background: #fff; border: 1px solid #EDE9FE;
  }
  .perf::before { left: -27px; }
  .perf::after { right: -27px; }
  @media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
</style>
