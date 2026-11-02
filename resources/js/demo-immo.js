import './demo-atelier.js';
import '../css/demo-immo.css';

// Seuil Immobilier: listings, visit booking, client files and the agency tool.
// Everything is fictional and kept in this browser (localStorage), like the Canopée demo.
const storageKey = 'codenyr-seuil-v3';
const images = { maison: '/images/atelier/maison.webp', salon: '/images/atelier/interieur.webp', matiere: '/images/atelier/matiere.webp' };
const agents = { vente: 'Claire, conseillère achat-vente', location: 'Hugo, conseiller location' };
const documents = { identite: 'Pièce d’identité', domicile: 'Justificatif de domicile', revenus: '3 derniers justificatifs de revenus', impots: 'Dernier avis d’imposition' };
const statuses = ['Nouveau', 'À compléter', 'Complet', 'Offre en cours', 'Clos'];
const urls = JSON.parse(document.body.dataset.urls || '{}');

const pad = (n) => String(n).padStart(2, '0');
const dayKey = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
const addDays = (date, days) => { const copy = new Date(date); copy.setDate(copy.getDate() + days); return copy; };
const parseSlot = (slot) => { const [day, time] = slot.split('T'); const [y, m, d] = day.split('-').map(Number); const [h, min] = time.split(':').map(Number); return new Date(y, m - 1, d, h, min); };
const longDate = (date) => date.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
const timeLabel = (slot) => { const [h, m] = slot.split('T')[1].split(':'); return `${Number(h)} h ${m}`; };
const slotLabel = (slot) => `${longDate(parseSlot(slot))} à ${timeLabel(slot)}`;
const capitalize = (text) => text.charAt(0).toUpperCase() + text.slice(1);
const svg = (path) => `<svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="${path}"/></svg>`;
const clockIcon = svg('M12 7v5l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z');
const euros = (value) => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(value);
const priceLabel = (item) => item.mode === 'Location' ? `${euros(item.price)} / mois` : euros(item.price);
const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const uid = (prefix) => `${prefix}${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`;

function defaults() {
    const today = new Date();
    const nextOpenDay = (offset) => { let date = addDays(today, offset); while (date.getDay() === 0) date = addDays(date, 1); return dayKey(date); };
    return {
        properties: [
            { id: 'p1', title: 'Maison familiale avec jardin', type: 'Maison', mode: 'Vente', area: 'Quartier des Jardins', price: 289000, surface: 128, rooms: 6, bedrooms: 4, dpe: 'C', image: 'maison', status: 'Disponible', featured: true, text: 'Une maison de plain-pied prolongée par un jardin arboré. Séjour traversant, cuisine ouverte, quatre chambres et un atelier dans la dépendance.' },
            { id: 'p2', title: 'Appartement lumineux en centre historique', type: 'Appartement', mode: 'Vente', area: 'Centre historique', price: 189000, surface: 82, rooms: 4, bedrooms: 2, dpe: 'D', image: 'salon', status: 'Disponible', featured: true, text: 'Au deuxième étage d’un immeuble ancien, un appartement baigné de lumière : parquet, hauteur sous plafond et vue dégagée sur les toits.' },
            { id: 'p3', title: 'Loft aux matières brutes', type: 'Loft', mode: 'Vente', area: 'Bords de rivière', price: 245000, surface: 110, rooms: 4, bedrooms: 2, dpe: 'C', image: 'matiere', status: 'Sous compromis', featured: true, text: 'Un ancien atelier transformé en loft : béton ciré, bois, grandes verrières et un espace de vie de 60 m² ouvert sur la rivière.' },
            { id: 'p4', title: 'Maison de ville rénovée', type: 'Maison', mode: 'Vente', area: 'Faubourg', price: 214000, surface: 96, rooms: 5, bedrooms: 3, dpe: 'B', image: 'maison', status: 'Disponible', featured: false, text: 'Rénovée avec soin, cette maison de ville offre trois chambres, une cour intime et un garage. Commerces et écoles à pied.' },
            { id: 'p5', title: 'Deux pièces meublé proche gare', type: 'Appartement', mode: 'Location', area: 'Quartier de la gare', price: 690, surface: 44, rooms: 2, bedrooms: 1, dpe: 'E', image: 'salon', status: 'Disponible', featured: false, text: 'Un deux pièces meublé et fonctionnel, idéal pour un premier logement ou un séjour professionnel. Disponible immédiatement.' },
            { id: 'p6', title: 'Appartement avec terrasse', type: 'Appartement', mode: 'Location', area: 'Bords de rivière', price: 980, surface: 76, rooms: 3, bedrooms: 2, dpe: 'C', image: 'matiere', status: 'Disponible', featured: false, text: 'Trois pièces avec une terrasse de 20 m² plein sud, une cuisine équipée et une place de stationnement.' },
        ],
        clients: [
            { id: 'c1', name: 'Camille R.', email: 'camille@seuil.demo', phone: '06 00 00 00 01', project: 'Achat', budget: 250000, status: 'À compléter', docs: { identite: 'carte-identite.pdf', revenus: 'bulletins-salaire.pdf' }, notes: 'Recherche une maison avec extérieur, proche des écoles.', created: dayKey(addDays(today, -6)) },
            { id: 'c2', name: 'Malik B.', email: 'malik@seuil.demo', phone: '06 00 00 00 02', project: 'Location', budget: 900, status: 'Complet', docs: { identite: 'piece-identite.pdf', domicile: 'facture.pdf', revenus: 'revenus.pdf', impots: 'avis-imposition.pdf' }, notes: 'Mutation professionnelle, emménagement souhaité le mois prochain.', created: dayKey(addDays(today, -3)) },
            { id: 'c3', name: 'Inès L.', email: 'ines@seuil.demo', phone: '06 00 00 00 03', project: 'Achat', budget: 210000, status: 'Nouveau', docs: {}, notes: 'Premier achat, appartement lumineux en centre-ville.', created: dayKey(addDays(today, -1)) },
            { id: 'c4', name: 'Thomas et Léa G.', email: 'thomas@seuil.demo', phone: '06 00 00 00 04', project: 'Achat', budget: 300000, status: 'Offre en cours', docs: { identite: 'identites.pdf', domicile: 'facture.pdf', revenus: 'revenus.pdf', impots: 'avis.pdf' }, notes: 'Offre déposée sur la maison familiale, réponse attendue cette semaine.', created: dayKey(addDays(today, -20)) },
            { id: 'c5', name: 'Hélène D.', email: 'helene@seuil.demo', phone: '06 00 00 00 05', project: 'Vente', budget: 214000, status: 'À compléter', docs: { identite: 'cni.pdf' }, notes: 'Vend sa maison de ville, mandat signé. Diagnostics à recevoir.', created: dayKey(addDays(today, -9)) },
            { id: 'c6', name: 'Bastien P.', email: 'bastien@seuil.demo', phone: '06 00 00 00 06', project: 'Location', budget: 700, status: 'Clos', docs: { identite: 'id.pdf', domicile: 'domicile.pdf', revenus: 'revenus.pdf', impots: 'impots.pdf' }, notes: 'Bail signé le mois dernier.', created: dayKey(addDays(today, -40)) },
        ],
        bookings: [
            { id: 'b1', propertyId: 'p2', clientId: 'c1', name: 'Camille R.', email: 'camille@seuil.demo', slot: `${nextOpenDay(1)}T10:30`, status: 'Confirmée' },
            { id: 'b2', propertyId: 'p5', clientId: 'c2', name: 'Malik B.', email: 'malik@seuil.demo', slot: `${nextOpenDay(2)}T17:15`, status: 'Confirmée' },
            { id: 'b3', propertyId: 'p1', clientId: 'c4', name: 'Thomas et Léa G.', email: 'thomas@seuil.demo', slot: `${nextOpenDay(1)}T15:00`, status: 'Confirmée' },
            { id: 'b4', propertyId: 'p2', clientId: 'c3', name: 'Inès L.', email: 'ines@seuil.demo', slot: `${nextOpenDay(3)}T11:00`, status: 'Confirmée' },
            { id: 'b5', propertyId: 'p6', clientId: 'c2', name: 'Malik B.', email: 'malik@seuil.demo', slot: `${nextOpenDay(3)}T16:30`, status: 'Confirmée' },
            { id: 'b6', propertyId: 'p4', clientId: 'c5', name: 'Hélène D.', email: 'helene@seuil.demo', slot: `${nextOpenDay(4)}T09:30`, status: 'Confirmée' },
            { id: 'b7', propertyId: 'p1', clientId: 'c1', name: 'Camille R.', email: 'camille@seuil.demo', slot: `${dayKey(addDays(today, -2))}T14:00`, status: 'Effectuée' },
            { id: 'b8', propertyId: 'p5', clientId: 'c6', name: 'Bastien P.', email: 'bastien@seuil.demo', slot: `${dayKey(addDays(today, -5))}T17:15`, status: 'Effectuée' },
        ],
        settings: { start: '09:30', end: '18:00', duration: 45, days: [1, 2, 3, 4, 5, 6] },
    };
}

let data = defaults();
let persistent = true;
try {
    const saved = JSON.parse(localStorage.getItem(storageKey) || 'null');
    if (saved && ['properties', 'clients', 'bookings'].every((key) => Array.isArray(saved[key])) && saved.settings) data = saved;
} catch { persistent = false; }
function save() { try { localStorage.setItem(storageKey, JSON.stringify(data)); } catch { persistent = false; } }
const property = (id) => data.properties.find((item) => item.id === id);
const client = (id) => data.clients.find((item) => item.id === id);
const clientByEmail = (email) => data.clients.find((item) => item.email.toLowerCase() === email.trim().toLowerCase());
const docsDone = (item) => Object.keys(documents).filter((key) => item.docs?.[key]).length;
const activeBookings = () => data.bookings.filter((booking) => booking.status !== 'Annulée');

// Slots of one day, from the agency opening hours, minus past times and booked visits.
function daySlots(date) {
    const { start, end, duration, days } = data.settings;
    if (!days.includes(date.getDay())) return null;
    const toMinutes = (time) => { const [h, m] = time.split(':').map(Number); return h * 60 + m; };
    const now = new Date();
    const taken = new Set(activeBookings().map((booking) => booking.slot));
    const slots = [];
    for (let minutes = toMinutes(start); minutes + duration <= toMinutes(end); minutes += duration) {
        const slot = `${dayKey(date)}T${pad(Math.floor(minutes / 60))}:${pad(minutes % 60)}`;
        if (parseSlot(slot) > now) slots.push({ slot, free: !taken.has(slot) });
    }
    return slots;
}

/* ---------- Public listings ---------- */
function card(item) {
    return `<article class="project estate-card"><a class="project-media" href="${esc(urls.bien)}?ref=${esc(item.id)}"><img src="${images[item.image]}" alt="${esc(item.title)}, photographie d’ambiance" loading="lazy" width="1000" height="750">${item.status !== 'Disponible' ? `<span class="estate-flag">${esc(item.status)}</span>` : ''}</a>
        <div class="project-body"><span class="eyebrow">${esc(item.type)} · ${esc(item.area)}</span><h3><a href="${esc(urls.bien)}?ref=${esc(item.id)}">${esc(item.title)}</a></h3>
        <p class="estate-price">${priceLabel(item)}</p><ul class="estate-specs"><li>${item.surface} m²</li><li>${item.rooms} pièces</li><li>${item.bedrooms} ch.</li><li>DPE ${esc(item.dpe)}</li></ul></div></article>`;
}
document.querySelectorAll('[data-estate="featured"]').forEach((list) => {
    list.innerHTML = data.properties.filter((item) => item.featured && item.status !== 'Vendu').slice(0, 3).map(card).join('');
});

const filters = document.querySelector('#estate-filters');
if (filters) {
    const list = document.querySelector('[data-estate="list"]');
    const params = new URLSearchParams(location.search);
    let mode = params.get('mode') === 'Location' ? 'Location' : 'Vente';
    if (params.get('type')) filters.type.value = params.get('type');
    if (params.get('budget')) filters.budget.value = params.get('budget');
    const render = () => {
        filters.querySelectorAll('[data-mode]').forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.mode === mode)));
        const budget = Number(filters.budget.value) || Infinity;
        const items = data.properties.filter((item) => item.mode === mode && item.status !== 'Vendu' && (!filters.type.value || item.type === filters.type.value) && item.price <= budget && item.rooms >= Number(filters.rooms.value));
        list.innerHTML = items.map(card).join('') || '<p class="estate-empty">Aucun bien ne correspond à ces critères pour le moment. Élargissez la recherche ou contactez l’agence.</p>';
        document.querySelector('.estate-count').textContent = `${items.length} bien${items.length > 1 ? 's' : ''} ${mode === 'Vente' ? 'à vendre' : 'à louer'}.`;
    };
    filters.querySelectorAll('[data-mode]').forEach((button) => button.addEventListener('click', () => { mode = button.dataset.mode; render(); }));
    filters.addEventListener('input', render);
    filters.addEventListener('submit', (event) => event.preventDefault());
    render();
}

const detail = document.querySelector('#estate-detail');
if (detail) {
    const item = property(new URLSearchParams(location.search).get('ref')) || data.properties[0];
    detail.querySelector('[data-detail-title]').textContent = item.title;
    document.title = `${item.title} — Seuil Immobilier · Démonstration Codenyr`;
    const scale = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
    detail.querySelector('[data-detail-body]').innerHTML = `<div class="estate-detail-grid">
        <figure class="estate-photo"><img src="${images[item.image]}" alt="${esc(item.title)}, photographie d’ambiance" width="1000" height="750"></figure>
        <aside class="estate-summary"><p class="eyebrow">${esc(item.type)} · ${esc(item.area)}</p><p class="estate-price">${priceLabel(item)}</p>
            <dl class="estate-facts"><div><dt>Surface</dt><dd>${item.surface} m²</dd></div><div><dt>Pièces</dt><dd>${item.rooms}</dd></div><div><dt>Chambres</dt><dd>${item.bedrooms}</dd></div><div><dt>Statut</dt><dd>${esc(item.status)}</dd></div></dl>
            <div class="dpe" aria-label="Diagnostic de performance énergétique : classe ${esc(item.dpe)}">${scale.map((letter) => `<span class="dpe-${letter.toLowerCase()}${letter === item.dpe ? ' is-current' : ''}">${letter}</span>`).join('')}</div>
            ${item.status === 'Disponible' ? `<a class="button" href="${esc(urls.visite)}?ref=${esc(item.id)}">Réserver une visite</a>` : `<p class="estate-note">Ce bien est ${esc(item.status.toLowerCase())}. Réservez une visite d’un bien similaire ou contactez l’agence.</p>`}
            <p class="estate-note">${esc(agents[item.mode === 'Location' ? 'location' : 'vente'])} vous accompagne.</p></aside></div>
        <div class="estate-description"><h2>Le bien</h2><p>${esc(item.text)}</p><p class="estate-note">Annonce fictive, photographie d’ambiance. Honoraires et diagnostics détaillés sur demande dans une vraie annonce.</p></div>`;
}

/* ---------- Visit booking ---------- */
function icsFile(booking) {
    const item = property(booking.propertyId);
    const start = parseSlot(booking.slot);
    const end = new Date(start.getTime() + data.settings.duration * 60000);
    const stamp = (date) => `${date.getFullYear()}${pad(date.getMonth() + 1)}${pad(date.getDate())}T${pad(date.getHours())}${pad(date.getMinutes())}00`;
    const text = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Seuil Immobilier//Demo//FR', 'BEGIN:VEVENT', `UID:${booking.id}@seuil.demo`, `DTSTART:${stamp(start)}`, `DTEND:${stamp(end)}`, `SUMMARY:Visite – ${item?.title ?? 'Seuil Immobilier'}`, `LOCATION:${item?.area ?? ''}`, 'END:VEVENT', 'END:VCALENDAR'].join('\r\n');
    return URL.createObjectURL(new Blob([text], { type: 'text/calendar' }));
}

const booking = document.querySelector('#booking-form');
if (booking) {
    const select = booking.property;
    const strip = booking.querySelector('[data-date-strip]');
    const periods = booking.querySelector('[data-slot-periods]');
    const choice = booking.querySelector('[data-slot-choice]');
    const error = booking.querySelector('[data-booking-error]');
    const result = document.querySelector('[data-booking-result]');
    let offset = 0;
    let day = null;
    let chosen = null;
    const bookable = data.properties.filter((item) => item.status === 'Disponible');
    select.innerHTML = bookable.map((item) => `<option value="${esc(item.id)}">${esc(item.title)} — ${priceLabel(item)}</option>`).join('');
    const wanted = new URLSearchParams(location.search).get('ref');
    if (bookable.some((item) => item.id === wanted)) select.value = wanted;
    const showProperty = () => {
        const item = property(select.value);
        booking.querySelector('[data-booking-property]').innerHTML = item ? `<img src="${images[item.image]}" alt="" width="1000" height="750"><div><strong>${esc(item.title)}</strong><span>${esc(item.area)} · ${item.surface} m² · ${priceLabel(item)}</span><span>Avec ${esc(agents[item.mode === 'Location' ? 'location' : 'vente'])}</span></div>` : '';
        booking.project.value = item?.mode === 'Location' ? 'Location' : 'Achat';
    };
    // Step 2: a strip of open days, then that day's times grouped into morning and afternoon.
    const renderSummary = () => {
        choice.classList.toggle('is-set', Boolean(chosen));
        if (!chosen) { choice.innerHTML = '<p>Choisissez un jour, puis un horaire.</p>'; return; }
        const item = property(select.value);
        const end = new Date(parseSlot(chosen).getTime() + data.settings.duration * 60000);
        choice.innerHTML = `<span class="slot-summary-icon">${clockIcon}</span><div><strong>${esc(capitalize(longDate(parseSlot(chosen))))}</strong><span>${timeLabel(chosen)} – ${end.getHours()} h ${pad(end.getMinutes())} · ${esc(agents[item?.mode === 'Location' ? 'location' : 'vente'])}</span></div>`;
    };
    const renderSlots = () => {
        const slots = daySlots(parseSlot(`${day}T00:00`)) || [];
        periods.innerHTML = [['Matin', (hour) => hour < 12], ['Après-midi', (hour) => hour >= 12]].map(([label, inPeriod]) => {
            const list = slots.filter(({ slot }) => inPeriod(Number(slot.slice(11, 13))));
            return list.length ? `<div class="slot-period"><p>${label}</p><div class="slot-chips">${list.map(({ slot, free }) => `<button type="button" class="slot-btn" data-slot="${slot}" aria-pressed="${slot === chosen}" ${free ? '' : 'disabled'} aria-label="${esc(free ? slotLabel(slot) : `${slotLabel(slot)}, déjà réservé`)}">${timeLabel(slot)}</button>`).join('')}</div></div>` : '';
        }).join('') || '<p class="slot-none">Plus aucun créneau ce jour-là.</p>';
    };
    const renderDays = () => {
        const days = Array.from({ length: 7 }, (_, index) => addDays(new Date(), offset * 7 + index)).map((date) => ({ date, slots: daySlots(date) })).filter(({ slots }) => slots !== null);
        const freeCount = (slots) => slots.filter(({ free }) => free).length;
        if (!days.some(({ date }) => dayKey(date) === day)) day = dayKey((days.find(({ slots }) => freeCount(slots)) || days[0] || { date: new Date() }).date);
        booking.querySelector('[data-month-label]').textContent = capitalize(parseSlot(`${day}T00:00`).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' }));
        booking.querySelector('[data-week="-1"]').disabled = offset === 0;
        strip.innerHTML = days.map(({ date, slots }) => {
            const free = freeCount(slots);
            return `<button type="button" class="date-chip" data-day="${dayKey(date)}" aria-pressed="${dayKey(date) === day}" ${free ? '' : 'disabled'} aria-label="${esc(`${longDate(date)}, ${free ? `${free} créneaux libres` : 'complet'}`)}"><span>${date.toLocaleDateString('fr-FR', { weekday: 'short' }).replace('.', '')}</span><strong>${date.getDate()}</strong><small>${free ? `${free} libre${free > 1 ? 's' : ''}` : 'Complet'}</small></button>`;
        }).join('');
        renderSlots();
        renderSummary();
    };
    strip.addEventListener('click', (event) => {
        const button = event.target.closest('[data-day]');
        if (!button) return;
        day = button.dataset.day;
        renderDays();
    });
    periods.addEventListener('click', (event) => {
        const button = event.target.closest('[data-slot]');
        if (!button) return;
        chosen = button.dataset.slot;
        renderSlots();
        renderSummary();
    });
    booking.querySelectorAll('[data-week]').forEach((button) => button.addEventListener('click', () => { offset = Math.max(0, offset + Number(button.dataset.week)); renderDays(); }));
    select.addEventListener('change', showProperty);
    booking.addEventListener('submit', (event) => {
        event.preventDefault();
        error.textContent = '';
        if (!select.value) { error.textContent = 'Choisissez un bien à visiter.'; return; }
        if (!chosen || activeBookings().some((item) => item.slot === chosen)) { error.textContent = 'Choisissez un créneau libre dans le calendrier.'; return; }
        if (!booking.name.value.trim() || !booking.email.checkValidity() || !booking.email.value) { error.textContent = 'Indiquez votre nom et une adresse e-mail valide.'; return; }
        let file = clientByEmail(booking.email.value);
        if (!file && booking.dossier.checked) {
            file = { id: uid('c'), name: booking.name.value.trim(), email: booking.email.value.trim(), phone: booking.phone.value.trim(), project: booking.project.value, budget: property(select.value).price, status: 'Nouveau', docs: {}, notes: '', created: dayKey(new Date()) };
            data.clients.unshift(file);
        }
        const visit = { id: uid('b'), propertyId: select.value, clientId: file?.id ?? null, name: booking.name.value.trim(), email: booking.email.value.trim(), slot: chosen, status: 'Confirmée' };
        data.bookings.push(visit);
        save();
        const item = property(visit.propertyId);
        result.hidden = false;
        result.innerHTML = `<p class="eyebrow">Visite confirmée</p><h2>À bientôt, ${esc(visit.name.split(' ')[0])}.</h2>
            <p><strong>${esc(item.title)}</strong><br>${esc(slotLabel(visit.slot))} · ${esc(item.area)}</p>
            <p>${file ? 'Votre dossier client est ouvert : déposez vos justificatifs pour gagner du temps le jour de la visite.' : 'Vous pouvez créer votre dossier client à tout moment.'}</p>
            <div class="result-actions"><a class="button" href="${icsFile(visit)}" download="visite-seuil.ics">Ajouter à mon agenda</a>${file ? `<a class="text-link" href="${esc(urls.espace)}?email=${encodeURIComponent(file.email)}">Ouvrir mon espace client</a>` : ''}</div>
            <p class="demo-note">${persistent ? 'Enregistré dans ce navigateur, visible dans l’espace agence.' : 'Stockage indisponible : la réservation reste sur cette page.'}</p>`;
        result.focus();
        chosen = null;
        renderDays();
    });
    select.addEventListener('change', renderSummary);
    showProperty();
    renderDays();
}

/* ---------- Estimation ---------- */
const estimate = document.querySelector('#estimate-form');
if (estimate) {
    const perSquareMetre = { 'Centre historique': 2350, 'Bords de rivière': 2500, 'Quartier des Jardins': 2200, Faubourg: 1950, 'Quartier de la gare': 1700 };
    const result = document.querySelector('[data-estimate-result]');
    estimate.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!estimate.reportValidity()) return;
        const typeFactor = { Maison: 1.05, Appartement: 1, Loft: 1.08 }[estimate.type.value];
        const value = Number(estimate.surface.value) * perSquareMetre[estimate.area.value] * typeFactor * Number(estimate.state.value) * Number(estimate.outside.value);
        const round = (n) => Math.round(n / 1000) * 1000;
        let file = clientByEmail(estimate.email.value);
        if (!file) {
            file = { id: uid('c'), name: estimate.name.value.trim(), email: estimate.email.value.trim(), phone: '', project: 'Vente', budget: round(value), status: 'Nouveau', docs: {}, notes: `Estimation en ligne : ${estimate.type.value}, ${estimate.surface.value} m², ${estimate.area.value}.`, created: dayKey(new Date()) };
            data.clients.unshift(file);
            save();
        }
        result.hidden = false;
        result.innerHTML = `<p class="eyebrow">Première estimation</p><h2>${euros(round(value * 0.93))} – ${euros(round(value * 1.07))}</h2>
            <p>${esc(estimate.type.value)} de ${Number(estimate.surface.value)} m², ${esc(estimate.area.value)}. Cette fourchette s’affine avec une visite : exposition, étage, travaux, prestations.</p>
            <p>Votre dossier vendeur est ouvert au nom de ${esc(file.name)}.</p>
            <div class="result-actions"><a class="button" href="${esc(urls.visite)}">Planifier l’estimation sur place</a><a class="text-link" href="${esc(urls.espace)}?email=${encodeURIComponent(file.email)}">Voir mon dossier</a></div>`;
        result.focus();
    });
}

/* ---------- Client space ---------- */
const clientLogin = document.querySelector('#client-login');
if (clientLogin) {
    const space = document.querySelector('#client-space');
    const sessionKey = 'codenyr-seuil-client';
    let current = '';
    const open = (email) => {
        const file = clientByEmail(email);
        if (!file) { clientLogin.querySelector('.form-error').textContent = 'Aucun dossier pour cette adresse. Réservez une visite ou demandez une estimation pour en créer un.'; return; }
        current = file.email;
        try { sessionStorage.setItem(sessionKey, file.email); } catch { /* Session only. */ }
        clientLogin.hidden = true;
        space.hidden = false;
        renderSpace(file);
    };
    const renderSpace = (file) => {
        const done = docsDone(file);
        const total = Object.keys(documents).length;
        const visits = data.bookings.filter((item) => item.clientId === file.id || item.email.toLowerCase() === file.email.toLowerCase()).sort((a, b) => a.slot.localeCompare(b.slot));
        space.innerHTML = `<div class="client-head"><div><p class="eyebrow">Dossier ${esc(file.project.toLowerCase())}</p><h2>Bonjour ${esc(file.name.split(' ')[0])}.</h2></div><span class="badge">${esc(file.status)}</span></div>
            <div class="client-grid">
                <section class="panel"><h3>Mes justificatifs</h3><div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="${total}" aria-valuenow="${done}" aria-label="Justificatifs déposés"><span style="width:${(done / total) * 100}%"></span></div><p class="panel-note">${done} sur ${total} déposés${done === total ? ' — dossier complet.' : '.'}</p>
                    <ul class="doc-list">${Object.entries(documents).map(([key, label]) => `<li class="${file.docs?.[key] ? 'is-done' : ''}"><span>${esc(label)}${file.docs?.[key] ? `<small>${esc(file.docs[key])}</small>` : ''}</span>${file.docs?.[key] ? '<em>Déposé</em>' : `<label class="upload">Déposer<input type="file" data-doc="${key}" accept=".pdf,.jpg,.jpeg,.png"></label>`}</li>`).join('')}</ul>
                    <p class="demo-note">Démonstration : seul le nom du fichier est conservé, le fichier n’est envoyé nulle part.</p></section>
                <section class="panel"><h3>Mes visites</h3>${visits.length ? `<ul class="visit-list">${visits.map((visit) => `<li class="${visit.status === 'Annulée' ? 'is-cancelled' : ''}"><div><strong>${esc(property(visit.propertyId)?.title ?? 'Bien retiré')}</strong><span>${esc(slotLabel(visit.slot))}</span><span class="badge">${esc(visit.status)}</span></div>${visit.status === 'Confirmée' && parseSlot(visit.slot) > new Date() ? `<button type="button" class="text-link" data-cancel="${esc(visit.id)}">Annuler</button>` : ''}</li>`).join('')}</ul>` : '<p class="panel-note">Aucune visite pour le moment.</p>'}<a class="button" href="${esc(urls.visite)}">Réserver une visite</a></section>
            </div><button type="button" class="text-link" data-client-logout>Se déconnecter</button>`;
    };
    space.addEventListener('change', (event) => {
        const input = event.target.closest('[data-doc]');
        if (!input?.files?.[0]) return;
        const file = clientByEmail(current);
        file.docs = { ...file.docs, [input.dataset.doc]: input.files[0].name.slice(0, 80) };
        if (docsDone(file) === Object.keys(documents).length && ['Nouveau', 'À compléter'].includes(file.status)) file.status = 'Complet';
        else if (file.status === 'Nouveau') file.status = 'À compléter';
        save();
        renderSpace(file);
    });
    space.addEventListener('click', (event) => {
        const file = clientByEmail(current);
        const cancel = event.target.closest('[data-cancel]');
        if (cancel) { data.bookings.find((item) => item.id === cancel.dataset.cancel).status = 'Annulée'; save(); renderSpace(file); }
        if (event.target.closest('[data-client-logout]')) { try { sessionStorage.removeItem(sessionKey); } catch { /* Nothing kept. */ } space.hidden = true; clientLogin.hidden = false; }
    });
    clientLogin.addEventListener('submit', (event) => { event.preventDefault(); open(clientLogin.email.value); });
    let remembered = new URLSearchParams(location.search).get('email');
    try { remembered ||= sessionStorage.getItem(sessionKey); } catch { /* No session. */ }
    if (remembered) { clientLogin.email.value = remembered; open(remembered); }
}

/* ---------- Agency tool ---------- */
const agencyLogin = document.querySelector('#agency-login');
if (agencyLogin) {
    const agency = document.querySelector('#agency');
    const content = agency.querySelector('[data-agency-content]');
    const feedback = agency.querySelector('.agency-feedback');
    const drawer = agency.querySelector('[data-drawer]');
    const drawerContent = drawer.querySelector('[data-drawer-content]');
    const search = agency.querySelector('[data-agency-search]');
    const newButton = agency.querySelector('[data-agency-new]');
    const sessionKey = 'codenyr-seuil-agency';
    const titles = { overview: 'Tableau de bord', agenda: 'Agenda des visites', clients: 'Dossiers clients', listings: 'Annonces', slots: 'Créneaux d’ouverture' };
    const slug = (text) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/\s+/g, '-');
    const initials = (name) => name.split(/\s+/).map((part) => part[0] || '').join('').slice(0, 2).toUpperCase();
    const shortDay = (date) => date.toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' }).replace('.', '');
    const icons = {
        plus: svg('M12 5v14M5 12h14'), arrow: svg('M4 12h16m-7-7 7 7-7 7'), mail: svg('M4 6h16v12H4V6Zm0 0 8 7 8-7'), phone: svg('M5 4h4l2 5-3 2a11 11 0 0 0 5 5l2-3 5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z'),
        calendar: svg('M4 6h16v14H4V6Zm0 4h16M8 3v4m8-4v4'), folder: svg('M3 6h6l2 2h10v11H3V6Z'), home: svg('M3 11 12 4l9 7M5.5 9.5V20H10v-6h4v6h4.5V9.5'), check: svg('m4 12 5 5L20 6'),
    };
    let tab = 'overview';
    let weekOffset = 0;
    let query = '';
    let drawerOpener = null;

    const say = (message) => { feedback.textContent = message + (persistent ? '' : ' Stockage indisponible : les changements restent sur cette page.'); };
    const upcoming = () => activeBookings().filter((item) => parseSlot(item.slot) > new Date()).sort((a, b) => a.slot.localeCompare(b.slot));
    const visitsOf = (file) => data.bookings.filter((item) => item.clientId === file.id || item.email.toLowerCase() === file.email.toLowerCase());
    const matches = (...fields) => !query || fields.join(' ').toLowerCase().includes(query);
    const chip = (status) => `<span class="chip chip-${slug(status)}">${esc(status)}</span>`;
    const statusSelect = (field, options, value, label) => `<select data-field="${field}" aria-label="${esc(label)}">${options.map((option) => `<option ${option === value ? 'selected' : ''}>${esc(option)}</option>`).join('')}</select>`;
    const agentOf = (item) => (item?.mode === 'Location' ? agents.location : agents.vente).split(',')[0];

    /* Views */
    const views = {
        overview: () => {
            const next = upcoming();
            const week = Array.from({ length: 7 }, (_, index) => addDays(new Date(), index));
            const perDay = week.map((date) => activeBookings().filter((item) => item.slot.startsWith(dayKey(date))).length);
            const max = Math.max(1, ...perDay);
            const open = data.clients.filter((item) => item.status !== 'Clos');
            const complete = data.clients.length ? Math.round((data.clients.filter((item) => docsDone(item) === Object.keys(documents).length).length / data.clients.length) * 100) : 0;
            const waiting = data.clients.filter((item) => item.status !== 'Clos' && docsDone(item) < Object.keys(documents).length);
            const kpi = (icon, value, label, note) => `<div class="kpi"><span class="kpi-icon">${icons[icon]}</span><strong>${value}</strong><span>${label}</span><small>${note}</small></div>`;
            return `<div class="kpis">
                    ${kpi('calendar', perDay.reduce((sum, n) => sum + n, 0), 'visites sur 7 jours', `${next.length} à venir au total`)}
                    ${kpi('folder', open.length, 'dossiers actifs', `${data.clients.filter((item) => item.status === 'Nouveau').length} nouveau(x)`)}
                    ${kpi('check', `${complete} %`, 'dossiers complets', `${waiting.length} en attente de pièces`)}
                    ${kpi('home', data.properties.filter((item) => item.status === 'Disponible').length, 'biens disponibles', `${data.properties.filter((item) => item.status === 'Sous compromis').length} sous compromis`)}
                </div>
                <div class="dash-grid">
                    <section class="card dash-next"><header><h3>Prochaines visites</h3><button type="button" class="link-button" data-goto="agenda">Voir l’agenda ${icons.arrow}</button></header>
                        ${next.length ? `<ol class="timeline">${next.slice(0, 5).map((item) => { const home = property(item.propertyId); return `<li><button type="button" data-booking-open="${esc(item.id)}"><time><strong>${timeLabel(item.slot)}</strong><small>${shortDay(parseSlot(item.slot))}</small></time>${home ? `<img src="${images[home.image]}" alt="" width="1000" height="750">` : ''}<span class="timeline-text"><strong>${esc(home?.title ?? 'Bien retiré')}</strong><span>${esc(item.name)} · avec ${esc(agentOf(home))}</span></span>${chip(item.status)}</button></li>`; }).join('')}</ol>` : '<p class="empty">Aucune visite planifiée.</p>'}</section>
                    <section class="card dash-chart"><header><h3>Visites cette semaine</h3></header><div class="bars">${week.map((date, index) => `<div class="bar${index === 0 ? ' is-today' : ''}"><span style="--h:${(perDay[index] / max) * 100}%"><em>${perDay[index]}</em></span><small>${date.toLocaleDateString('fr-FR', { weekday: 'short' }).replace('.', '')}</small></div>`).join('')}</div></section>
                    <section class="card dash-pipeline"><header><h3>Dossiers par étape</h3><button type="button" class="link-button" data-goto="clients">Ouvrir ${icons.arrow}</button></header>
                        <div class="pipeline-bar">${statuses.map((status) => { const count = data.clients.filter((item) => item.status === status).length; return count ? `<span class="seg-${slug(status)}" style="flex:${count}" title="${esc(status)} : ${count}"></span>` : ''; }).join('')}</div>
                        <ul class="pipeline">${statuses.map((status) => `<li><span class="dot dot-${slug(status)}"></span>${esc(status)}<strong>${data.clients.filter((item) => item.status === status).length}</strong></li>`).join('')}</ul></section>
                    <section class="card dash-docs"><header><h3>Pièces à relancer</h3></header>
                        ${waiting.length ? `<ul class="person-list">${waiting.slice(0, 4).map((item) => `<li><button type="button" data-client-open="${esc(item.id)}"><span class="avatar">${esc(initials(item.name))}</span><span><strong>${esc(item.name)}</strong><small>${Object.keys(documents).length - docsDone(item)} pièce(s) manquante(s)</small></span>${icons.arrow}</button></li>`).join('')}</ul>` : '<p class="empty">Tous les dossiers sont complets.</p>'}</section>
                </div>`;
        },
        agenda: () => {
            const { start, end } = data.settings;
            const startHour = Math.floor(Number(start.slice(0, 2)));
            const endHour = Math.ceil(Number(end.slice(0, 2)) + Number(end.slice(3)) / 60);
            const hours = Array.from({ length: endHour - startHour }, (_, index) => startHour + index);
            const first = addDays(new Date(), weekOffset * 7);
            const days = Array.from({ length: 7 }, (_, index) => addDays(first, index)).filter((date) => data.settings.days.includes(date.getDay()) || data.bookings.some((item) => item.slot.startsWith(dayKey(date))));
            const rowHeight = 88;
            const events = (date) => data.bookings.filter((item) => item.slot.startsWith(dayKey(date)) && matches(item.name, item.email, property(item.propertyId)?.title)).map((item) => {
                const at = parseSlot(item.slot);
                const top = ((at.getHours() + at.getMinutes() / 60) - startHour) * rowHeight;
                return `<button type="button" class="cal-event is-${slug(item.status)}" style="top:${top}px;height:${Math.max(36, (data.settings.duration / 60) * rowHeight - 4)}px" data-booking-open="${esc(item.id)}"><strong>${timeLabel(item.slot)}</strong><span>${esc(item.name)}</span><small>${esc(property(item.propertyId)?.title ?? 'Bien retiré')}</small></button>`;
            }).join('');
            return `<div class="cal-toolbar"><div class="cal-nav"><button type="button" class="slot-nav" data-cal-week="-1" aria-label="Semaine précédente">${svg('M20 12H4m7-7-7 7 7 7')}</button><button type="button" class="cal-today" data-cal-week="0">Aujourd’hui</button><button type="button" class="slot-nav" data-cal-week="1" aria-label="Semaine suivante">${icons.arrow}</button></div>
                    <strong>${esc(capitalize(first.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' })))} – ${esc(addDays(first, 6).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }))}</strong>
                    <div class="cal-legend"><span><i class="dot dot-confirmee"></i>Confirmée</span><span><i class="dot dot-effectuee"></i>Effectuée</span><span><i class="dot dot-annulee"></i>Annulée</span></div></div>
                <div class="cal-scroll"><div class="cal" style="--days:${days.length};--row:${rowHeight}px">
                    <div class="cal-head"><span></span>${days.map((date) => `<div class="${dayKey(date) === dayKey(new Date()) ? 'is-today' : ''}"><small>${date.toLocaleDateString('fr-FR', { weekday: 'short' }).replace('.', '')}</small><strong>${date.getDate()}</strong></div>`).join('')}</div>
                    <div class="cal-body" style="height:${hours.length * rowHeight}px"><div class="cal-hours">${hours.map((hour) => `<span>${hour} h</span>`).join('')}</div>${days.map((date) => `<div class="cal-day">${events(date)}</div>`).join('')}</div>
                </div></div>`;
        },
        clients: () => `<div class="kanban">${statuses.map((status) => {
            const files = data.clients.filter((item) => item.status === status && matches(item.name, item.email, item.project));
            return `<section class="kanban-col"><header><span class="dot dot-${slug(status)}"></span>${esc(status)}<em>${files.length}</em></header>${files.map((item) => {
                const done = docsDone(item);
                return `<button type="button" class="kanban-card" data-client-open="${esc(item.id)}"><span class="kanban-top"><span class="avatar">${esc(initials(item.name))}</span><span><strong>${esc(item.name)}</strong><small>${esc(item.project)}${item.budget ? ` · ${item.project === 'Location' ? `${euros(item.budget)}/mois` : euros(item.budget)}` : ''}</small></span></span><span class="mini-progress"><span style="width:${(done / Object.keys(documents).length) * 100}%"></span></span><small>${done}/${Object.keys(documents).length} pièces · ${visitsOf(item).length} visite(s)</small></button>`;
            }).join('') || '<p class="kanban-empty">Aucun dossier</p>'}</section>`;
        }).join('')}</div>`,
        listings: () => `<div class="listing-grid">${data.properties.filter((item) => matches(item.title, item.area, item.type)).map((item) => `<article class="listing-card" data-listing="${esc(item.id)}"><div class="listing-photo"><img src="${images[item.image]}" alt="" width="1000" height="750">${chip(item.status)}${item.featured ? '<span class="listing-star">À la une</span>' : ''}</div>
                <div class="listing-body"><strong>${esc(item.title)}</strong><span>${esc(item.type)} · ${esc(item.area)} · ${esc(item.mode)}</span><p>${priceLabel(item)}</p><ul><li>${item.surface} m²</li><li>${item.rooms} pièces</li><li>${data.bookings.filter((visit) => visit.propertyId === item.id && visit.status !== 'Annulée').length} visite(s)</li></ul>
                <div class="listing-actions">${statusSelect('status', ['Disponible', 'Sous compromis', 'Vendu', 'Loué'], item.status, `Statut de ${item.title}`)}<label class="switch"><input type="checkbox" data-featured ${item.featured ? 'checked' : ''}><span></span>À la une</label></div></div></article>`).join('') || '<p class="empty">Aucune annonce ne correspond.</p>'}</div>`,
        slots: () => {
            const names = ['Dim.', 'Lun.', 'Mar.', 'Mer.', 'Jeu.', 'Ven.', 'Sam.'];
            const { start, end, duration, days } = data.settings;
            const perDay = daySlotsCount(start, end, duration);
            return `<form class="card slots-form" data-settings><div class="slots-grid">
                    <label>Première visite<input name="start" type="time" value="${start}" required></label>
                    <label>Dernière fin de visite<input name="end" type="time" value="${end}" required></label></div>
                    <fieldset class="segmented"><legend>Durée d’une visite</legend>${[30, 45, 60].map((minutes) => `<label><input type="radio" name="duration" value="${minutes}" ${minutes === duration ? 'checked' : ''}><span>${minutes} min</span></label>`).join('')}</fieldset>
                    <fieldset class="day-chips"><legend>Jours d’ouverture</legend>${[1, 2, 3, 4, 5, 6, 0].map((day) => `<label><input type="checkbox" name="days" value="${day}" ${days.includes(day) ? 'checked' : ''}><span>${names[day]}</span></label>`).join('')}</fieldset>
                    <p class="slots-preview">${icons.calendar}<span><strong>${perDay} créneaux par jour</strong> · ${perDay * days.length} par semaine proposés aux visiteurs</span></p>
                    <button class="button" type="submit">Enregistrer les créneaux</button></form>`;
        },
    };
    const daySlotsCount = (start, end, duration) => { const toMinutes = (time) => Number(time.slice(0, 2)) * 60 + Number(time.slice(3)); return Math.max(0, Math.floor((toMinutes(end) - toMinutes(start)) / duration)); };

    /* Drawer: one booking, one client file, or a creation form */
    const drawers = {
        booking: (id) => {
            const item = data.bookings.find((visit) => visit.id === id);
            const home = property(item.propertyId);
            const file = client(item.clientId) || clientByEmail(item.email);
            return `<p class="eyebrow">Visite</p><h3 id="drawer-title">${esc(capitalize(slotLabel(item.slot)))}</h3>
                ${home ? `<div class="drawer-property"><img src="${images[home.image]}" alt="" width="1000" height="750"><div><strong>${esc(home.title)}</strong><span>${esc(home.area)} · ${priceLabel(home)}</span><span>Avec ${esc(agentOf(home))}</span></div></div>` : ''}
                <dl class="drawer-facts"><div><dt>Client</dt><dd>${esc(item.name)}</dd></div><div><dt>E-mail</dt><dd>${esc(item.email)}</dd></div></dl>
                <fieldset class="segmented" data-booking-status="${esc(item.id)}"><legend>Statut de la visite</legend>${['Confirmée', 'Effectuée', 'Annulée'].map((status) => `<label><input type="radio" name="booking-status" value="${status}" ${status === item.status ? 'checked' : ''}><span>${status}</span></label>`).join('')}</fieldset>
                ${file ? `<button type="button" class="button button-ghost" data-client-open="${esc(file.id)}">Ouvrir le dossier de ${esc(file.name)}</button>` : ''}`;
        },
        client: (id) => {
            const file = client(id);
            const done = docsDone(file);
            const visits = visitsOf(file).sort((a, b) => a.slot.localeCompare(b.slot));
            return `<div class="drawer-person"><span class="avatar avatar-lg">${esc(initials(file.name))}</span><div><p class="eyebrow">Dossier ${esc(file.project.toLowerCase())}</p><h3 id="drawer-title">${esc(file.name)}</h3><span>Ouvert le ${esc(parseSlot(`${file.created}T00:00`).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' }))}</span></div></div>
                <div class="drawer-contact"><a href="mailto:${esc(file.email)}">${icons.mail}${esc(file.email)}</a>${file.phone ? `<span>${icons.phone}${esc(file.phone)}</span>` : ''}</div>
                <label class="drawer-field">Étape du dossier${statusSelect('client-status', statuses, file.status, 'Étape du dossier')}</label>
                <section class="drawer-section"><h4>Justificatifs <em>${done}/${Object.keys(documents).length}</em></h4><span class="mini-progress"><span style="width:${(done / Object.keys(documents).length) * 100}%"></span></span>
                    <ul class="doc-list">${Object.entries(documents).map(([key, label]) => `<li class="${file.docs?.[key] ? 'is-done' : ''}"><span>${esc(label)}${file.docs?.[key] ? `<small>${esc(file.docs[key])}</small>` : ''}</span><label class="switch"><input type="checkbox" data-doc-toggle="${key}" ${file.docs?.[key] ? 'checked' : ''}><span></span>Reçu</label></li>`).join('')}</ul></section>
                <section class="drawer-section"><h4>Visites <em>${visits.length}</em></h4>${visits.length ? `<ul class="drawer-visits">${visits.map((visit) => `<li><button type="button" data-booking-open="${esc(visit.id)}"><span><strong>${esc(property(visit.propertyId)?.title ?? 'Bien retiré')}</strong><small>${esc(slotLabel(visit.slot))}</small></span>${chip(visit.status)}</button></li>`).join('')}</ul>` : '<p class="empty">Aucune visite.</p>'}</section>
                <label class="drawer-field">Notes internes<textarea data-notes rows="4" maxlength="1000" placeholder="Critères, échanges, prochaines étapes…">${esc(file.notes)}</textarea></label>`;
        },
        newClient: () => `<p class="eyebrow">Nouveau dossier</p><h3 id="drawer-title">Ouvrir un dossier client</h3>
            <form class="drawer-form" data-new-client><label>Nom et prénom<input name="name" required maxlength="80"></label><label>Adresse e-mail<input name="email" type="email" required maxlength="150"></label><label>Téléphone<input name="phone" type="tel" maxlength="20"></label><label>Projet<select name="project"><option>Achat</option><option>Vente</option><option>Location</option><option>Investissement</option></select></label><label>Budget (€)<input name="budget" type="number" min="0" step="1000"></label><button class="button" type="submit">Créer le dossier</button></form>`,
        newListing: () => `<p class="eyebrow">Nouvelle annonce</p><h3 id="drawer-title">Publier un bien</h3>
            <form class="drawer-form" data-new-listing><label>Titre de l’annonce<input name="title" required maxlength="80"></label>
                <div class="drawer-row"><label>Type<select name="type"><option>Maison</option><option>Appartement</option><option>Loft</option></select></label><label>Projet<select name="mode"><option>Vente</option><option>Location</option></select></label></div>
                <label>Quartier<select name="area"><option>Centre historique</option><option>Bords de rivière</option><option>Quartier des Jardins</option><option>Faubourg</option><option>Quartier de la gare</option></select></label>
                <div class="drawer-row"><label>Prix (€)<input name="price" type="number" min="1" required></label><label>Surface (m²)<input name="surface" type="number" min="1" required></label><label>Pièces<input name="rooms" type="number" min="1" value="3" required></label></div>
                <fieldset class="photo-picker"><legend>Photo</legend>${Object.entries(images).map(([key, src], index) => `<label><input type="radio" name="image" value="${key}" ${index === 0 ? 'checked' : ''}><img src="${src}" alt="Photo ${index + 1}" width="1000" height="750"></label>`).join('')}</fieldset>
                <label>Description<textarea name="text" rows="3" maxlength="600"></textarea></label><button class="button" type="submit">Publier l’annonce</button></form>`,
    };
    const openDrawer = (type, id) => {
        drawerOpener = drawer.hidden ? document.activeElement : drawerOpener;
        drawer.dataset.type = type;
        drawer.dataset.id = id ?? '';
        drawerContent.innerHTML = drawers[type](id);
        drawer.hidden = false;
        document.documentElement.classList.add('drawer-lock');
        drawer.querySelector('.drawer-panel').focus();
    };
    const refreshDrawer = () => { if (!drawer.hidden && drawers[drawer.dataset.type] && drawer.dataset.id) drawerContent.innerHTML = drawers[drawer.dataset.type](drawer.dataset.id); };
    const closeDrawer = () => { drawer.hidden = true; document.documentElement.classList.remove('drawer-lock'); drawerOpener?.focus?.(); };

    const render = () => {
        agency.querySelectorAll('[data-agency-tab]').forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.agencyTab === tab)));
        agency.querySelector('[data-agency-title]').textContent = titles[tab];
        agency.querySelector('[data-agency-date]').textContent = capitalize(longDate(new Date()));
        agency.querySelector('[data-count="agenda"]').textContent = upcoming().length || '';
        agency.querySelector('[data-count="clients"]').textContent = data.clients.filter((item) => item.status === 'Nouveau').length || '';
        const action = { clients: ['newClient', 'Nouveau dossier'], listings: ['newListing', 'Nouvelle annonce'] }[tab];
        newButton.hidden = !action;
        if (action) { newButton.dataset.drawer = action[0]; newButton.innerHTML = `${icons.plus} ${action[1]}`; }
        search.closest('.agency-search').hidden = !['agenda', 'clients', 'listings'].includes(tab);
        content.className = `agency-view view-${tab}`;
        content.innerHTML = views[tab]();
    };

    /* Events */
    agency.addEventListener('click', (event) => {
        const target = event.target;
        const tabButton = target.closest('[data-agency-tab]');
        if (tabButton) { tab = tabButton.dataset.agencyTab; feedback.textContent = ''; render(); return; }
        const goto = target.closest('[data-goto]');
        if (goto) { tab = goto.dataset.goto; render(); return; }
        const week = target.closest('[data-cal-week]');
        if (week) { weekOffset = Number(week.dataset.calWeek) === 0 ? 0 : weekOffset + Number(week.dataset.calWeek); render(); return; }
        const bookingOpen = target.closest('[data-booking-open]');
        if (bookingOpen) { openDrawer('booking', bookingOpen.dataset.bookingOpen); return; }
        const clientOpen = target.closest('[data-client-open]');
        if (clientOpen) { openDrawer('client', clientOpen.dataset.clientOpen); return; }
        if (target.closest('[data-agency-new]')) { openDrawer(newButton.dataset.drawer); return; }
        if (target.closest('[data-close-drawer]')) { closeDrawer(); return; }
        if (target.closest('[data-agency-reset]')) { data = defaults(); save(); closeDrawer(); render(); say('Données de démonstration restaurées.'); return; }
        if (target.closest('[data-agency-logout]')) { try { sessionStorage.removeItem(sessionKey); } catch { /* Nothing kept. */ } closeDrawer(); agency.hidden = true; agencyLogin.hidden = false; }
    });
    agency.addEventListener('change', (event) => {
        const target = event.target;
        const listing = target.closest('[data-listing]');
        if (listing) {
            const item = property(listing.dataset.listing);
            if (target.dataset.field === 'status') item.status = target.value;
            if (target.matches('[data-featured]')) item.featured = target.checked;
            save(); render(); say('Annonce mise à jour.');
            return;
        }
        if (target.name === 'booking-status') {
            data.bookings.find((item) => item.id === target.closest('[data-booking-status]').dataset.bookingStatus).status = target.value;
            save(); render(); say(`Visite marquée « ${target.value} ».`);
            return;
        }
        const file = drawer.dataset.type === 'client' ? client(drawer.dataset.id) : null;
        if (file && target.dataset.field === 'client-status') { file.status = target.value; save(); render(); refreshDrawer(); say('Étape du dossier mise à jour.'); return; }
        if (file && target.dataset.docToggle) {
            const docs = { ...file.docs };
            if (target.checked) docs[target.dataset.docToggle] = 'Reçu à l’agence'; else delete docs[target.dataset.docToggle];
            file.docs = docs;
            save(); render(); refreshDrawer(); say('Justificatifs mis à jour.');
        }
    });
    agency.addEventListener('input', (event) => {
        if (event.target.matches('[data-agency-search]')) { query = event.target.value.trim().toLowerCase(); render(); return; }
        if (event.target.matches('[data-notes]') && drawer.dataset.type === 'client') { client(drawer.dataset.id).notes = event.target.value; save(); }
    });
    agency.addEventListener('submit', (event) => {
        event.preventDefault();
        const form = event.target;
        if (!form.reportValidity()) return;
        if (form.matches('[data-new-client]')) {
            if (clientByEmail(form.email.value)) { say('Un dossier existe déjà pour cette adresse.'); return; }
            const file = { id: uid('c'), name: form.name.value.trim(), email: form.email.value.trim(), phone: form.phone.value.trim(), project: form.project.value, budget: Number(form.budget.value) || 0, status: 'Nouveau', docs: {}, notes: '', created: dayKey(new Date()) };
            data.clients.unshift(file);
            save(); render(); openDrawer('client', file.id); say('Dossier créé.');
        }
        if (form.matches('[data-new-listing]')) {
            data.properties.unshift({ id: uid('p'), title: form.title.value.trim(), type: form.type.value, mode: form.mode.value, area: form.area.value, price: Number(form.price.value), surface: Number(form.surface.value), rooms: Number(form.rooms.value), bedrooms: Math.max(1, Number(form.rooms.value) - 1), dpe: 'C', image: form.image.value, status: 'Disponible', featured: false, text: form.text.value.trim() || 'Nouvelle annonce publiée depuis l’espace agence de démonstration.' });
            save(); closeDrawer(); render(); say('Annonce publiée : elle apparaît dans « Nos biens ».');
        }
        if (form.matches('[data-settings]')) {
            const days = [...form.querySelectorAll('[name=days]:checked')].map((input) => Number(input.value));
            if (form.start.value >= form.end.value || !days.length) { say('Vérifiez les horaires et cochez au moins un jour.'); return; }
            data.settings = { start: form.start.value, end: form.end.value, duration: Number(form.duration.value), days };
            save(); render(); say('Créneaux enregistrés : la page de réservation est à jour.');
        }
    });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !drawer.hidden) closeDrawer(); });

    const enter = () => { agencyLogin.hidden = true; agency.hidden = false; document.body.classList.add('agency-open'); render(); };
    agencyLogin.addEventListener('submit', (event) => {
        event.preventDefault();
        if (agencyLogin.email.value.trim() !== 'agence@seuil.demo' || agencyLogin.password.value !== 'Seuil2026!') { agencyLogin.querySelector('.form-error').textContent = 'Identifiants de démonstration incorrects.'; return; }
        try { sessionStorage.setItem(sessionKey, '1'); } catch { /* Session only. */ }
        enter();
    });
    try { if (sessionStorage.getItem(sessionKey)) enter(); } catch { /* Starts signed out. */ }
}
