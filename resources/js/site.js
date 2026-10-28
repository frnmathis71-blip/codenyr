import { Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';
import { initCookiePreferences } from './cookie-preferences.js';

// Public pages: Livewire only (forms, reviews). Flux is not needed here, which keeps the bundle light.
Livewire.start();

initCookiePreferences();
document.addEventListener('livewire:navigated', initCookiePreferences);
