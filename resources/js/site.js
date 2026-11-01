import { Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';
import { initCookiePreferences } from './cookie-preferences.js';
import { initLaptop } from './home-laptop.js';

// Public pages: Livewire only (forms, reviews). Flux is not needed here, which keeps the bundle light.
Livewire.start();

initCookiePreferences();
initLaptop();
document.addEventListener('livewire:navigated', initCookiePreferences);
document.addEventListener('livewire:navigated', initLaptop);
