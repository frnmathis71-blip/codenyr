import { Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';
import '../../vendor/livewire/flux/dist/flux-lite.min.js';

// One shared runtime for public, authentication and admin pages, including navigation.
Livewire.start();

// No optional trackers are loaded by this site. This records the visitor's choice.
function initCookiePreferences() {
    const banner = document.querySelector('#cookie-banner');
    const settings = document.querySelector('#cookie-settings');
    if (!banner || banner.dataset.initialized) return;
    banner.dataset.initialized = 'true';
    const key = 'codenyr-cookie-preferences-v1';
    let preference = null;
    try { preference = JSON.parse(localStorage.getItem(key) || 'null'); } catch {}
    const valid = preference && ['accepted', 'rejected'].includes(preference.choice)
        && typeof preference.expires === 'number' && preference.expires > Date.now();
    banner.hidden = Boolean(valid);
    settings?.addEventListener('click', () => {
        banner.hidden = false;
        document.querySelector('#cookie-status').textContent = preference && preference.expires > Date.now()
            ? `Choix enregistré : cookies facultatifs ${preference.choice === 'accepted' ? 'acceptés' : 'refusés'}.`
            : '';
        banner.querySelector('button').focus();
    });
    banner.querySelectorAll('[data-cookie-choice]').forEach(button => button.addEventListener('click', () => {
        preference = { choice: button.dataset.cookieChoice, expires: Date.now() + 180 * 86400000 };
        try { localStorage.setItem(key, JSON.stringify(preference)); }
        catch { /* The preference applies to this page even if storage is unavailable. */ }
        banner.hidden = true;
        settings?.focus();
    }));
}
initCookiePreferences();
document.addEventListener('livewire:navigated', initCookiePreferences);
