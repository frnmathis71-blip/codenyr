@props([
    'sidebar' => false,
])

<a {{ $attributes->merge(['class' => 'block w-40']) }} aria-label="Codenyr"><img src="{{ asset('images/codenyr.png') }}" alt="Codenyr" width="2172" height="724" class="w-full h-auto"></a>
