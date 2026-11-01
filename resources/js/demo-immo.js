import './demo-atelier.js';
import '../css/demo-immo.css';

// Seuil Immobilier: listings, visit booking, client files and the agency tool.
// Everything is fictional and kept in this browser (localStorage), like the Canopée demo.
const storageKey = 'codenyr-seuil-v2';
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
const slotLabel = (slot) => { const [h, m] = slot.split('T')[1].split(':'); return `${longDate(parseSlot(slot))} à ${Number(h)} h ${m}`; };
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
        ],
        bookings: [
            { id: 'b1', propertyId: 'p2', clientId: 'c1', name: 'Camille R.', email: 'camille@seuil.demo', slot: `${nextOpenDay(1)}T10:30`, status: 'Confirmée' },
            { id: 'b2', propertyId: 'p5', clientId: 'c2', name: 'Malik B.', email: 'malik@seuil.demo', slot: `${nextOpenDay(2)}T17:15`, status: 'Confirmée' },
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
    const week = booking.querySelector('[data-slot-week]');
    const choice = booking.querySelector('[data-slot-choice]');
    const error = booking.querySelector('[data-booking-error]');
    const result = document.querySelector('[data-booking-result]');
    let offset = 0;
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
    const renderWeek = () => {
        const first = addDays(new Date(), offset * 7);
        booking.querySelector('[data-week-label]').textContent = `Semaine du ${first.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' })}`;
        booking.querySelector('[data-week="-1"]').disabled = offset === 0;
        week.innerHTML = Array.from({ length: 7 }, (_, index) => addDays(first, index)).map((date) => {
            const slots = daySlots(date);
            if (slots === null) return '';
            const buttons = slots.length ? slots.map(({ slot, free }) => `<button type="button" class="slot-btn" data-slot="${slot}" aria-pressed="${slot === chosen}" ${free ? '' : 'disabled'} aria-label="${free ? slotLabel(slot) : `${slotLabel(slot)}, déjà réservé`}">${slot.split('T')[1]}</button>`).join('') : '<p class="slot-none">Complet</p>';
            return `<div class="slot-day"><p>${date.toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' })}</p>${buttons}</div>`;
        }).join('');
    };
    week.addEventListener('click', (event) => {
        const button = event.target.closest('[data-slot]');
        if (!button) return;
        chosen = button.dataset.slot;
        choice.textContent = `Créneau choisi : ${slotLabel(chosen)}.`;
        renderWeek();
    });
    booking.querySelectorAll('[data-week]').forEach((button) => button.addEventListener('click', () => { offset = Math.max(0, offset + Number(button.dataset.week)); renderWeek(); }));
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
        choice.textContent = 'Aucun créneau choisi.';
        renderWeek();
    });
    showProperty();
    renderWeek();
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
    const sessionKey = 'codenyr-seuil-agency';
    let tab = 'overview';
    let openClient = null;
    const say = (message) => { feedback.textContent = message + (persistent ? '' : ' Stockage indisponible : les changements restent sur cette page.'); };
    const select = (name, options, value, label) => `<select data-field="${name}" aria-label="${esc(label)}">${options.map((option) => `<option ${option === value ? 'selected' : ''}>${esc(option)}</option>`).join('')}</select>`;
    const upcoming = () => activeBookings().filter((item) => parseSlot(item.slot) > new Date()).sort((a, b) => a.slot.localeCompare(b.slot));

    const views = {
        overview: () => {
            const next = upcoming();
            const open = data.clients.filter((item) => !['Clos'].includes(item.status));
            return `<h2>Vue d’ensemble</h2><div class="metrics">
                <div><strong>${next.length}</strong><span>visites à venir</span></div>
                <div><strong>${open.length}</strong><span>dossiers ouverts</span></div>
                <div><strong>${data.clients.filter((item) => ['Nouveau', 'À compléter'].includes(item.status)).length}</strong><span>dossiers à compléter</span></div>
                <div><strong>${data.properties.filter((item) => item.status === 'Disponible').length}</strong><span>biens disponibles</span></div></div>
                <h3>Prochaines visites</h3>${next.length ? `<ul class="row-list">${next.slice(0, 4).map((item) => `<li><div><strong>${esc(slotLabel(item.slot))}</strong><span>${esc(property(item.propertyId)?.title ?? 'Bien retiré')} · ${esc(item.name)}</span></div></li>`).join('')}</ul>` : '<p class="panel-note">Aucune visite planifiée.</p>'}`;
        },
        agenda: () => {
            const all = [...data.bookings].sort((a, b) => a.slot.localeCompare(b.slot));
            return `<h2>Agenda des visites</h2><p class="panel-note">Les visites réservées en ligne arrivent ici automatiquement.</p>${all.length ? `<ul class="row-list">${all.map((item) => `<li data-booking="${esc(item.id)}"><div><strong>${esc(slotLabel(item.slot))}</strong><span>${esc(property(item.propertyId)?.title ?? 'Bien retiré')}</span><span>${esc(item.name)} · ${esc(item.email)}</span></div>${select('status', ['Confirmée', 'Effectuée', 'Annulée'], item.status, `Statut de la visite de ${item.name}`)}</li>`).join('')}</ul>` : '<p class="panel-note">Aucune visite.</p>'}`;
        },
        clients: () => `<h2>Dossiers clients</h2>
            <form class="inline-form" data-new-client><label>Nom<input name="name" required maxlength="80"></label><label>E-mail<input name="email" type="email" required maxlength="150"></label><label>Projet<select name="project"><option>Achat</option><option>Vente</option><option>Location</option><option>Investissement</option></select></label><button class="button" type="submit">Créer le dossier</button></form>
            <ul class="row-list">${data.clients.map((item) => `<li data-client="${esc(item.id)}" class="${openClient === item.id ? 'is-open' : ''}"><div><strong>${esc(item.name)}</strong><span>${esc(item.project)} · ${esc(item.email)}</span><span>Justificatifs ${docsDone(item)}/${Object.keys(documents).length}</span></div>${select('status', statuses, item.status, `Statut du dossier de ${item.name}`)}<button type="button" class="text-link" data-toggle-client aria-expanded="${openClient === item.id}">${openClient === item.id ? 'Fermer' : 'Ouvrir'}</button>
                ${openClient === item.id ? `<div class="client-detail"><ul class="doc-list">${Object.entries(documents).map(([key, label]) => `<li class="${item.docs?.[key] ? 'is-done' : ''}"><span>${esc(label)}${item.docs?.[key] ? `<small>${esc(item.docs[key])}</small>` : ''}</span><label class="check"><input type="checkbox" data-doc-toggle="${key}" ${item.docs?.[key] ? 'checked' : ''}> Reçu</label></li>`).join('')}</ul><label>Notes internes<textarea data-notes rows="3" maxlength="1000">${esc(item.notes)}</textarea></label></div>` : ''}</li>`).join('')}</ul>`,
        listings: () => `<h2>Annonces</h2>
            <form class="inline-form" data-new-listing><label>Titre<input name="title" required maxlength="80"></label><label>Type<select name="type"><option>Maison</option><option>Appartement</option><option>Loft</option></select></label><label>Projet<select name="mode"><option>Vente</option><option>Location</option></select></label><label>Prix (€)<input name="price" type="number" min="1" required></label><label>Surface (m²)<input name="surface" type="number" min="1" required></label><label>Pièces<input name="rooms" type="number" min="1" value="3" required></label><button class="button" type="submit">Publier</button></form>
            <ul class="row-list">${data.properties.map((item) => `<li data-listing="${esc(item.id)}"><div><strong>${esc(item.title)}</strong><span>${esc(item.type)} · ${esc(item.mode)} · ${priceLabel(item)}</span></div>${select('status', ['Disponible', 'Sous compromis', 'Vendu', 'Loué'], item.status, `Statut de ${item.title}`)}<label class="check"><input type="checkbox" data-featured ${item.featured ? 'checked' : ''}> À la une</label></li>`).join('')}</ul>`,
        slots: () => {
            const days = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
            const { start, end, duration } = data.settings;
            return `<h2>Créneaux d’ouverture</h2><p class="panel-note">Ces réglages définissent les créneaux proposés aux visiteurs sur la page de réservation.</p>
                <form class="form-card" data-settings><div class="form-grid"><label>Première visite<input name="start" type="time" value="${start}" required></label><label>Fin de journée<input name="end" type="time" value="${end}" required></label><label>Durée d’une visite<select name="duration">${[30, 45, 60].map((minutes) => `<option value="${minutes}" ${minutes === duration ? 'selected' : ''}>${minutes} minutes</option>`).join('')}</select></label></div>
                <fieldset class="day-picker"><legend>Jours d’ouverture</legend>${[1, 2, 3, 4, 5, 6, 0].map((day) => `<label class="check"><input type="checkbox" name="days" value="${day}" ${data.settings.days.includes(day) ? 'checked' : ''}> ${days[day]}</label>`).join('')}</fieldset>
                <button class="button" type="submit">Enregistrer les créneaux</button></form>`;
        },
    };
    const render = () => {
        agency.querySelectorAll('[data-agency-tab]').forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.agencyTab === tab)));
        content.innerHTML = views[tab]();
    };
    agency.querySelectorAll('[data-agency-tab]').forEach((button) => button.addEventListener('click', () => { tab = button.dataset.agencyTab; feedback.textContent = ''; render(); }));
    content.addEventListener('change', (event) => {
        const row = event.target.closest('li');
        const field = event.target.dataset.field;
        if (row?.dataset.booking && field) { data.bookings.find((item) => item.id === row.dataset.booking).status = event.target.value; save(); say('Visite mise à jour.'); }
        if (row?.dataset.client && field) { client(row.dataset.client).status = event.target.value; save(); say('Statut du dossier mis à jour.'); }
        if (event.target.dataset.docToggle) {
            const item = client(event.target.closest('[data-client]').dataset.client);
            item.docs = { ...item.docs, [event.target.dataset.docToggle]: event.target.checked ? 'Reçu à l’agence' : undefined };
            if (!event.target.checked) delete item.docs[event.target.dataset.docToggle];
            save(); render(); say('Justificatifs mis à jour.');
        }
        if (row?.dataset.listing) {
            const item = property(row.dataset.listing);
            if (field) item.status = event.target.value;
            if (event.target.matches('[data-featured]')) item.featured = event.target.checked;
            save(); say('Annonce mise à jour.');
        }
    });
    content.addEventListener('input', (event) => {
        if (!event.target.matches('[data-notes]')) return;
        client(event.target.closest('[data-client]').dataset.client).notes = event.target.value;
        save();
    });
    content.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-toggle-client]');
        if (!toggle) return;
        const id = toggle.closest('[data-client]').dataset.client;
        openClient = openClient === id ? null : id;
        render();
    });
    content.addEventListener('submit', (event) => {
        event.preventDefault();
        const form = event.target;
        if (!form.reportValidity()) return;
        if (form.matches('[data-new-client]')) {
            if (clientByEmail(form.email.value)) { say('Un dossier existe déjà pour cette adresse.'); return; }
            data.clients.unshift({ id: uid('c'), name: form.name.value.trim(), email: form.email.value.trim(), phone: '', project: form.project.value, budget: 0, status: 'Nouveau', docs: {}, notes: '', created: dayKey(new Date()) });
            say('Dossier créé.');
        }
        if (form.matches('[data-new-listing]')) {
            data.properties.unshift({ id: uid('p'), title: form.title.value.trim(), type: form.type.value, mode: form.mode.value, area: 'Centre historique', price: Number(form.price.value), surface: Number(form.surface.value), rooms: Number(form.rooms.value), bedrooms: Math.max(1, Number(form.rooms.value) - 1), dpe: 'C', image: 'salon', status: 'Disponible', featured: false, text: 'Nouvelle annonce publiée depuis l’espace agence de démonstration.' });
            say('Annonce publiée : elle apparaît dans « Nos biens ».');
        }
        if (form.matches('[data-settings]')) {
            const days = [...form.querySelectorAll('[name=days]:checked')].map((input) => Number(input.value));
            if (form.start.value >= form.end.value || !days.length) { say('Vérifiez les horaires et cochez au moins un jour.'); return; }
            data.settings = { start: form.start.value, end: form.end.value, duration: Number(form.duration.value), days };
            say('Créneaux enregistrés : la page de réservation est à jour.');
        }
        save();
        render();
    });
    agency.querySelector('[data-agency-reset]').addEventListener('click', () => { data = defaults(); save(); render(); say('Données de démonstration restaurées.'); });
    agency.querySelector('[data-agency-logout]').addEventListener('click', () => { try { sessionStorage.removeItem(sessionKey); } catch { /* Nothing kept. */ } agency.hidden = true; agencyLogin.hidden = false; });
    const enter = () => { agencyLogin.hidden = true; agency.hidden = false; render(); };
    agencyLogin.addEventListener('submit', (event) => {
        event.preventDefault();
        if (agencyLogin.email.value.trim() !== 'agence@seuil.demo' || agencyLogin.password.value !== 'Seuil2026!') { agencyLogin.querySelector('.form-error').textContent = 'Identifiants de démonstration incorrects.'; return; }
        try { sessionStorage.setItem(sessionKey, '1'); } catch { /* Session only. */ }
        enter();
    });
    try { if (sessionStorage.getItem(sessionKey)) enter(); } catch { /* Starts signed out. */ }
}
