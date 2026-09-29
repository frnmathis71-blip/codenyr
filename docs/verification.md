# Vérification locale — 29 septembre 2026

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
