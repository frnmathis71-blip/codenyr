@props(['name' => 'arrow-up-right'])
@php
    $path = match ($name) {
        'arrow-right' => 'M4 12h16m-7-7 7 7-7 7',
        'arrow-left' => 'M20 12H4m7-7-7 7 7 7',
        'arrow-down' => 'M12 4v16m-7-7 7 7 7-7',
        'arrow-up' => 'M12 20V4m-7 7 7-7 7 7',
        'arrow-down-right' => 'M5 5l14 14M5 19h14V5',
        'arrow-up-left' => 'M19 19 5 5m0 14V5h14',
        'arrow-down-left' => 'M19 5 5 19M5 5v14h14',
        'menu' => 'M4 6h16M4 12h16M4 18h16',
        'check' => 'm4 12 5 5L20 6',
        'close' => 'm6 6 12 12M6 18 18 6',
        'spark' => 'M12 3v18M3 12h18M5.6 5.6l12.8 12.8M5.6 18.4 18.4 5.6',
        'star', 'star-outline' => 'm12 3 2.8 5.7 6.3.9-4.6 4.5 1.1 6.3L12 17.4l-5.6 3 1.1-6.3-4.6-4.5 6.3-.9L12 3Z',
        default => 'M5 19 19 5M5 5h14v14',
    };
@endphp
<svg {{ $attributes->class(['ui-icon']) }} width="1em" height="1em" viewBox="0 0 24 24" fill="{{ $name === 'star' ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:inline-block;vertical-align:-0.15em;flex-shrink:0"><path d="{{ $path }}" /></svg>
