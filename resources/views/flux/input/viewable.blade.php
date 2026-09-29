@props(['iconVariant' => 'mini', 'size' => null])

<flux:button
    :attributes="$attributes->merge(['variant' => 'subtle', 'class' => '-me-1', 'square' => true])"
    :size="$size === 'sm' || $size === 'xs' ? 'xs' : 'sm'"
    x-data="fluxInputViewable"
    x-on:click="toggle()"
    x-bind:aria-pressed="open"
    aria-label="{{ __('Toggle password visibility') }}"
>
    <svg class="size-5 shrink-0" data-password-visibility-icon viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
        <circle cx="12" cy="12" r="3" />
        <path x-show="open" style="display: none" d="m3 3 18 18" />
    </svg>
</flux:button>
