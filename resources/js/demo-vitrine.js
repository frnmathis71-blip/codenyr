import '../css/demo-vitrine.css';
import { initStudio } from './demo-studio.js';
initStudio();
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
