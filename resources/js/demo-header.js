import '../css/demo-tabbar.css';

// Shared by the demos: a menu button that slides the links out beside it,
// and a header that turns solid once the page leaves the hero.
export function initHeader(header) {
    const burger = header.querySelector('button[aria-controls]');
    const menu = document.getElementById(burger.getAttribute('aria-controls'));
    burger.hidden = false;
    const isOpen = () => header.classList.contains('menu-open');
    const setMenu = (open) => {
        burger.setAttribute('aria-expanded', String(open));
        header.classList.toggle('menu-open', open);
        if (open) menu.querySelector('a')?.focus({ preventScroll: true });
    };
    burger.addEventListener('click', () => setMenu(!isOpen()));
    menu.addEventListener('click', (event) => { if (event.target.closest('a')) setMenu(false); });
    document.addEventListener('click', (event) => {
        if (isOpen() && !menu.contains(event.target) && !burger.contains(event.target)) setMenu(false);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen()) { setMenu(false); burger.focus(); }
    });

    const onScroll = () => header.classList.toggle('is-scrolled', window.scrollY > 40);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
}
