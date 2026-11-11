<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@hasSection('title')@yield('title') · NutriSyria @else NutriSyria @endif</title>
    <meta name="description" content="@yield('description', config('site.description'))">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/site/mark.svg') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Sora:wght@400;500;600;800&display=swap">

    @vite(['resources/css/site.css', 'resources/js/site.js'])
    {{-- The identity's tiles, addressed from the site's real root (asset), so they load when the
         site is served from a subfolder too. The stylesheets use these as var(--tiles-*). --}}
    <style>
        :root {
            @foreach (['pattern', 'pattern-green', 'cover-1', 'cover-2', 'cover-3', 'cover-4'] as $tiles)
                --tiles-{{ $tiles }}: url('{{ asset("images/site/tiles/$tiles.webp") }}');
            @endforeach
        }
    </style>
    @stack('head')
</head>
<body class="ns-root">
    <a class="ns-skip" href="#main">انتقل إلى المحتوى</a>

    @include('site.partials.header')

    @if ($profileNextStep ?? null)
        @include('site.partials.profile-reminder', ['nextStep' => $profileNextStep])
    @endif

    <main id="main">
        {{-- Account pages show their messages inside the form instead. --}}
        @unless (View::hasSection('form'))
            @include('site.partials.flash', ['toast' => true])
        @endunless
        @yield('content')
    </main>

    @include('site.partials.footer')

    @stack('scripts')
</body>
</html>
