import '../css/demo-atelier.css';
import { initStudio } from './demo-studio.js';
initStudio();
document.documentElement.classList.add('js');

const header = document.querySelector('.header');
const burger = document.querySelector('.burger');
const menu = document.querySelector('#navigation');
burger.hidden = false;
const setMenu = (open) => {
    burger.setAttribute('aria-expanded', String(open));
    burger.querySelector('.burger-label').textContent = open ? 'Fermer' : 'Menu';
    header.classList.toggle('menu-open', open);
    document.documentElement.classList.toggle('no-scroll', open);
    if (open) menu.querySelector('a')?.focus({ preventScroll: true });
};
burger.addEventListener('click', () => setMenu(burger.getAttribute('aria-expanded') !== 'true'));
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && header.classList.contains('menu-open')) { setMenu(false); burger.focus(); }
});

// The header turns solid once the page leaves the hero.
const onScroll = () => header.classList.toggle('is-scrolled', window.scrollY > 40);
onScroll();
window.addEventListener('scroll', onScroll, { passive: true });

// Sections fade in as they enter the viewport.
const revealed = document.querySelectorAll('[data-reveal]');
if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
    }), { rootMargin: '0px 0px -8% 0px' });
    revealed.forEach((node) => observer.observe(node));
} else {
    revealed.forEach((node) => node.classList.add('is-visible'));
}

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
