import '../css/demo-tabbar.css';

// Shared by the demos: a menu button that slides the links out beside it,
// and a header that turns solid once the page leaves the hero.
// The menu stays open from page to page until the visitor closes it (remembered per demo for the session).
export function initHeader(header) {
    const burger = header.querySelector('button[aria-controls]');
    const menu = document.getElementById(burger.getAttribute('aria-controls'));
    const storageKey = `codenyr-demo-menu:${location.pathname.split('/')[2] || ''}`;
    burger.hidden = false;
    const isOpen = () => header.classList.contains('menu-open');
    const setMenu = (open, focus = true) => {
        burger.setAttribute('aria-expanded', String(open));
        header.classList.toggle('menu-open', open);
        try { sessionStorage.setItem(storageKey, open ? '1' : ''); } catch { /* Not remembered without storage. */ }
        if (open && focus) menu.querySelector('a')?.focus({ preventScroll: true });
    };
    try {
        if (sessionStorage.getItem(storageKey)) {
            // Reopened on page load: shown at once, without the slide-in.
            header.classList.add('is-restoring');
            setMenu(true, false);
            setTimeout(() => header.classList.remove('is-restoring'), 60);
        }
    } catch { /* Starts closed without storage. */ }
    burger.addEventListener('click', () => setMenu(!isOpen()));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen()) { setMenu(false); burger.focus(); }
    });

    const onScroll = () => header.classList.toggle('is-scrolled', window.scrollY > 40);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
}
