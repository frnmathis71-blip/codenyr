
// Records the visitor's choice. The only optional service is Google reCAPTCHA on the forms, when it is configured
// (resources/js/recaptcha.js reads this choice and is told when it changes).
export function initCookiePreferences() {
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
    let openedFromSettings = false;
    settings?.addEventListener('click', () => {
        openedFromSettings = true;
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
        document.dispatchEvent(new CustomEvent('codenyr:cookie-choice', { detail: preference.choice }));
        // Hand focus back only when the footer link opened the banner; otherwise the page would jump to the footer.
        if (openedFromSettings) settings?.focus({ preventScroll: true });
        openedFromSettings = false;
    }));
}
