import '../css/demo-vitrine.css';
const form = document.querySelector('#studio-form');
const fields = [...form.querySelectorAll('select')];
const key = document.body.dataset.previewKey || 'codenyr-atelier-preview-v1';
try {
    const saved = JSON.parse(sessionStorage.getItem(key) || '{}');
    for (const field of fields) if ([...field.options].some(option => option.value === saved[field.name])) {
        field.value = saved[field.name];
        document.documentElement.dataset[field.name] = field.value;
    }
} catch { /* Preview remains usable when storage is unavailable. */ }
const toggle = document.querySelector('#studio-toggle');
const panel = document.querySelector('#studio');
toggle.hidden = false;
function setPanel(open) {
    panel.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
    (open ? fields[0] : toggle).focus({preventScroll:true});
}
toggle.addEventListener('click', () => setPanel(panel.hidden));
document.querySelector('#studio-close').addEventListener('click', () => setPanel(false));
document.addEventListener('keydown', event => { if (event.key === 'Escape' && !panel.hidden) setPanel(false); });
form.addEventListener('change', event => {
    const field = event.target;
    document.documentElement.dataset[field.name] = field.value;
    try { sessionStorage.setItem(key, JSON.stringify(Object.fromEntries(fields.map(item => [item.name, item.value])))); } catch {}
    document.querySelector('.studio-status').textContent = `Aperçu mis à jour : ${field.selectedOptions[0].textContent}.`;
});
form.addEventListener('reset', () => {
    fields.forEach(field => delete document.documentElement.dataset[field.name]);
    try { sessionStorage.removeItem(key); } catch {}
    document.querySelector('.studio-status').textContent = 'Le design initial est rétabli.';
});
const menu = document.querySelector('.menu-toggle');
menu.hidden = false;
document.querySelector('.header').classList.add('js-menu');
menu.addEventListener('click', () => {
    const open = menu.getAttribute('aria-expanded') !== 'true';
    menu.setAttribute('aria-expanded', String(open));
    document.querySelector('#navigation').classList.toggle('is-open', open);
});
document.querySelectorAll('[data-filter]').forEach(button => button.addEventListener('click', () => {
    document.querySelectorAll('[data-filter]').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
    let count = 0;
    document.querySelectorAll('[data-category]').forEach(project => {
        project.hidden = button.dataset.filter !== 'Tous' && project.dataset.category !== button.dataset.filter;
        if (!project.hidden) count++;
    });
    document.querySelector('.filter-status').textContent = `${count} projet${count > 1 ? 's' : ''} présenté${count > 1 ? 's' : ''}.`;
}));
const contact = document.querySelector('#demo-contact');
if (contact) {
    contact.addEventListener('submit', event => {
        event.preventDefault();
        document.querySelector('#form-result').textContent = 'La démonstration est réussie : le formulaire est valide. Aucun message ni aucune donnée n’a été envoyé ou enregistré.';
        contact.reset();
    });
    contact.querySelector('[type=submit]').disabled = false;
}
