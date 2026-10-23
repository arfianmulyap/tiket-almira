<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>@hasSection('title')@yield('title') — {{ config('app.name') }}@else{{ config('app.name') }}@endif</title>

{{--
    Design system stylesheet.

    Served statically from `public/css` rather than through Vite because
    `styling.md` is authored as plain CSS and the Node toolchain is not
    installed in this environment. `filemtime` busts the browser cache
    whenever the stylesheet changes. Moving these tokens into Tailwind is a
    tracked follow-up once an asset build is available.
--}}
<link
    rel="stylesheet"
    href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}"
>

@if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@endif

@stack('head')
