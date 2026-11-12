// Google reCAPTCHA for the forms that contain <x-recaptcha>: this file is only loaded on those pages.
// Each submission is held back, a fresh v3 token is put in the form, then the submission goes on.
// When the server answers with a doubtful score, a v2 checkbox is shown and its answer sent along.
const PREFERENCES = 'codenyr-cookie-preferences-v1';
const MESSAGES = {
    blocked: 'La protection anti-robots n’a pas pu se charger. Vérifiez votre connexion ou votre bloqueur de contenu, puis réessayez.',
    unchecked: 'Cochez la case « Je ne suis pas un robot » pour envoyer le formulaire.',
};
let script = null;
// Agreement given on this page, kept in memory when the browser refuses to store it.
let granted = false;

function consentGiven() {
    if (granted) return true;
    try {
        const preference = JSON.parse(localStorage.getItem(PREFERENCES) || 'null');
        return preference?.choice === 'accepted' && preference.expires > Date.now();
    } catch {
        return false;
    }
}

function loadRecaptcha(siteKey) {
    script ??= new Promise((resolve, reject) => {
        const tag = document.createElement('script');
        tag.src = `https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(siteKey)}&hl=fr`;
        tag.async = true;
        tag.onload = () => window.grecaptcha.ready(resolve);
        tag.onerror = () => { script = null; tag.remove(); reject(new Error('reCAPTCHA unavailable')); };
        document.head.append(tag);
    });
    return script;
}

// Livewire reads the value on "input"; a plain form simply posts it.
function fill(input, value) {
    input.value = value;
    input.dispatchEvent(new Event('input', { bubbles: true }));
}

function protect(box) {
    const form = box.closest('form');
    if (!form || box.dataset.ready) return;
    box.dataset.ready = 'true';
    const { siteKey, challengeKey, action } = box.dataset;
    const part = name => box.querySelector(`[data-recaptcha-${name}]`);
    const [token, challengeToken, consent, widget, error] = ['token', 'challenge-token', 'consent', 'widget', 'error'].map(part);
    let widgetId = null;
    let cleared = false;
    let busy = false;
    const report = message => { error.textContent = message; error.hidden = message === ''; };

    const challenge = async () => {
        if (!challengeKey) return;
        try { await loadRecaptcha(siteKey); } catch { return report(MESSAGES.blocked); }
        fill(challengeToken, '');
        widget.hidden = false;
        if (widgetId !== null) return window.grecaptcha.reset(widgetId);
        widgetId = window.grecaptcha.render(widget, {
            sitekey: challengeKey,
            theme: 'dark',
            callback: answer => { fill(challengeToken, answer); report(''); },
            'expired-callback': () => fill(challengeToken, ''),
        });
    };
    const refresh = () => {
        consent.hidden = consentGiven();
        if (consent.hidden) loadRecaptcha(siteKey).then(() => box.hasAttribute('data-challenge') && widgetId === null && challenge()).catch(() => {});
    };
    part('accept').addEventListener('click', () => {
        granted = true;
        try { localStorage.setItem(PREFERENCES, JSON.stringify({ choice: 'accepted', expires: Date.now() + 180 * 86400000 })); } catch { /* Private browsing: asked again on the next page. */ }
        refresh();
    });
    // Choice made in the cookie banner (cookie-preferences.js).
    document.addEventListener('codenyr:cookie-choice', event => {
        if (event.detail === 'rejected') granted = false;
        refresh();
    });
    window.addEventListener('recaptcha-challenge', challenge);
    window.addEventListener('recaptcha-reset', () => { if (widgetId !== null) challenge(); });

    // Capture phase on the document: runs before Livewire's own submit handler on the form.
    document.addEventListener('submit', async event => {
        if (event.target !== form) return;
        if (cleared) { cleared = false; return; }
        event.preventDefault();
        event.stopPropagation();
        if (busy) return;
        if (!consent.hidden) { consent.focus(); return; }
        if (widgetId !== null && challengeToken.value === '') return report(MESSAGES.unchecked);
        busy = true;
        report('');
        try {
            await loadRecaptcha(siteKey);
            fill(token, await window.grecaptcha.execute(siteKey, { action }));
        } catch {
            busy = false;
            return report(MESSAGES.blocked);
        }
        fill(challengeToken, challengeToken.value);
        // Next task: a form ignores requestSubmit() while its first submit event is still being dispatched.
        setTimeout(() => {
            busy = false;
            cleared = true;
            try { form.requestSubmit(event.submitter); } catch { form.requestSubmit(); }
        });
    }, true);

    refresh();
}

const start = () => document.querySelectorAll('[data-recaptcha]').forEach(protect);
start();
document.addEventListener('DOMContentLoaded', start);
document.addEventListener('livewire:navigated', start);
