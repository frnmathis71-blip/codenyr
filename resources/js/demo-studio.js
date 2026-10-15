import '../css/demo-studio.css';

/**
 * Shared "Personnaliser ce site" panel for the product demos.
 * Settings become data-* attributes on <html>; the demo stylesheets react to them.
 * State survives page changes (sessionStorage) and can be shared as a link (?cz=…).
 */
export function initStudio() {
    const panel = document.querySelector('#cz-panel');
    if (!panel) return;

    const root = document.documentElement;
    const form = panel.querySelector('#cz-form');
    const toggle = document.querySelector('#cz-toggle');
    const status = panel.querySelector('.cz-status');
    const accentInput = panel.querySelector('#cz-accent');
    const accentReset = panel.querySelector('#cz-accent-reset');
    const quote = panel.querySelector('#cz-quote');
    const storageKey = panel.dataset.key;
    const accentVar = panel.dataset.accentVar;
    const defaults = JSON.parse(panel.dataset.defaults || '{}');
    const presets = JSON.parse(panel.dataset.presets || '{}');
    const selects = [...form.querySelectorAll('select')];
    const allowed = Object.fromEntries(selects.map((select) => [select.name, [...select.options].map((option) => option.value)]));
    allowed.palette = [...form.querySelectorAll('input[name=palette]')].map((input) => input.value);
    for (const [key, values] of Object.entries(allowed)) if (!values.includes(defaults[key])) defaults[key] = values[0];
    const brandNodes = [...document.querySelectorAll('[data-brand]')];
    brandNodes.forEach((node) => { node.dataset.brandOriginal = node.textContent; });

    const blank = () => ({ ...defaults, brand: '', accent: '' });
    let state = blank();

    function sanitize(input) {
        const clean = {};
        if (!input || typeof input !== 'object') return clean;
        for (const [key, values] of Object.entries(allowed)) if (values.includes(input[key])) clean[key] = input[key];
        if (typeof input.brand === 'string') clean.brand = input.brand.slice(0, 32);
        if (typeof input.accent === 'string' && /^#[0-9a-f]{6}$/i.test(input.accent)) clean.accent = input.accent;
        return clean;
    }

    function load() {
        try {
            const shared = new URLSearchParams(location.search).get('cz');
            if (shared) return sanitize(JSON.parse(shared));
        } catch { /* An invalid shared link falls back to the saved preview. */ }
        try {
            return sanitize(JSON.parse(sessionStorage.getItem(storageKey) || '{}'));
        } catch {
            return {};
        }
    }

    // Only what differs from the product defaults: keeps links and quote summaries short.
    function changes() {
        return Object.fromEntries(Object.entries(state).filter(([key, value]) => value !== '' && value !== defaults[key]));
    }

    function label(name, value) {
        if (name === 'palette') return form.querySelector(`input[name=palette][value="${value}"]`)?.closest('label').querySelector('.cz-swatch-label').textContent;
        if (name === 'brand') return value;
        if (name === 'accent') return value;
        const select = form.querySelector(`select[name="${name}"]`);
        return select ? [...select.options].find((option) => option.value === value)?.textContent : value;
    }

    function fieldName(name) {
        if (name === 'palette') return 'Palette';
        if (name === 'brand') return 'Nom';
        if (name === 'accent') return 'Couleur';
        return form.querySelector(`label[for="cz-${name}"]`)?.textContent ?? name;
    }

    function shareUrl() {
        const url = new URL(location.href);
        const diff = changes();
        if (Object.keys(diff).length) url.searchParams.set('cz', JSON.stringify(diff));
        else url.searchParams.delete('cz');
        return url.toString();
    }

    function toHex(color) {
        const value = color.trim();
        if (/^#[0-9a-f]{6}$/i.test(value)) return value;
        if (/^#[0-9a-f]{3}$/i.test(value)) return '#' + [...value.slice(1)].map((c) => c + c).join('');
        const rgb = value.match(/\d+/g);
        return rgb && rgb.length >= 3 ? '#' + rgb.slice(0, 3).map((n) => Number(n).toString(16).padStart(2, '0')).join('') : '#000000';
    }

    function apply(announce) {
        for (const name of Object.keys(allowed)) root.dataset[name] = state[name];
        brandNodes.forEach((node) => { node.textContent = state.brand.trim() || node.dataset.brandOriginal; });
        if (state.accent) {
            root.style.setProperty(accentVar, state.accent);
            root.dataset.accent = 'custom';
        } else {
            root.style.removeProperty(accentVar);
            delete root.dataset.accent;
        }

        // Reflect state in the controls.
        selects.forEach((select) => { select.value = state[select.name]; });
        form.querySelectorAll('input[name=palette]').forEach((input) => { input.checked = input.value === state.palette; });
        form.querySelector('#cz-brand').value = state.brand;
        accentInput.value = state.accent || toHex(getComputedStyle(root).getPropertyValue(accentVar));
        accentReset.hidden = !state.accent;
        panel.querySelectorAll('[data-preset]').forEach((button) => {
            const preset = presets[button.dataset.preset] || {};
            button.setAttribute('aria-pressed', String(Object.entries(preset).every(([key, value]) => state[key] === value)));
        });

        const diff = changes();
        const summary = Object.entries(diff).map(([key, value]) => `${fieldName(key)} : ${label(key, value)}`).join(' · ');
        const url = new URL(quote.href);
        url.searchParams.set('design', `Démo ${panel.dataset.demo}${summary ? ' — ' + summary : ' — design d’origine'}\nLien : ${shareUrl()}`);
        quote.href = url.toString();

        try { sessionStorage.setItem(storageKey, JSON.stringify(diff)); } catch { /* Preview still works without storage. */ }
        if (announce) status.textContent = announce;
    }

    state = { ...blank(), ...load() };
    apply();

    // Panel open / close.
    toggle.hidden = false;
    function setOpen(open) {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        (open ? form.querySelector('#cz-brand') : toggle).focus({ preventScroll: true });
    }
    toggle.addEventListener('click', () => setOpen(panel.hidden));
    panel.querySelector('#cz-close').addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !panel.hidden) setOpen(false); });

    form.addEventListener('input', (event) => {
        const field = event.target;
        if (field.name === 'brand') { state.brand = field.value; apply(); }
        if (field.name === 'accent') { state.accent = field.value; apply(); }
    });
    form.addEventListener('change', (event) => {
        const field = event.target;
        if (field.name in allowed) {
            state[field.name] = field.value;
            apply(`Aperçu mis à jour : ${label(field.name, field.value)}.`);
        }
    });
    accentReset.addEventListener('click', () => { state.accent = ''; apply('Couleur de la palette rétablie.'); });
    panel.querySelectorAll('[data-preset]').forEach((button) => button.addEventListener('click', () => {
        state = { ...state, ...presets[button.dataset.preset], accent: '' };
        apply(`Ambiance « ${button.querySelector('strong').textContent} » appliquée.`);
    }));
    form.addEventListener('reset', (event) => {
        event.preventDefault();
        state = blank();
        apply('Le design d’origine est rétabli.');
    });
    panel.querySelector('#cz-share').addEventListener('click', async () => {
        const link = shareUrl();
        try {
            await navigator.clipboard.writeText(link);
            status.textContent = 'Lien copié : il rouvre la démo avec vos réglages.';
        } catch {
            window.prompt('Copiez ce lien :', link);
        }
    });
}
