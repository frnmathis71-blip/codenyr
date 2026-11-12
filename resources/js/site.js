import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import { initCookiePreferences } from './cookie-preferences.js';
import { initLaptop } from './home-laptop.js';
import { siteHeader } from './site-header.js';

// Public pages: Livewire only (forms, reviews). Flux is not needed here, which keeps the bundle light.
Alpine.data('siteHeader', siteHeader);
Livewire.start();

// Rate limit reached (HTTP 429): show the form's own notice instead of Livewire's error overlay.
Livewire.interceptRequest(({ onError }) => onError(({ response, preventDefault }) => {
    const notice = document.querySelector('[data-rate-limit]');
    if (response.status !== 429 || !notice) return;
    preventDefault();
    notice.hidden = false;
    notice.focus();
}));

initCookiePreferences();
initLaptop();
document.addEventListener('livewire:navigated', initCookiePreferences);
document.addEventListener('livewire:navigated', initLaptop);
