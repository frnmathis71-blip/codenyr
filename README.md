# Codenyr

Site professionnel et CRM en Laravel 13, PHP 8.4, Blade, Livewire 4 et Tailwind CSS 4. Le starter kit Fortify est conservé (mot de passe, récupération, double authentification, passkeys, paramètres du compte). L’inscription publique crée uniquement des comptes clients. Aucun compte administrateur par défaut.

## Installation

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
npm ci
npm run build
php artisan codenyr:admin
```

Sur cette installation Herd, le site est disponible sur `http://codenyr.test`. SQLite fonctionne en développement. Le schéma utilise des types compatibles MySQL.

```sh
composer dev
```

Cette commande démarre le serveur, Vite et le worker de file d’attente. Avec Herd et les assets compilés, seul le worker est nécessaire pour les e-mails : `php artisan queue:work --tries=3`.

## Données et administration

- `/admin` : indicateurs et derniers prospects.
- `/admin/prospects` : recherche, filtre, détail, statut, notes, suppression confirmée.
- `/admin/tarifs` : modification des prix des quatre offres et des trois maintenances. Montants en euros (virgule ou point accepté), enregistrés en centimes en base ; mise à jour immédiate sur l’accueil, Services et Tarifs. Les valeurs de configuration servent de valeurs initiales tant qu’aucun prix n’a été enregistré.
- `/admin/archives` : sites terminés archivés, avec recherche et restauration. Pour archiver depuis les prospects, enregistrer un devis accepté et cocher « Site livré », puis cliquer sur « Archiver ». Les archives conservent les notes et les avis clients et sont exclues du tableau de bord actif.
- `/admin/realisations` : CRUD, image principale, galerie, publication, mise en avant, identification des concepts fictifs.
- `/admin/temoignages` : CRUD, note et publication. Ne saisir que des avis authentiques avec autorisation.
- `/login` : connexion. Créer le compte via la commande interactive `php artisan codenyr:admin` (mot de passe masqué, minimum 12 caractères, majuscules/minuscules, chiffre et symbole). Le booléen `is_admin` n’est pas assignable par les formulaires publics.
- Les formulaires devis et contact enregistrent un `Lead`, puis mettent en file deux e-mails distincts. Le transport local `log` ne délivre aucun e-mail externe.
- Les formulaires ne demandent aucun budget. Le choix d’une offre affiche sa description et son contenu, sans options supplémentaires à cocher. Les anciens budgets et demandes de fonctionnalités restent consultables sur les dossiers existants.

### Comptes clients et avis modérés

1. Le client utilise **Inscription** et accède immédiatement à son compte, sans vérification d’e-mail pour le moment. **Connexion** redirige les clients vers `/espace-client` et les administrateurs vers `/admin`. Aucun inscrit ne peut devenir administrateur via le formulaire.
2. Dans **Administration → Prospects → Consulter**, saisir l’e-mail du compte client dans **Compte client associé**, mettre le devis sur **Accepté**, puis cocher **Site livré** et enregistrer. Le compte doit être associé explicitement par l’administrateur ; une simple inscription ou une correspondance d’e-mail avec une demande ne donne pas le droit de publier un avis.
3. Le client voit le projet dans son espace et peut donner une note et un avis avec son accord de publication. Un seul avis est conservé par projet.
4. Dans **Administration → Témoignages**, les avis **À modérer** apparaissent en premier. **Contrôler** affiche le texte complet. **Approuver et publier** le rend public ; **Refuser l’avis** nécessite un motif visible du client. Le texte du client ne peut pas être réécrit par le formulaire de témoignage manuel.
5. Toute modification par le client retire immédiatement l’avis du site et demande une nouvelle validation. Une version modifiée pendant la lecture de l’administrateur ne peut pas être approuvée sans être rouverte. La révocation de la livraison masque également l’avis.

La vérification d’e-mail est temporairement désactivée par défaut (`AUTH_EMAIL_VERIFICATION=false`). Aucun e-mail de vérification n’est envoyé, les comptes existants non vérifiés restent accessibles, et aucune adresse n’est artificiellement marquée comme vérifiée. Pour la réactiver : `AUTH_EMAIL_VERIFICATION=true`, puis `php artisan optimize:clear` (ou reconstruire le cache en production). Configurer alors le SMTP pour recevoir les liens. Les e-mails de devis et de récupération de mot de passe restent indépendants de ce réglage.

Les données de démonstration sont facultatives : `php artisan db:seed --class=DemoSeeder`. Trois concepts explicitement fictifs, aucun faux avis et aucun compte connu. Le seeder est idempotent et ne remplace pas les modifications existantes. Ne pas l’exécuter automatiquement en production ; dépublier les concepts locaux dans l’administration si nécessaire.

Les offres statiques se configurent dans `config/codenyr.php`. L’e-mail et les liens sociaux sont pilotés par les variables `CODENYR_*`. Les liens sociaux absents ne sont pas affichés. Le fichier de logo fourni est conservé dans `public/images/codenyr.png`.

## Gestion commerciale par projet

Les nouveaux modules restent réservés aux administrateurs et utilisent le layout Codenyr :

- `/admin/clients` : coordonnées commerciales sans création obligatoire d’un compte de connexion.
- `/admin/projets` : liste et création de dossiers commerciaux ; `/admin/projets/{id}` centralise résumé, devis, factures, paiements, documents, contrats, maintenance, fichiers, notes et historique.
- Depuis le détail d’un prospect, **Créer un projet commercial** préremplit les coordonnées et conserve un lien vers le `Lead` original. L’association n’accorde aucun nouveau droit dans l’espace client ; les avis et leur modération continuent de dépendre du prospect associé explicitement au compte client.
- `/admin/catalogue` : prestations éditables et cases de l’éditeur de devis. Les quatre offres et trois maintenances sont initialisées depuis les tarifs existants ; leurs prix restent liés à `Price`/`PricingCatalog`. Les prestations supplémentaires ont leurs propres tarifs.
- `/admin/devis` : lignes personnalisées, options, remises, TVA, acompte et récurrences distinctes. Enregistrer prépare le brouillon ; **Marquer envoyé** conserve le contenu et génère le PDF, sans envoyer automatiquement un e-mail. Les modifications suivantes passent par une duplication avec nouveau numéro.
- `/admin/factures` : factures manuelles ou depuis un devis accepté, avec acompte, montant intermédiaire et solde. L’émission attribue le numéro et fige le contenu. Les factures issues d’un devis prennent en compte les montants déjà émis ; un brouillon existant est réouvert plutôt que dupliqué. Pour facturer un abonnement seul, créer une nouvelle facture, sélectionner la prestation mensuelle/trimestrielle/annuelle du catalogue et préciser les dates de la période. Le prix saisi couvre cette période, sans prorata automatique ; le montant rejoint le total à régler et les dates figurent sur le PDF. Répéter pour chaque période à facturer. Les paiements s’enregistrent depuis le dossier projet ; les trop-perçus sont refusés.
- `/admin/documents` : imports privés, génération depuis un texte ou un modèle, aperçu PDF/image, téléchargements, archivage, versions PDF et imports signés liés aux originaux. Formats d’import : PDF, DOCX, XLSX, PNG et JPEG, 20 Mo maximum. L’extension PHP `zip` est nécessaire pour contrôler les fichiers Office. Les fichiers de travail utilisent le type dédié.
- `/admin/modeles-documents` : textes CGV et contrats administrables, avec variables `{{client}}`, `{{project}}`, `{{seller}}`, `{{amount}}`, `{{delay}}`, `{{services}}`, `{{payment}}`. Choisir un devis associé pour ses prix, prestations et conditions. Les documents enregistrés conservent leur texte et les informations du modèle utilisé.
- `/admin/parametres-commerciaux` : identité, logo des futurs documents, TVA et mentions, préfixes, validité, délais et acompte par défaut. Les informations légales et textes doivent être renseignés selon l’entreprise réelle ; aucune identité ni CGV définitive n’est inventée.
- `/admin/recherche` : recherche dans les clients, projets, devis, factures et documents. Le dashboard existant conserve ses compteurs et ajoute les indicateurs commerciaux et actions requises.

`ClientProject` et `client_projects` portent les dossiers commerciaux ; `Project` et `projects` restent le portfolio public. Les archives commerciales sont filtrées dans Projets, tandis que `/admin/archives` conserve les archives de prospects. Un projet archivé reste consultable et peut être restauré.

Les calculs utilisent des centimes et des taux/quantités au centième ; les totaux sont recalculés côté serveur. La remise globale en montant s’applique au paiement initial, la remise globale en pourcentage à chaque fréquence. Les taux de TVA saisis sur les lignes sont appliqués, même si le réglage global est désactivé : celui-ci définit seulement le taux initial des nouvelles lignes. La TVA est arrondie par taux ; les factures partielles répartissent les montants du devis et le solde conserve exactement les centimes résiduels. Le sous-total de chaque fréquence est limité à 9 999 999,99 € pour garantir des opérations entières sans dépassement.

Les documents et logos commerciaux sont stockés sur le disque privé `local` (`storage/app/private`) avec noms générés. Les téléchargements et aperçus passent par les routes admin autorisées. Les anciennes versions ne sont pas écrasées. Sauvegarder la base **et** ce stockage privé ; le lien `public/storage` n’est pas utilisé pour les documents commerciaux. La génération PDF utilise `barryvdh/laravel-dompdf`, avec ressources distantes désactivées. Les seuils des alertes sont configurables. Pour les imports de 20 Mo, prévoir `upload_max_filesize >= 20M`, `post_max_size > 20M` et une limite de requête adaptée dans le serveur web ; Livewire autorise également 20 Mo à l’étape temporaire.

Déploiement de cette évolution : sauvegarder les données, installer les dépendances Composer, exécuter `php artisan migrate --force`, compiler avec `npm ci` puis `npm run build`, déployer le manifeste avec tous les assets, et vider/reconstruire les caches Laravel. La migration est additive et ne reprend pas automatiquement les anciens prospects. Les tests locaux utilisent SQLite ; la concurrence des séquences et paiements doit aussi être vérifiée sur MySQL en recette. Aucun déploiement OVH n’est effectué automatiquement.

## Vérifications

```sh
composer test
npm run build
```

Livewire et Flux sont intégrés au bundle Vite `resources/js/app.js`. Tous les layouts utilisent `@livewireScriptConfig` et partagent ce runtime ; ne pas ajouter `@livewireScripts`, `@fluxScripts` ou une deuxième instance Alpine. Recompiler avec `npm run build` après une mise à jour Composer de Livewire/Flux et déployer le manifeste avec ses fichiers JS/CSS.

Pest couvre notamment les pages publiques, l’accès administrateur et les composants Livewire, l’inscription sans élévation de privilèges, la vérification d’e-mail, les droits de dépôt d’avis, la modération et ses versions, la validation et l’anti-spam des formulaires, les notifications, la visibilité des projets et des avis, les CRUD et les uploads. Les tests utilisent une base SQLite en mémoire et des e-mails simulés.

## Mise en production

1. PHP 8.4 avec les extensions Laravel, `fileinfo`, `pdo_mysql` et GD pour le traitement des images ; serveur pointant uniquement sur `public/`.
2. Configurer `.env` : `APP_NAME=Codenyr`, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://votre-domaine`, `APP_LOCALE=fr`, clé d’application persistante et secrets privés. Activer HTTPS et `SESSION_SECURE_COOKIE=true`.
3. Renseigner MySQL (`DB_CONNECTION=mysql`, hôte, port, base, utilisateur et mot de passe). Sauvegarder avant migration. Exécuter `php artisan migrate --force` et `php artisan storage:link`.
4. Configurer un vrai transport SMTP et une adresse d’expédition autorisée (`MAIL_*`, `CODENYR_EMAIL`). Configurer SPF/DKIM/DMARC chez le fournisseur et vérifier une réception réelle. Le mode `log` local n’est pas un envoi.
5. Installer un worker supervisé `php artisan queue:work --tries=3 --timeout=90` avec redémarrage automatique. Les messages sont distincts pour permettre leur reprise séparée. Surveiller `php artisan queue:failed` et utiliser `queue:retry` après résolution d’un incident. Ne pas journaliser les données clients durablement en production.
6. Compiler les assets, installer les dépendances sans outils de développement et exécuter `php artisan optimize`. Redémarrer le worker après chaque déploiement. Prévoir les droits d’écriture sur `storage` et `bootstrap/cache`.
7. Créer l’administrateur avec la commande interactive, activer la double authentification et prévoir les sauvegardes de la base et des images.
8. Compléter **tous les TODO** des mentions légales et de la confidentialité : identité, statut, SIRET, siège, hébergeur, responsable, prestataires, conservation et dispositions applicables. Faire valider ces textes selon le statut réel. Le contenu actuel est une trame à compléter, pas un document juridique final.
9. Confirmer le régime de TVA, les contrats de maintenance et les coordonnées ; ajouter la photo du fondateur et les liens sociaux si souhaité. Remplacer ou dépublier les concepts fictifs avant de présenter des références clients réelles.
10. Vérifier en environnement de recette les e-mails, MySQL, le stockage public, les formulaires et le compte admin. Aucun benchmark Lighthouse ni test SMTP/MySQL de production n’est présumé par les tests locaux.

La maintenance proposée reste facultative et distincte des modifications et nouvelles fonctionnalités. Aucun tracker publicitaire ou analytique tiers n’est installé. Les cookies sont techniques. Le sitemap contient les pages publiques et uniquement les réalisations publiées.

## Démonstration professionnelle Canopée

La démonstration `/demonstrations/canopee/gestion` propose une administration interactive à six modules : tableau de bord, contenus, demandes et devis (statuts et notes), clients, rendez-vous et réglages publics du studio. La page `/demonstrations/canopee/connexion` préremplit `admin@canopee.demo` et `Canopee2026!`. Il s’agit exclusivement d’une simulation côté navigateur, sans authentification Laravel ni accès à l’administration Codenyr. La session est conservée dans l’onglet ; les contenus et données fictives sont sauvegardés localement. Aucun e-mail n’est envoyé et aucun devis réel n’est émis.

## Sécurité, consentement et référencement

HTTPS est obligatoire par défaut (`FORCE_HTTPS=true`), y compris en local avec `herd secure codenyr`. Renseigner `APP_URL` en HTTPS. Les requêtes HTTP sont redirigées en 308 par Laravel (Herd applique aussi sa redirection), les sessions utilisent des cookies sécurisés et les réponses HTTPS portent HSTS. Derrière un proxy terminant TLS, configurer dans Laravel uniquement les adresses des proxies de confiance pour reconnaître le protocole transmis et éviter une boucle de redirection. Le certificat de production reste à configurer sur l’hébergeur. Les tests désactivent la redirection sauf ceux dédiés à HTTPS.

La confidentialité, les CGU (`/cgu`), la 404, les titres et descriptions, Open Graph et Twitter Card sont intégrés. L’image sociale locale fait 1200 × 630 pixels. Le bouton principal est « Demander un devis ». Aucun secret API n’a été trouvé dans les sources JS, Blade ou les bundles publics ; conserver les futurs secrets dans la configuration serveur, jamais dans une variable `VITE_*`.

Le bandeau enregistre le choix des cookies facultatifs pendant 180 jours et permet sa modification depuis le pied de page. Aucun traceur facultatif n’est actuellement installé. L’ajout futur d’un outil nécessitant le consentement devra être conditionné à ce choix avant tout chargement. Les cookies nécessaires restent actifs. Les données de démonstration ne sont pas des données clients.

Les formulaires ont une validation serveur, CSRF, un champ piège, une limitation de cinq tentatives par dix minutes et un rejet des messages contenant plus de cinq liens. Les requêtes utilisent Eloquent ou des paramètres liés ; les données soumises ne sont pas concaténées au SQL. Les contenus utilisateur sont échappés dans les vues. Ces contrôles sont couverts par les tests ; ils ne constituent pas une promesse d’absence de toute vulnérabilité.

Les textes légaux restent à compléter avec l’identité légale, les prestataires réels et les durées et procédures de conservation avant publication en production. Les polices Instrument Sans sont auto-hébergées en WOFF2 (source initiale : Bunny Fonts) et le logo public est servi en WebP.


### Espace client et documents partagés

- Dans chaque dossier, « Client et accès à l’espace client » permet de changer la fiche client et de rattacher un compte existant par son e-mail. Le client peut s’inscrire avant ou après la création du projet. Un e-mail vide retire l’accès ; un compte administrateur ne peut pas être rattaché.
- Le client connecté retrouve ses dossiers et leurs documents partagés dans `/espace-client`, avec les avis existants. Les notes et l’historique internes ne sont pas exposés. Les fichiers restent sur le disque privé, et chaque téléchargement vérifie l’association actuelle du projet au compte.
- Dans Documents ou dans les documents du dossier, « Partager dans l’espace client » publie un fichier disponible. Les PDF de devis doivent être envoyés et les factures émises ; leurs brouillons restent internes. « Retirer de l’espace client » révoque le téléchargement. Les documents existants sont internes par défaut.
- Changer le compte ou le client révoque l’ancien accès et retire tous les partages du dossier. L’administrateur choisit ensuite les pièces à partager à nouveau. Les coordonnées des documents historiques ne sont pas réécrites ; les nouveaux documents utilisent le nouveau client. Ce rattachement commercial est indépendant de l’association des prospects utilisée pour les avis.
- Les nouveaux PDF de devis affichent une quantité sans le mot « forfait ». Lorsqu’un acompte positif est prévu, le bloc d’acceptation indique son montant exact et précise qu’il doit être reçu avant le démarrage. Un devis sans acompte n’affiche pas cette clause. Les PDF déjà finalisés et conservés ne sont pas réécrits automatiquement.


### Droits sur les données et règlements

Les factures émises disposent d’un panneau « État et règlements » : saisir le paiement ou renseigner le solde puis enregistrer. L’état est calculé à partir des paiements non annulés. Une erreur de saisie peut être corrigée avec un motif ; la trace reste visible et ne constitue ni un remboursement ni un avoir.

L’espace client permet d’enregistrer une demande relative aux données et de retirer l’accord de publication d’un avis. Les administrateurs suivent les échéances et répondent dans `/admin/demandes-rgpd`. Le détail des vérifications et des informations manquantes pour la conformité se trouve dans [RGPD_AUDIT.md](RGPD_AUDIT.md). La clôture d’une demande n’exécute aucune suppression automatique.
