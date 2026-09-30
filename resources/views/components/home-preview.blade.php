<div class="home-showcase" x-data="{ view: 'site' }">
    <div class="home-showcase-top"><span><i aria-hidden="true"></i> De l’idée à l’interface</span><span>CONCEPT CODENYR / 01</span></div>
    <div class="home-preview-switch" role="group" aria-label="Choisir un aperçu de savoir-faire"><button type="button" @click="view = 'site'" :aria-pressed="view === 'site'" aria-controls="preview-site">Site vitrine</button><button type="button" @click="view = 'admin'" :aria-pressed="view === 'admin'" aria-controls="preview-admin">Espace de gestion</button></div>
    <div class="home-preview-window">
        <div class="home-window-bar" aria-hidden="true"><span><i></i><i></i><i></i></span><span x-text="view === 'site' ? 'atelier.example' : 'atelier.example / mon espace'">atelier.example</span><span>↗</span></div>
        <div id="preview-site" class="home-demo-site" x-show="view === 'site'">
            <div class="home-demo-nav"><strong>atelier<span>®</span></strong><span>L’art de faire autrement.</span></div>
            <div class="home-demo-main"><div><span class="home-demo-eyebrow">MATIÈRE & SAVOIR-FAIRE</span><p class="home-demo-title">Le beau.<br>Le juste.<br><em>L’essentiel.</em></p><p>Des espaces de caractère,<br>pensés pour durer.</p><span class="home-demo-faux-link">Notre univers <span>↗</span></span></div><div class="home-sculpture" aria-hidden="true"><div class="home-sculpture-arch"></div><div class="home-sculpture-plinth"></div><div class="home-sculpture-ball"></div><div class="home-sculpture-line"></div></div></div>
            <div class="home-demo-footer"><span>DES PIÈCES QUI RACONTENT UNE HISTOIRE.</span><span>01 — 03</span></div>
        </div>
        <div id="preview-admin" class="home-demo-admin" x-show="view === 'admin'" x-cloak>
            <div class="home-demo-nav"><strong>atelier<span> / studio</span></strong><span>Espace de gestion</span></div>
            <div class="home-admin-heading"><div><span class="home-demo-eyebrow">VOTRE ACTIVITÉ, EN UN COUP D’ŒIL</span><p>Bonjour, Camille.</p></div><span>Mon espace ↗</span></div>
            <div class="home-admin-stats"><div><span>Réalisations</span><strong>12<small>publiées</small></strong></div><div><span>Demandes</span><strong>04<small>à consulter</small></strong></div></div>
            <div class="home-admin-list"><span>DERNIÈRES RÉALISATIONS</span><div><i></i><strong>Un intérieur singulier</strong><span>Publié</span></div><div><i></i><strong>La matière au naturel</strong><span>Brouillon</span></div></div>
            <p class="home-admin-disclaimer">Données fictives · Aperçu d’une interface sur mesure</p>
        </div>
    </div>
    <div class="home-preview-note"><span class="home-note-icon" aria-hidden="true"><span class="mobile-decoration" aria-hidden="true">✳</span></span><div><strong>Votre univers. Votre outil.</strong><span>Le design et la technique, pensés ensemble.</span></div><span aria-hidden="true">↗</span></div>
    <p class="home-preview-caption">Explorez deux facettes d’un même projet. Illustrations de démonstration.</p>
</div>
