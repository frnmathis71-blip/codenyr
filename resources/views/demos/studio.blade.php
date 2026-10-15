{{--
    Shared customisation panel for the three product demos.
    Expects:
      $studio = [
        'key'      => storage key (one per demo),
        'product'  => offer name (Landing Page, Site Vitrine, Site Pro),
        'demo'     => demo display name,
        'brand'    => default brand name shown in [data-brand] elements,
        'accent'   => CSS variable holding the accent colour (--accent, --orange…),
        'palettes' => [value => [label, paper, ink, accent]],
        'groups'   => [group label => [setting => [label, [value => option label]]]],
        'presets'  => [value => [label, description, [setting => value]]],
        'defaults' => [setting => value],
      ]
--}}
<button id="cz-toggle" class="cz-toggle" type="button" aria-controls="cz-panel" aria-expanded="false" hidden><span class="cz-toggle-dot" aria-hidden="true"></span>Personnaliser ce site</button>
<section id="cz-panel" class="cz-panel" aria-labelledby="cz-title" hidden
    data-key="{{ $studio['key'] }}"
    data-accent-var="{{ $studio['accent'] }}"
    data-defaults='@json($studio['defaults'])'
    data-presets='@json(collect($studio['presets'])->map(fn ($preset) => $preset[2]))'
    data-demo="{{ $studio['demo'] }}"
    data-quote-url="{{ route('quote', ['offer' => $studio['product']]) }}">
    <div class="cz-head">
        <div><span class="cz-kicker">Le studio Codenyr · {{ $studio['product'] }}</span><h2 id="cz-title">Imaginez votre version.</h2></div>
        <button type="button" class="cz-close" id="cz-close" aria-label="Fermer la personnalisation"><x-ui-icon name="close" /></button>
    </div>
    <p class="cz-intro">Testez votre nom, vos couleurs et une autre mise en page. Tout est appliqué en direct, sur toutes les pages de la démo.</p>

    <form id="cz-form" class="cz-form">
        <fieldset class="cz-group">
            <legend>Votre marque</legend>
            <label for="cz-brand">Nom de votre activité</label>
            <input id="cz-brand" name="brand" maxlength="32" autocomplete="off" placeholder="{{ $studio['brand'] }}">
            <div class="cz-accent">
                <label for="cz-accent">Couleur de votre marque</label>
                <div><input id="cz-accent" name="accent" type="color" value="#000000"><button type="button" id="cz-accent-reset" class="cz-link" hidden>Couleur de la palette</button></div>
            </div>
        </fieldset>

        <fieldset class="cz-group">
            <legend>Ambiances prêtes à l’emploi</legend>
            <div class="cz-presets">
                @foreach($studio['presets'] as $value => [$label, $description])
                    <button type="button" class="cz-preset" data-preset="{{ $value }}" aria-pressed="false"><strong>{{ $label }}</strong><small>{{ $description }}</small></button>
                @endforeach
            </div>
        </fieldset>

        <fieldset class="cz-group">
            <legend>Palette</legend>
            <div class="cz-swatches">
                @foreach($studio['palettes'] as $value => [$label, $paper, $ink, $accent])
                    <label class="cz-swatch" title="{{ $label }}">
                        <input type="radio" name="palette" value="{{ $value }}" @checked(($studio['defaults']['palette'] ?? null) === $value)>
                        <span class="cz-swatch-chip" style="--sw-paper: {{ $paper }}; --sw-ink: {{ $ink }}; --sw-accent: {{ $accent }}" aria-hidden="true"></span>
                        <span class="cz-swatch-label">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        @foreach($studio['groups'] as $group => $settings)
            <fieldset class="cz-group">
                <legend>{{ $group }}</legend>
                @foreach($settings as $name => [$label, $options])
                    <label for="cz-{{ $name }}">{{ $label }}</label>
                    <select id="cz-{{ $name }}" name="{{ $name }}">
                        @foreach($options as $value => $option)<option value="{{ $value }}" @selected(($studio['defaults'][$name] ?? null) === $value)>{{ $option }}</option>@endforeach
                    </select>
                @endforeach
            </fieldset>
        @endforeach

        <div class="cz-actions">
            <button type="button" id="cz-share" class="cz-secondary">Copier le lien de cette version</button>
            <button type="reset" class="cz-secondary">Revenir au design d’origine</button>
        </div>
    </form>
    <a class="cz-cta" id="cz-quote" href="{{ route('quote', ['offer' => $studio['product']]) }}">Demander ce design pour mon activité <x-ui-icon name="arrow-up-right" /></a>
    <p class="cz-status" role="status" aria-live="polite"></p>
</section>
