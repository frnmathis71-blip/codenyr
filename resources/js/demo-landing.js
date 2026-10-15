import '../css/demo-landing.css';
import { initStudio } from './demo-studio.js';

const coffees = {
    soleil: { name: 'Le Soleil', origin: 'Brésil · Doux & généreux', description: 'Un café rond et réconfortant. Des notes de noisette et de chocolat au lait, pour commencer la journée du bon pied.', notes: ['Noisette', 'Chocolat au lait', 'Tout en rondeur'], tasting: 'Une texture enveloppante, une douceur familière et une finale gourmande. Le Soleil évoque les matins tranquilles et les grandes tablées.', number: '01' },
    escale: { name: 'L’Escale', origin: 'Colombie · Équilibré & lumineux', description: 'La petite pause qui fait voyager. Des notes de caramel et d’orange, avec un bel équilibre entre douceur et vivacité.', notes: ['Caramel', 'Orange', 'Bel équilibre'], tasting: 'Une attaque lumineuse, des notes fruitées et une douceur persistante. L’Escale raconte une pause ouverte sur de nouveaux horizons.', number: '02' },
    nocturne: { name: 'Le Nocturne', origin: 'Assemblage · Intense & profond', description: 'Un caractère affirmé et une belle longueur en bouche. Des notes de cacao et d’épices pour les amateurs de sensations intenses.', notes: ['Cacao', 'Épices', 'Corps intense'], tasting: 'Une présence profonde, des arômes épicés et une finale cacaotée. Le Nocturne accompagne les moments où l’on prend vraiment le temps.', number: '03' },
};
let selected = coffees.soleil;
document.querySelectorAll('[data-coffee]').forEach((button) => {
    button.addEventListener('click', () => {
        selected = coffees[button.dataset.coffee];
        document.querySelectorAll('[data-coffee]').forEach((option) => option.setAttribute('aria-pressed', String(option === button)));
        for (const field of ['name', 'origin', 'description', 'tasting']) document.querySelector(`[data-${field}]`).textContent = selected[field];
        document.querySelector('[data-notes]').replaceChildren(...selected.notes.map((note) => {
            const span = document.createElement('span');
            span.textContent = note;
            return span;
        }));
        document.querySelector('.product-art').dataset.roast = button.dataset.coffee;
        document.querySelector('[data-art-name]').textContent = selected.name;
        document.querySelector('.product-number').textContent = selected.number;
    });
});


initStudio();
