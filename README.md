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
- `/admin/realisations` : CRUD, image principale, galerie, publication, mise en avant, identification des concepts fictifs.
- `/admin/temoignages` : CRUD, note et publication. Ne saisir que des avis authentiques avec autorisation.
- `/login` : connexion. Créer le compte via la commande interactive `php artisan codenyr:admin` (mot de passe masqué, minimum 12 caractères, majuscules/minuscules, chiffre et symbole). Le booléen `is_admin` n’est pas assignable par les formulaires publics.
- Les formulaires devis et contact enregistrent un `Lead`, puis mettent en file deux e-mails distincts. Le transport local `log` ne délivre aucun e-mail externe.

### Comptes clients et avis modérés

1. Le client utilise **Inscription** et accède immédiatement à son compte, sans vérification d’e-mail pour le moment. **Connexion** redirige les clients vers `/espace-client` et les administrateurs vers `/admin`. Aucun inscrit ne peut devenir administrateur via le formulaire.
2. Dans **Administration → Prospects → Consulter**, saisir l’e-mail du compte client dans **Compte client associé**, mettre le devis sur **Accepté**, puis cocher **Site livré** et enregistrer. Le compte doit être associé explicitement par l’administrateur ; une simple inscription ou une correspondance d’e-mail avec une demande ne donne pas le droit de publier un avis.
3. Le client voit le projet dans son espace et peut donner une note et un avis avec son accord de publication. Un seul avis est conservé par projet.
4. Dans **Administration → Témoignages**, les avis **À modérer** apparaissent en premier. **Contrôler** affiche le texte complet. **Approuver et publier** le rend public ; **Refuser l’avis** nécessite un motif visible du client. Le texte du client ne peut pas être réécrit par le formulaire de témoignage manuel.
5. Toute modification par le client retire immédiatement l’avis du site et demande une nouvelle validation. Une version modifiée pendant la lecture de l’administrateur ne peut pas être approuvée sans être rouverte. La révocation de la livraison masque également l’avis.

La vérification d’e-mail est temporairement désactivée par défaut (`AUTH_EMAIL_VERIFICATION=false`). Aucun e-mail de vérification n’est envoyé, les comptes existants non vérifiés restent accessibles, et aucune adresse n’est artificiellement marquée comme vérifiée. Pour la réactiver : `AUTH_EMAIL_VERIFICATION=true`, puis `php artisan optimize:clear` (ou reconstruire le cache en production). Configurer alors le SMTP pour recevoir les liens. Les e-mails de devis et de récupération de mot de passe restent indépendants de ce réglage.

Les données de démonstration sont facultatives : `php artisan db:seed --class=DemoSeeder`. Trois concepts explicitement fictifs, aucun faux avis et aucun compte connu. Le seeder est idempotent et ne remplace pas les modifications existantes. Ne pas l’exécuter automatiquement en production ; dépublier les concepts locaux dans l’administration si nécessaire.

Les offres statiques se configurent dans `config/codenyr.php`. L’e-mail et les liens sociaux sont pilotés par les variables `CODENYR_*`. Les liens sociaux absents ne sont pas affichés. Le fichier de logo fourni est conservé dans `public/images/codenyr.png`.

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
