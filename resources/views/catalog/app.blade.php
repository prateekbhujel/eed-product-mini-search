<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @php
            $seo = $seo ?? [
                'title' => 'E24 Appliance Spare Parts Search',
                'description' => 'Search appliance spare parts by model number, OEM reference, brand, category and availability.',
                'canonical' => url('/'),
                'robots' => 'index,follow',
                'type' => 'website',
                'image' => null,
                'schema' => null,
            ];
        @endphp
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="description" content="{{ $seo['description'] }}">
        <meta name="robots" content="{{ $seo['robots'] }}">
        <link rel="canonical" href="{{ $seo['canonical'] }}">
        <meta property="og:title" content="{{ $seo['title'] }}">
        <meta property="og:description" content="{{ $seo['description'] }}">
        <meta property="og:type" content="{{ $seo['type'] }}">
        <meta property="og:url" content="{{ $seo['canonical'] }}">
        @if(! empty($seo['image']))
            <meta property="og:image" content="{{ $seo['image'] }}">
            <meta name="twitter:image" content="{{ $seo['image'] }}">
            <link rel="preload" as="image" href="{{ $seo['image'] }}" fetchpriority="high">
        @endif
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $seo['title'] }}">
        <meta name="twitter:description" content="{{ $seo['description'] }}">
        <title>{{ $seo['title'] }}</title>
        @if(! empty($seo['schema']))
            <script type="application/ld+json">{!! json_encode($seo['schema'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endif
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    </head>
    <body>
        <div id="eed-root"></div>
    </body>
</html>
