<x-layouts::auth :title="__('Register')">
    <div class="flex flex-col gap-6">
        <x-auth-header title="Créer mon compte client" description="Retrouvez vos projets et partagez votre expérience après la livraison de votre site." />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf
            <input type="hidden" name="started" value="{{ \App\Support\SpamGuard::startToken() }}">
            <div aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden"><label for="fax">Laisser ce champ vide</label><input id="fax" name="fax" type="text" tabindex="-1" autocomplete="off"></div>
            <!-- Name -->
            <flux:input
                name="name"
                :label="__('Name')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('Full name')"
            />

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
            />

            <x-password-requirements>
                <!-- Password -->
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    :placeholder="__('Password')"
                    passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                    viewable
                />

                <!-- Confirm Password -->
                <flux:input
                    name="password_confirmation"
                    :label="__('Confirm password')"
                    type="password"
                    required
                    autocomplete="new-password"
                    :placeholder="__('Confirm password')"
                    passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                    viewable
                />
            </x-password-requirements>

            <x-recaptcha action="inscription" :challenge="(bool) session('recaptcha_challenge')" />
            @error('captcha')<p class="text-sm text-red-400" role="alert">{{ $message }}</p>@enderror

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                    {{ __('Create account') }}
                </flux:button>
            </div>
        </form>
        <p class="text-xs text-zinc-400">@if(\Laravel\Fortify\Features::enabled(\Laravel\Fortify\Features::emailVerification())) Un e-mail de vérification vous sera envoyé. @else Votre espace client est accessible dès l’inscription. @endif Le dépôt d’avis sera ouvert après confirmation de votre devis et de la livraison par Codenyr. Consultez notre <flux:link :href="route('privacy')">politique de confidentialité</flux:link>.</p>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
