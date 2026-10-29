@php
    // Critères tirés des règles réellement appliquées (Password::defaults()), pour que la liste reste fidèle à la validation.
    $rules = \Illuminate\Validation\Rules\Password::defaults()->appliedRules();

    $criteria = array_filter([
        'min' => __('At least :min characters', ['min' => $rules['min']]),
        'letters' => $rules['letters'] && ! $rules['mixedCase'] ? __('At least one letter') : null,
        'mixedCase' => $rules['mixedCase'] ? __('One uppercase and one lowercase letter') : null,
        'numbers' => $rules['numbers'] ? __('At least one number') : null,
        'symbols' => $rules['symbols'] ? __('At least one special character (!, ?, #, @…)') : null,
        'match' => __('Both passwords match'),
    ]);
@endphp

<div
    {{ $attributes->class('flex flex-col gap-6') }}
    x-data="{
        password: '',
        confirmation: '',
        min: {{ (int) $rules['min'] }},
        read() {
            const inputs = this.$root.querySelectorAll('input[autocomplete=new-password]');
            this.password = inputs[0]?.value ?? '';
            this.confirmation = inputs[1]?.value ?? '';
        },
        met(rule) {
            const p = this.password;
            switch (rule) {
                case 'min': return [...p].length >= this.min;
                case 'letters': return /\p{L}/u.test(p);
                case 'mixedCase': return /\p{Ll}/u.test(p) && /\p{Lu}/u.test(p);
                case 'numbers': return /\p{N}/u.test(p);
                case 'symbols': return /[\p{Z}\p{S}\p{P}]/u.test(p);
                case 'match': return p !== '' && p === this.confirmation;
            }
            return false;
        },
    }"
    x-init="read()"
    x-on:input="read()"
>
    {{ $slot }}

    <div class="-mt-3 text-sm" aria-live="polite">
        <p class="mb-2 text-zinc-500 dark:text-zinc-400">{{ __('Your password must contain:') }}</p>
        <ul class="flex flex-col gap-1.5">
            @foreach ($criteria as $rule => $label)
                <li
                    class="flex items-center gap-2 transition-colors"
                    x-bind:class="met('{{ $rule }}') ? 'text-green-600 dark:text-green-400' : 'text-zinc-500 dark:text-zinc-400'"
                >
                    <flux:icon.check-circle variant="micro" x-show="met('{{ $rule }}')" x-cloak class="shrink-0" />
                    <flux:icon.minus-circle variant="micro" x-show="! met('{{ $rule }}')" class="shrink-0" />
                    <span>{{ $label }}</span>
                </li>
            @endforeach
            @if ($rules['uncompromised'])
                <li class="flex items-center gap-2 text-zinc-500 dark:text-zinc-400">
                    <flux:icon.shield-check variant="micro" class="shrink-0" />
                    <span>{{ __('Must not appear in a known data leak') }}</span>
                </li>
            @endif
        </ul>
    </div>
</div>
