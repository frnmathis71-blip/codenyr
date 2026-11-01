{{-- Phones only: floating tab bar of the demos. It replaces the menu button and the floating
     "Personnaliser ce site" button; tabs are [href, label, icon, current]. --}}
@props(['tabs'])
<nav class="demo-tabbar" aria-label="Navigation rapide">
    @foreach($tabs as [$href, $label, $icon, $current])
        <a href="{{ $href }}" @if($current) aria-current="page" @endif><span class="demo-tabbar-icon"><x-ui-icon :name="$icon" /></span>{{ $label }}</a>
    @endforeach
    <button type="button" class="demo-tabbar-studio" data-cz-open aria-controls="cz-panel" aria-expanded="false" aria-label="Modifier le design de ce site"><span class="demo-tabbar-icon"><x-ui-icon name="sliders" /></span>Modifier</button>
</nav>
