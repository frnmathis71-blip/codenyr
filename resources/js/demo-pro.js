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

// Local demonstration only: this session never authenticates a Laravel user.
const sessionKey = 'canopee-demo-session';
let demoConnected = false;
try { demoConnected = sessionStorage.getItem(sessionKey) === 'active'; } catch {}
const login = document.querySelector('#demo-login');
login?.addEventListener('submit', event => {
    event.preventDefault();
    const feedback = document.querySelector('#login-feedback');
    if (document.querySelector('#demo-email').value.trim() !== 'admin@canopee.demo' || document.querySelector('#demo-password').value !== 'Canopee2026!') {
        feedback.textContent = 'Utilisez les identifiants de démonstration indiqués à gauche.'; return;
    }
    try { sessionStorage.setItem(sessionKey, 'active'); location.assign(login.dataset.adminUrl); }
    catch { feedback.textContent = 'Le stockage de session est indisponible. Autorisez-le pour essayer la connexion.'; }
});
document.querySelector('#show-password')?.addEventListener('click', event => {
    const input = document.querySelector('#demo-password');
    input.type = input.type === 'password' ? 'text' : 'password';
    event.currentTarget.textContent = input.type === 'password' ? 'Afficher' : 'Masquer';
});
const businessKey = 'canopee-business-v1';
const businessDefaults = {
    requests: [{id:'r1',name:'Camille Laurent',title:'Jardin familial · Lyon',amount:4800,status:'Nouveau',note:''},{id:'r2',name:'Alex Martin',title:'Terrasse végétalisée · Annecy',amount:2600,status:'Devis envoyé',note:''},{id:'r3',name:'Lou Bernard',title:'Patio de ville · Grenoble',amount:3200,status:'Accepté',note:''}],
    clients: [{id:'c1',name:'Camille Laurent',email:'camille@example.com'},{id:'c2',name:'Alex Martin',email:'alex@example.com'}],
    appointments: [{id:'m1',title:'Premier échange · Camille',date:'2026-10-08T10:00'},{id:'m2',title:'Présentation du projet · Alex',date:'2026-10-09T14:30'}],
    settings: {name:'Canopée',email:'bonjour@canopee.demo',message:'Paysage & jardins vivants. Des lieux qui ont du sens.'}
};
let business = structuredClone(businessDefaults);
try {
    const saved = JSON.parse(localStorage.getItem(businessKey) || 'null');
    if (saved && ['requests','clients','appointments'].every(key=>Array.isArray(saved[key])) && saved.settings && typeof saved.settings.name === 'string' && typeof saved.settings.email === 'string' && typeof saved.settings.message === 'string' && saved.requests.every(x=>typeof x.name==='string' && typeof x.title==='string' && typeof x.amount==='number' && typeof x.status==='string' && typeof x.note==='string') && saved.clients.every(x=>typeof x.name==='string' && typeof x.email==='string') && saved.appointments.every(x=>typeof x.title==='string' && typeof x.date==='string' && Number.isFinite(Date.parse(x.date)))) business = saved;
} catch {}
function publicSettings() {
    const footer = document.querySelector('#studio-public-contact');
    if (footer) footer.textContent = `${business.settings.name} · ${business.settings.message} Contact : ${business.settings.email}`;
}
publicSettings();
const admin = document.querySelector('#demo-admin');
if (admin && demoConnected) {
    admin.hidden = false; document.querySelector('#admin-gate').hidden = true;
    const notify = message => { document.querySelector('#business-feedback').textContent = message; };
    const persistBusiness = () => { try {localStorage.setItem(businessKey,JSON.stringify(business));notify('Modifications enregistrées dans ce navigateur.');} catch {notify('Stockage indisponible : changements conservés sur cette page uniquement.');} renderBusiness();publicSettings(); };
    document.querySelectorAll('[data-admin-tab]').forEach(button=>button.addEventListener('click',()=>{
        document.querySelectorAll('[data-admin-tab]').forEach(x=>x.setAttribute('aria-pressed',String(x===button)));
        document.querySelectorAll('[data-admin-panel]').forEach(panel=>panel.hidden=panel.dataset.adminPanel!==button.dataset.adminTab);
        notify('');
    }));
    document.querySelector('#demo-logout').addEventListener('click',event=>{try {sessionStorage.removeItem(sessionKey);} catch {} location.assign(event.currentTarget.dataset.loginUrl);});
    const money = value=>new Intl.NumberFormat('fr-FR',{style:'currency',currency:'EUR',maximumFractionDigits:0}).format(value);
    const date = value=>new Date(value).toLocaleString('fr-FR',{dateStyle:'medium',timeStyle:'short'});
    function row(title,subtitle) { const node=element('article',undefined,'manager-row');const copy=element('div');copy.append(element('h3',title),element('p',subtitle));node.append(copy);return node; }
    function deleteButton(collection,item) {const button=element('button','Supprimer');button.type='button';button.addEventListener('click',()=>{const index=business[collection].indexOf(item);business[collection].splice(index,1);persistBusiness();notify('Élément supprimé.');const undo=element('button','Annuler');undo.type='button';undo.addEventListener('click',()=>{business[collection].splice(index,0,item);persistBusiness();});document.querySelector('#business-feedback').append(' ',undo);});return button;}
    function renderBusiness() {
        const published = [...data.projects,...data.articles].filter(x=>x.state==='published').length;
        document.querySelector('#demo-metrics').replaceChildren(...[[business.requests.filter(x=>x.status==='Nouveau').length,'Nouvelles demandes'],[business.clients.length,'Clients'],[money(business.requests.filter(x=>x.status==='Accepté').reduce((sum,x)=>sum+x.amount,0)),'Devis acceptés · simulation'],[published,'Contenus publiés']].map(([value,label])=>{const card=element('article',undefined,'admin-card');card.append(element('strong',String(value)),element('p',label));return card;}));
        document.querySelector('#recent-requests').replaceChildren(...business.requests.slice(0,3).map(x=>row(x.name,`${x.title} · ${x.status}`)));
        const appointments=[...business.appointments].sort((a,b)=>a.date.localeCompare(b.date));
        const upcoming=appointments.filter(x=>new Date(x.date)>=new Date());
        document.querySelector('#next-appointments').replaceChildren(...(upcoming.length?upcoming.slice(0,3).map(x=>row(x.title,date(x.date))):[element('p','Aucun rendez-vous à venir.')]));
        const query=document.querySelector('#request-search').value.toLocaleLowerCase('fr');
        const requests=business.requests.filter(x=>`${x.name} ${x.title}`.toLocaleLowerCase('fr').includes(query));
        document.querySelector('#request-list').replaceChildren(...requests.map(item=>{
            const node=row(item.name,`${item.title} · Devis fictif : ${money(item.amount)}`);const controls=element('div',undefined,'request-controls');
            const label=element('label','Statut');const select=element('select');['Nouveau','En étude','Devis envoyé','Accepté','Refusé'].forEach(value=>{const option=element('option',value);option.value=value;select.append(option);});select.value=item.status;select.addEventListener('change',()=>{item.status=select.value;persistBusiness();});label.append(select);
            const notes=element('label','Note interne');const input=element('textarea');input.rows=2;input.maxLength=1000;input.value=item.note;notes.append(input);const save=element('button','Enregistrer la note');save.type='button';save.addEventListener('click',()=>{item.note=input.value.trim();persistBusiness();});controls.append(label,notes,save);node.append(controls);return node;
        }));
        if (!requests.length) document.querySelector('#request-list').append(element('p','Aucune demande ne correspond.'));
        document.querySelector('#client-list').replaceChildren(...business.clients.map(x=>{const node=row(x.name,x.email);node.append(deleteButton('clients',x));return node;}));
        document.querySelector('#appointment-list').replaceChildren(...appointments.map(x=>{const node=row(x.title,date(x.date));node.append(deleteButton('appointments',x));return node;}));
        for (const key of ['clients','appointments']) if(!business[key].length)document.querySelector(key==='clients'?'#client-list':'#appointment-list').append(element('p','Aucun élément. Ajoutez votre premier exemple.'));
    }
    document.querySelector('#request-search').addEventListener('input',renderBusiness);
    for (const [id,collection,fields] of [['client-form','clients',['name','email']],['appointment-form','appointments',['title','date']]]) {
        document.getElementById(id).addEventListener('submit',event=>{event.preventDefault();const form=event.currentTarget;const item={id:crypto.randomUUID()};fields.forEach(key=>item[key]=form.elements.namedItem(key).value.trim());if(fields.some(key=>!item[key]))return;business[collection].push(item);form.reset();persistBusiness();});
    }
    const settingsForm=document.querySelector('#business-settings');Object.entries(business.settings).forEach(([key,value])=>settingsForm.elements.namedItem(key).value=value);
    settingsForm.addEventListener('submit',event=>{event.preventDefault();const values=Object.fromEntries(new FormData(settingsForm));if(Object.values(values).some(x=>!x.trim()))return;business.settings=values;persistBusiness();});
    document.querySelector('#entry-form').addEventListener('submit',renderBusiness);
    document.querySelector('#reset-content').addEventListener('click',renderBusiness);
    document.querySelector('[data-admin-tab="overview"]').addEventListener('click',renderBusiness);
    renderBusiness();
}
