// Site header: the menu stays open from page to page until the visitor closes it
// (remembered for the browser session), and the header turns solid once the page scrolls.
const storageKey = 'codenyr-menu-open';

export function siteHeader() {
    return {
        open: false,
        scrolled: window.scrollY > 40,
        init() {
            try {
                if (sessionStorage.getItem(storageKey)) {
                    // Reopened on page load: shown at once, without the slide-in.
                    this.$el.classList.add('is-restoring');
                    this.open = true;
                    setTimeout(() => this.$el.classList.remove('is-restoring'), 60);
                }
            } catch { /* Starts closed without storage. */ }
            this.$watch('open', (value) => {
                try { sessionStorage.setItem(storageKey, value ? '1' : ''); } catch { /* Not remembered without storage. */ }
            });
        },
    };
}
