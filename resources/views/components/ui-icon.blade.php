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
        'home' => 'M3 11 12 4l9 7M5.5 9.5V20H10v-6h4v6h4.5V9.5',
        'tag' => 'M3 3h8l10 10-8 8L3 11V3Zm4.5 4.5h.01',
        'document' => 'M14 3H6v18h12V7l-4-4Zm0 0v4h4M9 13h6M9 17h4',
        'grid' => 'M4 4h7v7H4zm9 0h7v7h-7zM4 13h7v7H4zm9 0h7v7h-7z',
        'chat' => 'M4 5h16v11H9l-5 4V5Z',
        'user' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0',
        'sliders' => 'M4 6h9m4 0h3M4 12h3m4 0h9M4 18h11m4 0h1M15 4v4M9 10v4M17 16v4',
        'layers' => 'm12 3 9 5-9 5-9-5 9-5Zm-9 9 9 5 9-5M3 16l9 5 9-5',
        'book' => 'M4 5a2 2 0 0 1 2-2h14v16H6a2 2 0 0 0-2 2V5Zm0 14a2 2 0 0 1 2-2h14',
        'lock' => 'M6 11h12v10H6V11Zm2 0V8a4 4 0 0 1 8 0v3',
        'coffee' => 'M4 9h12v5a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5V9Zm12 2h2a2 2 0 0 1 0 4h-2M8 3v3m4-3v3',
        'sparkle' => 'M12 3v5m0 8v5M3 12h5m8 0h5M6.5 6.5l2.5 2.5m6 6 2.5 2.5m0-11L15 9m-6 6-2.5 2.5',
        'clock' => 'M12 7v5l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'star', 'star-outline' => 'm12 3 2.8 5.7 6.3.9-4.6 4.5 1.1 6.3L12 17.4l-5.6 3 1.1-6.3-4.6-4.5 6.3-.9L12 3Z',
        default => 'M5 19 19 5M5 5h14v14',
    };
@endphp
<svg {{ $attributes->class(['ui-icon']) }} width="1em" height="1em" viewBox="0 0 24 24" fill="{{ $name === 'star' ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" style="display:inline-block;vertical-align:-0.15em;flex-shrink:0"><path d="{{ $path }}" /></svg>
