import { Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';
import '../../vendor/livewire/flux/dist/flux-lite.min.js';
import { initCookiePreferences } from './cookie-preferences.js';

// Admin, account and authentication pages: Livewire plus the Flux UI runtime.
Livewire.start();

initCookiePreferences();
document.addEventListener('livewire:navigated', initCookiePreferences);
