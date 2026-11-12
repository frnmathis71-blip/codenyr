@props(['action', 'livewire' => false, 'challenge' => false])
{{-- Google reCAPTCHA for one form: invisible v3 token on submit, v2 checkbox when the server asks for it.
     Nothing is loaded from Google before the visitor agreed (resources/js/recaptcha.js). --}}
@if(\App\Support\Recaptcha::enabled())
@once
@vite('resources/js/recaptcha.js')
<style>
.grecaptcha-badge { visibility: hidden; }
.recaptcha { display: grid; gap: 12px; margin: 4px 0; }
.recaptcha [hidden] { display: none; }
.recaptcha-consent { border: 1px solid color-mix(in srgb, currentColor 25%, transparent); border-radius: 10px; padding: 14px 16px; font-size: 14px; line-height: 1.5; }
.recaptcha-consent p { margin: 0 0 10px; }
.recaptcha-consent button { border: 1px solid currentColor; border-radius: 999px; background: none; color: inherit; cursor: pointer; font: inherit; font-weight: 600; padding: 8px 16px; }
.recaptcha-consent button:hover, .recaptcha-consent button:focus-visible { background: color-mix(in srgb, currentColor 12%, transparent); }
.recaptcha-error { color: #f87171; font-size: 14px; margin: 0; }
.recaptcha-notice { font-size: 11px; line-height: 1.5; margin: 0; opacity: .7; }
.recaptcha a { text-decoration: underline; }
</style>
@endonce
<div class="recaptcha" data-recaptcha data-site-key="{{ config('services.recaptcha.site_key') }}" data-action="{{ $action }}"
    @if(\App\Support\Recaptcha::challengeAvailable()) data-challenge-key="{{ config('services.recaptcha.v2_site_key') }}" @endif
    @if($challenge) data-challenge @endif @if($livewire) wire:ignore @endif>
    <input type="hidden" name="recaptcha_token" data-recaptcha-token @if($livewire) wire:model="captcha" @endif>
    <input type="hidden" name="recaptcha_challenge" data-recaptcha-challenge-token @if($livewire) wire:model="captchaChallenge" @endif>
    <div class="recaptcha-consent" data-recaptcha-consent tabindex="-1" hidden>
        <p><strong>Protection anti-robots.</strong> Pour envoyer ce formulaire, autorisez Google reCAPTCHA : ce service vérifie que vous n’êtes pas un robot et dépose des cookies Google. Vous préférez vous en passer ? Écrivez-nous à <a href="mailto:{{ config('codenyr.email') }}">{{ config('codenyr.email') }}</a>.</p>
        <button type="button" data-recaptcha-accept>Autoriser reCAPTCHA</button>
    </div>
    <div data-recaptcha-widget hidden></div>
    <p class="recaptcha-error" data-recaptcha-error role="alert" hidden></p>
    <p class="recaptcha-notice">Ce site est protégé par reCAPTCHA. Les <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">règles de confidentialité</a> et les <a href="https://policies.google.com/terms" target="_blank" rel="noopener noreferrer">conditions d’utilisation</a> de Google s’appliquent.</p>
</div>
@endif
