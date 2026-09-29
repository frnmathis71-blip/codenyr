# Vérification locale — 30 septembre 2026

## Tarifs administrables

- `/admin/tarifs`, réservé aux administrateurs : quatre prix de création et trois prix mensuels de maintenance. Virgule, point et séparateurs d’espaces acceptés ; prix négatifs, trop grands ou comportant plus de deux décimales refusés.
- Stockage en centimes dans `prices`, migration appliquée localement. Sauvegarde transactionnelle et affichage partagé sur Accueil, Services et Tarifs. Les prix actuels sont conservés par défaut ; aucune modification des prix publics pendant le contrôle navigateur.
- Écran et libellés contrôlés avec un compte temporaire. Tests couvrant les permissions, la persistance, l’affichage public et le rejet des données invalides sans sauvegarde partielle.

## Navigation multipage et avis clients

- Navigation ordinateur/mobile identique sur les pages : Accueil, Services, Réalisations, Avis clients, Tarifs, À propos et Contact. Les liens de découverte de l’accueil ouvrent les pages dédiées ; les offres mènent à leur détail dans Services.
- Page `/avis` et lien dans le pied de page, intégration au sitemap. Avis publiés uniquement, texte complet, pagination par 12, état vide explicite.
- Cartes partagées entre l’accueil et la page Avis : auteur et entreprise identifiables, contour, séparation du nom et du texte, étoiles jaunes et note accessible sur cinq.

## Nouvelle page d’accueil

- Accueil repensé : composition éditoriale, aperçu interactif vitrine/administration, réalisations, offres directement reliées au devis présélectionné, méthode, FAQ native et appel à l’action final.
- Navigation initiale par sections remplacée ensuite par la navigation multipage ci-dessus ; menu mobile refermé après activation d’un lien, libellé d’ouverture/fermeture et retour du focus avec Échap. Aucun carrousel automatique ; prise en compte de la réduction des animations.
- Illustrations réalisées en HTML/CSS, sans nouvelle dépendance JS ni image externe. Les concepts et données de démonstration restent explicitement signalés.
- Contrôle navigateur à 320, 390, 768 px et au format ordinateur : pas de débordement horizontal. Bascule de l’aperçu, navigation mobile et ouverture de la FAQ au clavier vérifiées. Build validé ; 42 tests des pages publiques et du tableau de bord réussis (180 assertions).

## Archives et formulaire simplifié

- Migration `archived_at` appliquée localement. Archivage réservé aux devis acceptés et sites livrés, archives consultables et restaurables, exclusion des listes et compteurs actifs. Les notes, rattachements clients et avis sont conservés.
- Budget retiré des demandes, fonctionnalités supplémentaires remplacées par le descriptif et le contenu de l’offre sélectionnée. Les anciens champs envoyés depuis un formulaire déjà ouvert sont ignorés côté serveur.
- Contrôle de visibilité du mot de passe avec un seul SVG et suppression du contrôle natif supplémentaire d’Edge. Cache des vues vidé pour activer la surcharge Flux.
- Navigateur : changement d’offre et contenu associé, icône unique et bascule mot de passe visible/masqué, archivage et restauration d’un projet temporaire. Tests : 89 réussis, 372 assertions, 5 ignorés ; Pint, PHPStan et build validés.

## Chargement des interactions et administration

- Livewire et Flux sont compilés ensemble par Vite et démarrés une seule fois ; les sept layouts partagent `@livewireScriptConfig`. Les scripts ne nécessitent plus deux routes PHP séparées pour leur téléchargement et leur URL change avec le contenu du build.
- Les six comptages de prospects sont regroupés en une requête SQL. Le tableau de bord et la liste ne chargent que les colonnes affichées, sans les descriptions et notes complètes.
- Vérification navigateur : affichage puis masquage du mot de passe au clavier, connexion administrateur, liste et détail d’un nouveau projet, sauvegarde des notes, paramètres puis retour à l’administration. Aucune erreur JS relevée sur ces parcours. Compte et projet temporaires supprimés.
- Le clic automatisé à la souris n’a pas permis une vérification fiable du bouton ; l’activation au clavier a été contrôlée. La boucle de chargement signalée n’a pas été reproduite dans le parcours final, sa cause initiale n’est donc pas établie avec certitude.
- `composer test` : Pint et PHPStan validés, 80 tests réussis, 316 assertions, 5 tests ignorés (vérification d’e-mail désactivée). Build et `git diff --check` validés. Pas de benchmark global de latence ni de déploiement distant.

## Vérification d’e-mail temporairement désactivée

- `AUTH_EMAIL_VERIFICATION=false` par défaut : aucun envoi de vérification, accès immédiat après inscription et pour les comptes existants non vérifiés.
- Le rattachement administratif accepte ces comptes. Le devis accepté, la livraison confirmée et la modération des avis restent obligatoires.
- Les mentions de vérification sont masquées dans l’inscription, le profil et les informations clients. Les routes de vérification sont absentes. Le réglage permet de réactiver ce parcours sans falsifier `email_verified_at`.
- Tests : 74 réussis, 292 assertions ; 5 tests de vérification du starter kit ignorés car la fonctionnalité est désactivée. Analyse statique et build validés.
- Page d’inscription locale contrôlée : le message d’accès immédiat est affiché et l’ancienne promesse d’e-mail de vérification est absente.

## Ajout de l’espace client et des avis modérés

- Inscription publique client activée, vérification d’e-mail conservée ; aucun accès administrateur accordé à l’inscription.
- Suite étendue : 76 tests réussis, 292 assertions, aucun test ignoré. Pint et PHPStan passent.
- Connexion client et administrateur, dépôt d’un avis, affichage « À modérer », consultation du texte complet et refus motivé vérifiés dans le navigateur. Les règles d’approbation, la remise en modération après édition et les conflits de version sont couverts par les tests.
- Espace client contrôlé à 375, 768, 1024 et 1440 px, sans débordement ; inscription et menu avec Connexion/Inscription contrôlés à 375 px.
- Les deux comptes, le projet et l’avis temporaires du contrôle navigateur ont été supprimés. Aucun avis de test n’a été publié.
- SMTP toujours à configurer pour la réception réelle des e-mails de vérification ; le transport local reste `log`.

## Vérification initiale du site

Environnement : Windows, Herd, PHP 8.4.25, Laravel 13, SQLite, transport mail `log`.

- `composer test` : Pint, PHPStan niveau 7 et Pest. 61 tests réussis, 202 assertions. 2 tests du starter kit ignorés car l’inscription est désactivée ; la fermeture des routes GET et POST d’inscription est testée séparément.
- `npm run build` : compilation de production réussie, polices hébergées localement.
- `php artisan view:cache` : compilation Blade réussie.
- `git diff --check` : pas d’erreur d’espacement.
- Navigateur : accueil, services, tarifs, portfolio, à propos, devis, contact et quatre pages administrateur mesurés à 375, 768, 1024 et 1440 px : aucun débordement horizontal.
- Menu mobile testé ; connexion et déconnexion administrateur testées ; formulaire d’édition d’une réalisation enregistré avec succès.
- Contact et devis soumis avec des coordonnées fictives locales. Confirmation du devis observée dans le navigateur ; deux messages traités pour chaque demande par le worker, sans échec. Aucun envoi SMTP externe : les messages ont été écrits dans le journal local.
- Les demandes fictives et le compte administrateur temporaire ont été supprimés après vérification.
- Trois illustrations de concepts de portfolio chargées et vérifiées dans le navigateur. Aucune référence à un véritable client ni faux avis.
- Aucune erreur JavaScript relevée dans la session de vérification.

Captures locales, exclues de Git : `storage/app/qa/codenyr-desktop.png`, `codenyr-mobile.png` et `codenyr-admin.png`.

Limites : pas de déploiement public, de mesure Lighthouse chiffrée, d’audit d’accessibilité externe ni de validation sur MySQL/SMTP de production. Les pages légales restent explicitement à compléter. Le guide de déploiement et les points à renseigner figurent dans le README.
