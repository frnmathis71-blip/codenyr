import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import { initCookiePreferences } from './cookie-preferences.js';
import priceEstimator from './price-estimator.js';

// Public pages: Livewire only (forms, reviews). Flux is not needed here, which keeps the bundle light.
Alpine.data('priceEstimator', priceEstimator);
Livewire.start();

initCookiePreferences();
document.addEventListener('livewire:navigated', initCookiePreferences);
