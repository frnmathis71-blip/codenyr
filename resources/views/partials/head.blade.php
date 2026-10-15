<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<meta name="robots" content="noindex,nofollow">
<link rel="icon" href="{{ asset('favicon-48.png') }}" type="image/png">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=codenyr">
<meta name="apple-mobile-web-app-title" content="Codenyr">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
