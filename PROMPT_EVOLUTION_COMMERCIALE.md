# Prompt d’évolution commerciale de Codenyr adapté au dépôt existant

## Mission

Faire évoluer l’application Codenyr existante vers une gestion commerciale centralisée par projet : client, devis, factures, paiements, documents, CGV, contrats, maintenance, fichiers, notes et historique.

Implémenter progressivement dans ce dépôt. Ne pas recréer l’application, son authentification, ses layouts ou son dashboard. Ne jamais modifier `vendor/`, supprimer des données existantes ou exécuter `migrate:fresh`. Analyser le code avant toute modification et respecter les éventuelles instructions du dépôt. Les choix ci-dessous sont adaptés à l’état observé ; vérifier cet état avant d’implémenter.

## 1. Existant à préserver

- Stack : Laravel 13, PHP 8.4, Blade, Livewire 4, Flux 2, Tailwind CSS 4, Vite/Vite Plus, Fortify. SQLite en développement et dans les tests ; compatibilité MySQL requise pour la production prévue sur OVH.
- Authentification : `User`, `is_admin`, Gate `admin` dans `AppServiceProvider`, groupe de routes admin avec `auth`, `verified`, `can:admin`, et composants héritant de `App\Livewire\Admin\AdminComponent`, dont `boot()` autorise l’administration.
- Conserver Fortify, inscription client sans élévation de privilèges, récupération de mot de passe, double authentification, passkeys et comportement configurable de vérification d’e-mail. Ne pas rendre obligatoire la vérification actuellement désactivée par défaut.
- Layout : `resources/views/components/admin-layout.blade.php`, styles `resources/css/codenyr.css` et autres imports de `app.css`. Réutiliser les classes existantes, notamment `admin-panel`, `admin-toolbar`, `admin-table`, `status-pill`, `button` et `button-secondary`. Interface française, responsive, accessible, sans violet.
- Runtime : Livewire/Flux sont intégrés à `resources/js/app.js`. Conserver `@livewireScriptConfig` ; ne pas ajouter `@livewireScripts`, `@fluxScripts` ou une seconde instance Alpine.
- Routes actuelles : `/admin`, `/admin/prospects`, `/admin/archives`, `/admin/realisations`, `/admin/temoignages`, `/admin/tarifs`, `/espace-client`. Ne pas les remplacer.
- `/devis`, route nommée `quote`, est le formulaire PUBLIC de demande de devis `InquiryForm`, pas un éditeur commercial. Le conserver avec ses validations, son anti-spam et ses notifications. Les futurs devis commerciaux seront sous `/admin/devis`.
- `App\Models\Project` et la table `projects` représentent le PORTFOLIO PUBLIC : réalisations, slug, images, publication, mise en avant et démonstrations. `Admin\Projects` et `admin.projects` servent déjà `/admin/realisations`. Ne pas détourner ces classes, cette table ou ce nom de route pour la gestion commerciale.
- `Lead` représente les demandes/prospects. Il contient déjà les coordonnées, le type de demande, la description, le statut, les notes, `user_id`, `delivered_at`, `archived_at`, ainsi que des champs historiques de budget et fonctionnalités. Préserver ces données.
- Les « projets » affichés actuellement dans l’espace client sont des `Lead`. `CustomerReviews` autorise un avis uniquement pour le compte explicitement associé, avec `Lead.status = accepted` et `delivered_at` renseigné. `Testimonial.lead_id` est unique ; les avis ont une révision et une modération. Conserver ces règles, les avis existants, le retrait de publication après modification et la protection contre une approbation de version périmée.
- `/admin/archives` archive actuellement des `Lead` acceptés et livrés ; ce fonctionnement doit continuer.
- Les prix publics existent : modèle `Price`, table `prices`, service `PricingCatalog`, composant `Admin\Pricing`, configuration `config/codenyr.php`. Quatre offres : Landing Page, Site Vitrine, Site Pro, Sur mesure. Trois maintenances : Essentiel, Pro, Business. Les montants personnalisés sont déjà enregistrés en centimes.
- Le dashboard actuel compte des prospects et des réalisations publiques, pas des projets commerciaux ni des encaissements. Conserver ses indicateurs et expliciter les nouveaux.
- Les démonstrations Canopée et autres sont des simulations publiques ; leur module de devis ne doit pas être réutilisé comme stockage commercial réel.

Lire notamment les routes, modèles, migrations, `Admin\Leads`, `Admin\Projects`, `Admin\Pricing`, `CustomerReviews`, `Admin\Testimonials`, les providers, layouts, configuration de stockage et tests existants.

## 2. Architecture adaptée

### Projet commercial

Créer `ClientProject`, table `client_projects`, pour le dossier commercial. L’intitulé visible reste « Projets ». Utiliser `/admin/projets` et `/admin/projets/{clientProject}`, avec des noms distincts tels que `admin.client-projects.*`. Conserver `Project` pour les réalisations publiques. Une liaison facultative vers une réalisation pourra être ajoutée, sans publication automatique du dossier.

Relations proposées : un client possède plusieurs projets commerciaux ; un projet peut provenir d’un `Lead` via une liaison explicite. Un prospect peut donner lieu à plusieurs travaux successifs : ne pas imposer un projet unique par client ou par adresse e-mail.

### Identité commerciale et compte de connexion

Il n’existe actuellement aucun modèle `Client` autonome : `Lead.client()` retourne un `User` par `user_id`. Créer un modèle `Client` pour les coordonnées commerciales, avec liaison facultative vers un compte `User` non administrateur. Ne pas créer de nouvelle authentification ni imposer un compte pour préparer un devis.

Prévoir nom/société, contact, e-mail, téléphone, adresse et identifiants professionnels facultatifs. Depuis un projet : sélectionner un client ou le créer rapidement. Depuis un prospect : préremplir les coordonnées et proposer une association ou création explicite. Une correspondance d’e-mail seule ne doit jamais donner accès à des données ou autoriser un avis.

### Transition des dossiers existants

- Ajouter une action « Créer un projet commercial » depuis un prospect, en conservant le prospect source.
- Ne pas convertir automatiquement toutes les demandes en projets ni dédupliquer silencieusement les clients.
- Prévoir, si nécessaire, une commande de reprise idempotente avec prévisualisation et rapport des associations ; ne pas l’exécuter automatiquement en production.
- Conserver initialement le parcours d’avis fondé sur `Lead`. L’acceptation d’un devis commercial ne doit pas à elle seule rendre un avis possible ni confirmer une livraison.
- Un projet créé sans prospect doit rester utilisable commercialement. Si l’espace client doit ensuite exposer ces projets, réaliser une évolution dédiée et testée des relations et de la modération. Ne pas fabriquer un faux prospect pour contourner ce travail.
- Ne pas créer deux sources concurrentes pour la livraison, l’association de compte ou l’acceptation : documenter l’autorité de chaque donnée et les éventuelles synchronisations explicites. Ne pas réattribuer un dossier ayant un avis par une nouvelle voie qui contournerait les protections actuelles.

### Modèles complémentaires

Ajouter selon les besoins : `ServiceCategory`, `Service`, `Quote`, `QuoteItem`, `Invoice`, `InvoiceItem`, `Payment`, `Document`, `DocumentVersion`, `DocumentTemplate`, `MaintenanceContract`, `ProjectNote`, `ProjectEvent`, paramètres commerciaux et séquences de numérotation. Éviter les abstractions génériques inutiles. Les devis et factures gardent leurs données structurées ; `Document` centralise leurs fichiers et versions sans remplacer ces modèles financiers.

## 3. Projet et dossier central

Créer une liste recherchable avec filtres Tous, Actifs, Terminés, Archivés et bouton « + Nouveau projet ».

Informations : client, nom, type, description, date de création automatique, début et livraison prévus, statut, URL, domaine, hébergeur et notes internes. Types : Landing Page, Site vitrine, Site Pro, E-commerce, Application web, Refonte, Maintenance, Développement spécifique, Autre.

Statuts : Prospect, Devis à préparer, Devis envoyé, En attente client, Devis accepté, En attente d’acompte, À démarrer, En développement, En validation, À mettre en ligne, Terminé, En maintenance, Annulé, Archivé. Définir leur regroupement pour les filtres et garder le statut de projet distinct des statuts des devis et factures.

Fiche centrale : coordonnées client, informations et dates, statut facilement modifiable, actions Créer un devis, Créer une facture, Ajouter/importer un document, Ajouter une note, Modifier le projet et menu « + Ajouter ».

Navigation interne : Résumé, Devis, Factures, Paiements, Documents, Contrats, Maintenance, Fichiers, Notes, Historique. Sur desktop, proposer un aperçu de devis à côté du formulaire ; sur mobile, un basculement Configuration/Aperçu.

Cartes calculées depuis les données persistées : devis, facturation, paiements, documents, maintenance et échéance. Afficher séparément :

- montant initial convenu selon le devis accepté de référence ;
- total des factures émises ;
- total des paiements enregistrés ;
- reste dû sur les factures ;
- montant du devis restant à facturer, lorsqu’il est déterminable ;
- engagements récurrents, par fréquence.

Un devis accepté n’est ni une facture ni une recette encaissée. Un montant restant à facturer n’est pas un impayé. Plusieurs devis acceptés ne doivent pas être additionnés sans règle explicite de devis principal, remplacement ou complément.

## 4. Catalogue et tarifs existants

Créer un catalogue commercial administrable : catégorie, nom, description, prix en centimes, unité, fréquence ponctuelle/mensuelle/trimestrielle/annuelle, actif/inactif et ordre.

Initialiser de façon idempotente les offres et maintenances depuis `PricingCatalog`, en reprenant les prix réellement configurés plutôt que les montants fictifs des exemples. Ne jamais écraser ensuite les modifications administratives.

Pour les prestations liées aux offres publiques, conserver `Price`/`PricingCatalog` comme source tarifaire existante et prévoir un lien explicite par clé ; les prestations supplémentaires peuvent avoir leur propre prix. Les champs de prix des offres liées doivent lire et modifier la même source que `/admin/tarifs`, sans divergence silencieuse. Une éventuelle unification future doit être une migration séparée et testée.

Les cases à cocher sont générées depuis le catalogue. Catégories initiales possibles : Création, Fonctionnalités, Design, SEO, Mise en ligne, Maintenance. Les exemples (contact, réservation, paiement, multilingue, sauvegardes, assistance…) sont des données éditables, jamais une liste figée dans les vues.

## 5. Devis

Ajouter une section admin Devis et la création depuis un projet avec client/projet présélectionnés. Permettre ajout par cases, retrait, ligne personnalisée et édition du nom, description, quantité, unité, prix, remise, TVA, fréquence et caractère facultatif de chaque ligne.

Calculer immédiatement via Livewire et recalculer côté serveur : sous-total, remises par ligne et globale, HT, TVA, TTC, acompte et solde prévisionnel. Montants en centimes ; quantités et taux avec précision définie, sans calcul financier en floats. Définir l’ordre des remises, l’arrondi et la répartition par taux de TVA. Valider les bornes ; empêcher les totaux négatifs et acomptes supérieurs au total initial.

Paramètres : TVA activée ou non, taux par défaut, mention fiscale, validité, préfixe et acompte par défaut. Ne pas intégrer définitivement un régime fiscal ou une identité juridique incomplète aux vues.

Acompte : aucun, pourcentage ou montant fixe. Remises : pourcentage ou montant fixe, par ligne et globalement.

Séparer le paiement initial et les lignes récurrentes ; afficher les sommes par fréquence sans additionner les mensualités au coût initial. Une option non retenue reste hors total et hors facturation automatique. L’acompte porte par défaut sur le montant initial, avec règle explicite.

Statuts : Brouillon, Prêt, Envoyé, Accepté, Refusé, Expiré, Annulé, Archivé. Actions : créer, consulter, modifier selon statut, dupliquer avec nouveau numéro, générer/visualiser/télécharger PDF, marquer envoyé/accepté/refusé et archiver. L’envoi de mail réel n’est pas requis pour « marquer envoyé ».

Numéros : `DEV-2026-0001`, séquence annuelle séparée des factures, transaction et verrouillage, contrainte unique, gestion des accès concurrents ; ne pas utiliser simplement `MAX + 1` sans verrou. Définir à quel moment un numéro est attribué et ne jamais recycler les numéros déjà attribués.

Copier les prestations et tarifs dans les lignes : une modification du catalogue ne doit jamais réécrire un devis existant. Conserver les snapshots des parties, TVA, conditions et textes applicables. Les modifications après envoi doivent produire une révision traçable ; un devis accepté doit être préservé.

## 6. PDF, documents et contrats

Aucune dépendance PDF n’est déclarée actuellement dans `composer.json`. Choisir une solution compatible PHP 8.4/Laravel 13 et l’ajouter proprement par Composer si nécessaire. Ne pas présumer qu’une impression navigateur fournit un PDF serveur téléchargeable.

PDF Codenyr : logo existant, coordonnées configurées, numéro, dates, client, projet, lignes, quantités, descriptions, remises, HT/TVA/TTC, acompte, reste prévisionnel, récurrences séparées, options, conditions et délai. Utiliser un modèle Blade dédié cohérent avec l’identité visuelle. Conserver les PDF historiques et les rattacher à leur version.

Bibliothèque Documents : recherche nom/numéro/client/projet ; filtres client, projet, type, date, année, statut. Types : devis, facture, CGV, contrat de prestation, contrat de maintenance, cahier des charges, bon de commande, attestation, document client/juridique/commercial, autre.

Un document est rattachable à un client, un projet et éventuellement un devis ou une facture. Valider la cohérence de ces relations côté serveur. Tous les fichiers présents ont une action Télécharger ; les imports proposent Télécharger l’original.

Import : nom, type, client, projet, date, description, notes ; au minimum PDF, DOCX, XLSX, PNG, JPG/JPEG. Limites configurables de taille, validation serveur des extensions et du contenu MIME, prise en compte du format ZIP des DOCX/XLSX sans autoriser arbitrairement tous les ZIP. Aucun chemin ou nom de stockage fourni par l’utilisateur.

Utiliser Laravel Storage et le disque privé `local`, déjà enraciné dans `storage/app/private`, ou un disque privé dédié. Ne pas utiliser le disque public des images du portfolio. Organisation possible : `client-projects/{id}/quotes`, `invoices`, `contracts`, `legal`, `uploads`, `files`, avec noms générés. Consultation et téléchargement passent par une route authentifiée et autorisée admin. Ne pas publier de lien direct ou temporaire contournant cette autorisation.

Gérer les erreurs et la cohérence entre transaction SQL et écritures de fichiers : pas de document marqué généré si le fichier manque, pas de remplacement destructif d’une version historique, nettoyage maîtrisé des imports échoués.

CGV et contrats : modèles éditables, aperçu, génération PDF, téléchargement, rattachement, import d’existants et archivage. Copier la version du texte et les variables utilisées lors de l’association à un dossier. Les anciens documents ne changent pas lorsque les modèles, coordonnées ou paramètres évoluent.

Contrat de prestation : parties, projet, prestations, prix, délais et modalités de paiement. Contrat de maintenance : parties, site, montant, fréquence, date de début, durée, renouvellement, prestations incluses, délai d’intervention, résiliation et notes ; actions Activer, Suspendre, Résilier, Archiver.

Conserver distinctement document généré et version signée importée, avec relation à l’original et état Signature en attente/reçue. Une importation signée ne doit pas écraser le PDF généré. Ne pas inventer des clauses ou données juridiques définitives : rendre les textes administrables et les informations manquantes identifiables.

## 7. Factures et paiements

Créer une facture manuellement ou depuis un devis accepté : reprendre les snapshots, uniquement les prestations et options retenues pertinentes pour cette facture. Types : classique, acompte, intermédiaire, solde. Préparer les relations nécessaires aux avoirs futurs.

Séparer brouillon, émission/finalisation et état de règlement. Numéros `FAC-2026-0001`, séquence indépendante, attribution atomique lors de l’émission, unicité et conservation définitive. Une facture émise conserve ses lignes, parties, dates, montants, mentions et PDF ; interdire sa suppression et sa réécriture libre côté serveur, pas seulement dans l’interface. Une correction future passe par un mécanisme comptable explicite.

Éviter la double facturation : rattacher acomptes, intermédiaires et solde au devis, déduire les montants déjà facturés selon une règle documentée, empêcher une conversion répétée accidentelle. Les mensualités de maintenance ne doivent pas être facturées en une fois avec la création du site. La génération automatique périodique et le paiement en ligne ne sont pas requis à ce stade.

PDF de facture : générer, consulter, télécharger, archiver avec snapshots et mentions configurées. L’archivage ne retire pas une facture des calculs financiers.

Paiement : facture, montant positif, date, moyen (virement, carte, chèque, espèces, autre), référence, commentaire. Calculer payé et reste dû depuis les paiements réellement enregistrés. Définir le traitement des trop-perçus et des corrections sans supprimer silencieusement l’historique ; protéger les opérations concurrentes et les doubles soumissions. Un paiement concerne une facture émise et le projet associé.

## 8. Suivi et tableau de bord

Notes : texte, auteur, date, épinglage. Fichiers de travail : logos, images, textes, cahier des charges et autres formats explicitement autorisés, séparés des documents commerciaux mais protégés de la même façon.

Timeline des événements significatifs : création, devis envoyé/accepté, document généré, version signée reçue, facture émise, paiement enregistré, changement de statut, archivage/restauration. Ne pas enregistrer chaque frappe. Enregistrer l’auteur et les références, sans exposer de secrets.

Checklist calculée depuis les données réelles : devis, acceptation, CGV et version signée, contrat et version signée, facture d’acompte et règlement, facture de solde et règlement ; maintenance et signature si applicable. Les éléments non requis ne pénalisent pas le pourcentage. Appeler ce pourcentage « Dossier administratif », distinct de l’avancement technique.

Alertes informatives : devis manquant, devis envoyé sans réponse selon seuil configurable, documents signés manquants, acompte dû, facture échue non soldée, livraison proche. Ne pas présenter comme retard une étape non applicable.

Étendre `admin.dashboard` sans reconstruire l’existant : projets commerciaux actifs, devis en attente, factures impayées, documents requis manquants, contrats de maintenance actifs, actions requises avec liens. Conserver le compteur « Réalisations » pour le portfolio et ne pas additionner deux fois prospects et projets liés.

Ajouter ensuite une recherche admin client/projet/devis/facture/document. Préférer archivage et restauration aux suppressions des dossiers et documents importants. Les projets archivés restent consultables avec leur historique complet. Distinguer les archives commerciales des archives historiques de prospects à `/admin/archives`.

## 9. Sécurité et validation

Toutes les nouvelles fonctionnalités commerciales sont admin uniquement, y compris téléchargements, aperçus et actions Livewire. Réutiliser la Gate et `AdminComponent` ; si `boot()` est redéfini, conserver l’autorisation parent. Ajouter les Policies nécessaires et protéger les identifiants/relations sensibles contre la manipulation du navigateur.

Validation Laravel côté serveur dans les composants et Form Requests pour les contrôleurs concernés, CSRF, assignation explicite des champs, transactions pour les opérations financières, requêtes paramétrées et échappement Blade. Ne jamais rendre les futurs documents accessibles aux comptes clients par simple correspondance d’e-mail.

Ne pas renvoyer des données confidentielles dans les pages publiques, le sitemap, les réalisations ou les démonstrations. Préserver HTTPS, les headers de sécurité, les paramètres de sessions et l’anti-spam existants.

## 10. Phases et critères de validation

Commencer par une synthèse de l’existant, du schéma proposé et des points de compatibilité. Ensuite réaliser les phases avec migrations additives et validation après chacune :

1. Clients et `ClientProject`, création depuis un prospect, relations explicites et protection des données historiques.
2. Liste, fiche projet et navigation ; masquer les modules non implémentés plutôt qu’afficher de faux indicateurs.
3. Catalogue et raccordement aux tarifs publics existants.
4. Devis, moteur de calcul, snapshots, statuts et numérotation.
5. PDF de devis, versions persistées et téléchargement privé.
6. Documents et fichiers de travail, imports et bibliothèque.
7. Modèles/versionnement des CGV et contrats, versions signées.
8. Factures, émission immuable, acomptes/intermédiaires/solde et PDF.
9. Paiements, suivi des règlements et protections contre doublons.
10. Maintenance et contrats associés.
11. Checklist, progression administrative et alertes.
12. Timeline complète, dashboard enrichi et recherche globale.

Ajouter les événements essentiels dès les premières phases pour conserver l’historique, puis compléter la présentation en phase 12. Ne pas interrompre inutilement le travail pour faire confirmer les choix réversibles ; demander une précision seulement si une décision métier indispensable reste indéterminable.

Tests Pest : droits invité/client/admin sur pages, actions Livewire et fichiers ; création et association client/projet/prospect ; aucune élévation de privilèges ; tarifs publics toujours synchronisés ; calculs et arrondis, remises, TVA multiple, acompte, quantités, options et récurrences ; numéros uniques, duplication et snapshots ; conversion en factures sans double facturation ; immutabilité après émission ; paiements partiels/concurrents ; imports valides et invalides ; téléchargements non autorisés ; PDF réellement produit ; versions signées ; checklist et archivage.

Préserver la suite de régression existante : formulaires publics, Fortify, vérification configurable, espace client, avis et révisions de modération, archives de prospects, tarifs, portfolio, uploads et démonstrations. La suite SQLite ne prouve pas les comportements de concurrence MySQL : prévoir leur vérification sur une base MySQL de test lorsque disponible et signaler ce qui n’a pas été vérifié.

Exécuter les contrôles adaptés à chaque phase et, avant livraison, `composer test` (Pint, PHPStan/Larastan et tests) puis `npm run build`. Mettre à jour le README avec nouvelles routes, réglages, dépendance PDF, stockage et procédure de déploiement. Rapporter les résultats réels et les limites ; ne pas prétendre avoir testé SMTP, MySQL ou OVH sans exécution correspondante. Ne pas déployer en production dans le cadre de cette implémentation locale.

## Résultat attendu

Un client peut avoir plusieurs projets ; chaque projet possède un dossier central complet. Exemple : depuis Restaurant Sithinem, créer un projet de site, sélectionner les prestations dans le catalogue, distinguer le total initial de la maintenance mensuelle, télécharger le devis, enregistrer l’acceptation, générer contrats et CGV, importer les signatures, émettre une facture d’acompte, saisir son règlement, suivre la livraison puis facturer le solde.

Tous les documents, fichiers, montants et événements sont retrouvables depuis le dossier, tout en préservant les prospects, comptes clients, avis modérés, tarifs publics et réalisations déjà présents dans Codenyr. Les exemples de prix et de dates servent à illustrer le parcours ; aucune donnée fictive ne doit être insérée automatiquement dans les dossiers réels.
