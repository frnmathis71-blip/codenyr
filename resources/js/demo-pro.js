import './demo-vitrine.js';
import '../css/demo-pro.css';
const storageKey = 'codenyr-canopee-content-v1';
const defaults = {
    projects: [{id:'p1',title:'Un jardin à partager',category:'Jardin familial',text:'Des cheminements simples, des plantations généreuses et un espace pour se retrouver. Un concept de jardin qui accompagne les moments du quotidien.',image:'garden',state:'published'}, {id:'p2',title:'La terrasse habitée',category:'Terrasse',text:'Créer une continuité entre intérieur et extérieur grâce aux matières naturelles et à une composition végétale structurée.',image:'interior',state:'published'}, {id:'p3',title:'Le patio calme',category:'Patio',text:'Une parenthèse intime faite de textures, de feuillages et de lumière. Projet de concept en cours de réflexion.',image:'material',state:'draft'}],
    articles: [{id:'a1',title:'Le jardin, une pièce à vivre',category:'Inspiration',text:'Observer les usages avant de dessiner : un coin pour lire, une table pour partager, un passage vers la maison. La conception commence avec ces gestes simples.',image:'garden',state:'published'}, {id:'a2',title:'Composer avec les matières',category:'Carnet du studio',text:'Le bois, la pierre et le végétal apportent chacun une texture différente. Leur association donne le ton du jardin et relie ses différents espaces.',image:'material',state:'published'}],
};
const images = {garden:'/images/canopee/jardin.jpg',interior:'/images/atelier/interieur.jpg',material:'/images/atelier/matiere.jpg'};
let data = structuredClone(defaults);
let persistent = true;
try {
    const saved = JSON.parse(localStorage.getItem(storageKey) || 'null');
    if (saved && ['projects','articles'].every(key => Array.isArray(saved[key]) && saved[key].every(item => ['id','title','category','text','image','state'].every(field => typeof item[field] === 'string') && images[item.image] && ['published','draft'].includes(item.state)))) data = saved;
} catch { persistent = false; }
function save() {
    try { localStorage.setItem(storageKey, JSON.stringify(data)); } catch { persistent = false; }
}
function element(tag, text, className) {
    const node = document.createElement(tag);
    if (text !== undefined) node.textContent = text;
    if (className) node.className = className;
    return node;
}
function renderPublic() {
    document.querySelectorAll('[data-collection]').forEach(container => {
        const query = (document.querySelector('#collection-search')?.value || '').toLocaleLowerCase('fr');
        let entries = data[container.dataset.collection].filter(item => item.state === 'published' && `${item.title} ${item.category} ${item.text}`.toLocaleLowerCase('fr').includes(query));
        if (container.dataset.limit) entries = entries.slice(0, Number(container.dataset.limit));
        container.replaceChildren(...entries.map(item => {
            const article = element('article', undefined, 'project');
            const image = element('img'); image.src = images[item.image]; image.alt = `Illustration d’ambiance : ${item.title}`; image.loading = 'lazy'; image.width = 1000; image.height = 750;
            const copy = element('div'); copy.append(element('span', item.category, 'eyebrow'),element('h2',item.title),element('p',item.text)); article.append(image,copy); return article;
        }));
        if (!entries.length) container.append(element('p','Aucun contenu publié ne correspond à votre recherche.'));
        const status = document.querySelector('.collection-status');
        if (status) status.textContent = `${entries.length} contenu${entries.length > 1 ? 's' : ''} présenté${entries.length > 1 ? 's' : ''}.`;
    });
}
renderPublic();
document.querySelector('#collection-search')?.addEventListener('input', renderPublic);
const list = document.querySelector('#manager-list');
if (list) {
    let module = 'projects';
    const dialog = document.querySelector('#editor');
    const form = document.querySelector('#entry-form');
    const feedback = document.querySelector('.manager-feedback');
    function report(message) { feedback.textContent = message + (persistent ? '' : ' Stockage indisponible : les changements restent dans cette page uniquement.'); }
    function edit(item) {
        form.reset();
        document.querySelector('#editor-title').textContent = item ? 'Modifier le contenu' : 'Ajouter un contenu';
        for (const field of ['id','title','category','text','image','state']) document.querySelector(`#entry-${field}`).value = item?.[field] || ({image:'garden',state:'published'}[field] || '');
        dialog.showModal();
        document.querySelector('#entry-title').focus();
    }
    function render() {
        list.replaceChildren(...data[module].map(item => {
            const row = element('article',undefined,'manager-row');
            const copy = element('div'); copy.append(element('span',item.state === 'published' ? 'Publié' : 'Brouillon','entry-state'),element('h3',item.title),element('p',item.category));
            const actions = element('div',undefined,'entry-actions');
            const update = element('button','Modifier'); update.type = 'button'; update.setAttribute('aria-label',`Modifier ${item.title}`); update.addEventListener('click',()=>edit(item));
            const remove = element('button','Supprimer'); remove.type = 'button'; remove.setAttribute('aria-label',`Supprimer ${item.title}`); remove.addEventListener('click',()=>{
                const index = data[module].indexOf(item); data[module].splice(index,1); save(); render(); report('Contenu supprimé.');
                const undo = element('button','Annuler la suppression'); undo.type='button'; undo.addEventListener('click',()=>{data[module].splice(index,0,item);save();render();report('Contenu restauré.');}); feedback.append(' ',undo);
            });
            actions.append(update,remove); row.append(copy,actions); return row;
        }));
        if (!data[module].length) list.append(element('p','Aucun contenu. Ajoutez votre premier exemple.'));
    }
    document.querySelectorAll('[data-module]').forEach(button=>button.addEventListener('click',()=>{
        module=button.dataset.module; document.querySelectorAll('[data-module]').forEach(option=>option.setAttribute('aria-pressed',String(option===button))); feedback.textContent='';render();
    }));
    document.querySelector('#new-entry').addEventListener('click',()=>edit());
    document.querySelector('#close-editor').addEventListener('click',()=>dialog.close());
    form.addEventListener('submit',event=>{
        event.preventDefault();
        const item = Object.fromEntries(['id','title','category','text','image','state'].map(field=>[field,document.querySelector(`#entry-${field}`).value.trim()]));
        if (!item.title || !item.category || !item.text) return;
        const index=data[module].findIndex(entry=>entry.id===item.id);
        if(index>=0) data[module][index]=item; else {item.id=`demo-${Date.now()}-${Math.random().toString(36).slice(2)}`;data[module].unshift(item);}
        save();render();dialog.close();report(item.state==='published'?'Contenu publié. Retrouvez-le sur le site.':'Brouillon enregistré, invisible sur les pages publiques.');
    });
    document.querySelector('#reset-content').addEventListener('click',()=>{
        const previous=structuredClone(data);data=structuredClone(defaults);save();render();report('Les exemples initiaux sont restaurés.');
        const undo=element('button','Annuler la restauration');undo.type='button';undo.addEventListener('click',()=>{data=previous;save();render();report('Vos contenus sont rétablis.');});feedback.append(' ',undo);
    });
    render();if(!persistent)report('Mode temporaire.');
}
