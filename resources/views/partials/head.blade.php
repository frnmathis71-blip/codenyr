<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<meta name="robots" content="noindex,nofollow">
<link rel="icon" href="{{ asset('images/codenyr.png') }}" type="image/png">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
