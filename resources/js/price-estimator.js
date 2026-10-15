// Public budget estimator: the visitor answers a few questions, sees an indicative
// range and carries the summary into the quote form.
const euros = (cents) =>
    new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(Math.round(cents / 100)) + ' €';
const monthly = (cents) =>
    new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(cents / 100) + ' €';
const roundUp = (cents, step = 5000) => Math.ceil(cents / step) * step;
const plural = (count, word) => `${count} ${word}${count > 1 ? 's' : ''}`;

export default (data, quoteUrl) => ({
    data,
    step: 1,
    steps: ['Projet', 'Taille', 'Fonctionnalités', 'Suivi'],
    offer: null,
    size: 0,
    picked: {},
    maintenance: '',

    get current() {
        return this.offer ? this.data.offers[this.offer] : null;
    },
    get hasSize() {
        return !!(this.current && this.current.included);
    },
    get available() {
        return this.current ? this.current.options.map((id) => ({ id, ...this.data.options[id] })) : [];
    },
    get extraUnits() {
        return this.hasSize ? Math.max(0, this.size - this.current.included) : 0;
    },
    get lines() {
        if (!this.current) return [];
        const lines = [{ label: `${this.current.name} (base)`, amount: this.current.amount }];
        if (this.extraUnits && this.current.extra) {
            lines.push({ label: `${plural(this.extraUnits, this.current.unit)} en plus`, amount: this.extraUnits * this.current.extra });
        }
        for (const option of this.available) {
            const quantity = this.picked[option.id] || 0;
            if (!quantity) continue;
            lines.push({
                label: option.unit ? `${option.name} × ${quantity}` : option.name,
                amount: option.amount * quantity,
                starting: option.starting,
            });
        }
        return lines;
    },
    get total() {
        return this.lines.reduce((sum, line) => sum + line.amount, 0);
    },
    get open() {
        // "Starting at" prices and the custom offer can only grow once the details are known.
        return this.offer === 'custom' || this.lines.some((line) => line.starting);
    },
    get low() {
        return roundUp(this.total, 1000);
    },
    get high() {
        return roundUp(this.total * (1 + this.data.margin));
    },
    get plan() {
        return this.maintenance ? this.data.maintenance[this.maintenance] : null;
    },

    euros,
    monthly,
    plural,

    chooseOffer(key) {
        this.offer = key;
        this.size = this.data.offers[key].included || 0;
        // Drop options that do not exist for the new offer.
        const allowed = this.data.offers[key].options;
        this.picked = Object.fromEntries(Object.entries(this.picked).filter(([id]) => allowed.includes(Number(id))));
        this.next();
    },
    toggle(id) {
        this.picked[id] = this.picked[id] ? 0 : 1;
    },
    bump(id, delta) {
        this.picked[id] = Math.min(10, Math.max(0, (this.picked[id] || 0) + delta));
    },
    resize(delta) {
        this.size = Math.min(40, Math.max(1, this.size + delta));
    },
    go(step) {
        if (step > 1 && !this.offer) return;
        this.step = step === 2 && !this.hasSize ? (this.step > 2 ? 1 : 3) : step;
        this.$nextTick(() => this.$refs.panel?.focus({ preventScroll: true }));
    },
    next() {
        this.go(this.step === 1 && !this.hasSize ? 3 : Math.min(4, this.step + 1));
    },
    back() {
        this.go(this.step === 3 && !this.hasSize ? 1 : Math.max(1, this.step - 1));
    },
    restart() {
        Object.assign(this, { offer: null, size: 0, picked: {}, maintenance: '' });
        this.go(1);
    },
    get summary() {
        const text = ['Estimation réalisée sur le site :', ...this.lines.map((line) => `- ${line.label}`)];
        if (this.hasSize) text.splice(2, 0, `- Taille souhaitée : ${plural(this.size, this.current.unit)}`);
        text.push(`- Fourchette indicative : ${euros(this.low)} – ${euros(this.high)}${this.open ? ' (à affiner)' : ''}`);
        text.push(`- Maintenance : ${this.plan ? this.plan.name : 'aucune pour le moment'}`);
        return text.join('\n');
    },
    get quoteLink() {
        const params = new URLSearchParams({ offer: this.current ? this.current.name : '', estimate: this.summary });
        return `${quoteUrl}?${params}`;
    },
});
