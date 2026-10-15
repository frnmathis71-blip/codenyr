@php
    $estimator = app(\App\Services\PriceEstimator::class)->data();
@endphp
<div class="estimator" x-data="priceEstimator(@js($estimator), @js(route('quote')))">
    <ol class="estimator-steps" aria-label="Étapes de l’estimation">
        <template x-for="(label, index) in steps" :key="index">
            <li x-show="index !== 1 || !offer || hasSize">
                <button type="button" @click="go(index + 1)" :disabled="index > 0 && !offer" :class="{ 'is-current': step === index + 1, 'is-done': step > index + 1 }" :aria-current="step === index + 1 ? 'step' : null">
                    <span class="estimator-step-index" x-text="index + 1"></span><span x-text="label"></span>
                </button>
            </li>
        </template>
    </ol>

    <div class="estimator-body">
        <div class="estimator-panel" x-ref="panel" tabindex="-1">
            {{-- 1. Type of project --}}
            <fieldset x-show="step === 1">
                <legend>Quel type de site vous faut-il ?</legend>
                <div class="estimator-offers">
                    @foreach($estimator['offers'] as $key => $offer)
                        <button type="button" class="estimator-choice" :class="{ 'is-selected': offer === '{{ $key }}' }" :aria-pressed="offer === '{{ $key }}'" @click="chooseOffer('{{ $key }}')">
                            <strong>{{ $offer['name'] }}</strong>
                            <span>{{ $offer['intro'] }}</span>
                            <small>dès {{ number_format($offer['amount'] / 100, 0, ',', ' ') }} €</small>
                        </button>
                    @endforeach
                </div>
            </fieldset>

            {{-- 2. Size --}}
            <fieldset x-show="step === 2" x-cloak>
                <legend x-text="current && current.unit === 'section' ? 'Combien de sections sur votre page ?' : 'Combien de pages environ ?'"></legend>
                <p class="estimator-hint" x-show="current">
                    <span x-text="current ? plural(current.included, current.unit) + ' incluses dans l’offre.' : ''"></span>
                    <span x-show="current && current.extra" x-text="current && current.extra ? 'Au-delà : ' + euros(current.extra) + ' par ' + current.unit + '.' : ''"></span>
                </p>
                <div class="estimator-counter">
                    <button type="button" @click="resize(-1)" :disabled="size <= 1" aria-label="Retirer">−</button>
                    <output aria-live="polite"><strong x-text="size"></strong> <span x-text="current ? current.unit + (size > 1 ? 's' : '') : ''"></span></output>
                    <button type="button" @click="resize(1)" aria-label="Ajouter">+</button>
                </div>
                <p class="estimator-hint">Pas sûr ? Gardez la valeur proposée : on affinera ensemble.</p>
            </fieldset>

            {{-- 3. Features --}}
            <fieldset x-show="step === 3" x-cloak>
                <legend>Des fonctionnalités en plus ?</legend>
                <p class="estimator-hint">Facultatif. Cochez seulement ce dont vous êtes sûr.</p>
                <div class="estimator-options">
                    <template x-for="option in available" :key="option.id">
                        <div class="estimator-option" :class="{ 'is-selected': picked[option.id] }">
                            <template x-if="!option.unit">
                                <button type="button" class="estimator-option-toggle" @click="toggle(option.id)" :aria-pressed="!!picked[option.id]">
                                    <span class="estimator-check" aria-hidden="true"><x-ui-icon name="check" /></span>
                                    <span class="estimator-option-text"><strong x-text="option.name"></strong><small x-show="option.description" x-text="option.description"></small></span>
                                    <span class="estimator-option-price" x-text="(option.starting ? 'dès ' : '+ ') + euros(option.amount)"></span>
                                </button>
                            </template>
                            <template x-if="option.unit">
                                <div class="estimator-option-toggle">
                                    <span class="estimator-option-text"><strong x-text="option.name"></strong><small x-text="euros(option.amount) + ' par ' + option.unit"></small></span>
                                    <span class="estimator-mini-counter">
                                        <button type="button" @click="bump(option.id, -1)" :disabled="!picked[option.id]" :aria-label="'Retirer : ' + option.name">−</button>
                                        <span x-text="picked[option.id] || 0"></span>
                                        <button type="button" @click="bump(option.id, 1)" :aria-label="'Ajouter : ' + option.name">+</button>
                                    </span>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </fieldset>

            {{-- 4. Maintenance --}}
            <fieldset x-show="step === 4" x-cloak>
                <legend>Et après la mise en ligne ?</legend>
                <p class="estimator-hint">La maintenance est facultative : vous pouvez aussi gérer vous-même votre hébergement.</p>
                <div class="estimator-plans">
                    <label class="estimator-choice" :class="{ 'is-selected': maintenance === '' }">
                        <input type="radio" class="sr-only" value="" x-model="maintenance">
                        <strong>Sans maintenance</strong><span>Je récupère mon site clé en main.</span><small>0 €/mois</small>
                    </label>
                    @foreach($estimator['maintenance'] as $key => $plan)
                        <label class="estimator-choice" :class="{ 'is-selected': maintenance === '{{ $key }}' }">
                            <input type="radio" class="sr-only" value="{{ $key }}" x-model="maintenance">
                            <strong>{{ $plan['name'] }}</strong><span>{{ $plan['intro'] }}</span><small>{{ $plan['starting'] ? 'dès ' : '' }}{{ number_format($plan['amount'] / 100, 2, ',', ' ') }} €/mois</small>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="estimator-nav" x-show="step > 1" x-cloak>
                <button type="button" class="button button-secondary" @click="back()">Retour</button>
                <button type="button" class="button button-primary" x-show="step < 4" @click="next()">Continuer</button>
            </div>
        </div>

        <aside class="estimator-summary" aria-label="Votre estimation">
            <p class="estimator-summary-title">Votre estimation</p>
            <div x-show="!offer">
                <p class="estimator-empty">Choisissez un type de site pour voir une première fourchette de prix.</p>
            </div>
            <div x-show="offer" x-cloak>
                <ul class="estimator-lines">
                    <template x-for="line in lines" :key="line.label">
                        <li><span x-text="line.label"></span><span x-text="(line.starting ? 'dès ' : '') + euros(line.amount)"></span></li>
                    </template>
                </ul>
                <p class="estimator-range" aria-live="polite">
                    <small>Fourchette indicative</small>
                    <strong><span x-text="euros(low)"></span> – <span x-text="euros(high)"></span></strong>
                </p>
                <p class="estimator-monthly" x-show="plan"><span x-text="plan ? '+ maintenance ' + plan.name + ' : ' + (plan.starting ? 'dès ' : '') + monthly(plan.amount) + '/mois' : ''"></span></p>
                <p class="estimator-note" x-show="open">Certaines options dépendent de vos besoins : le montant final sera précisé sur le devis.</p>
                <a class="button button-primary estimator-cta" :href="quoteLink">Recevoir mon devis précis</a>
                <button type="button" class="estimator-restart" @click="restart()">Recommencer</button>
            </div>
            <p class="estimator-legal">{{ config('codenyr.legal.vat') }}. Estimation sans engagement, le devis détaillé fait foi.</p>
        </aside>
    </div>
    <noscript><p class="estimator-hint">L’estimateur nécessite JavaScript. <a class="text-link" href="{{ route('quote') }}">Décrivez directement votre projet</a> pour recevoir un devis.</p></noscript>
</div>
