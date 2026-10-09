# Audit technique — 9 octobre 2026

L'audit porte sur l'état de travail local, qui contenait déjà de nombreuses modifications non commitées. Elles ont été conservées. Les contrôles automatisés valident les parcours couverts ; ils ne démontrent pas que tous les comportements possibles sont corrects.

## Architecture observée

- Laravel, Blade et Livewire constituent le système applicatif principal. Le runtime Livewire/Alpine démarre une seule fois dans `resources/js/app.js`.
- `CommercialCalculator` centralise les calculs commerciaux en entiers. `CommercialBilling` porte l'émission des devis/factures, `InvoicePayments` les règlements, `CustomerDocumentSharing` le partage de documents.
- `Price` et `PricingCatalog` pilotent les tarifs publics ; les prestations liées les réutilisent. Les autres prestations ont leurs propres prix. Il s'agit de deux catégories de données, pas de deux moteurs de calcul à fusionner.
- `Project` représente les réalisations publiques, `ClientProject` les dossiers commerciaux et `Lead` les demandes entrantes. Leurs responsabilités et droits sont distincts.

## Corrections réalisées pendant cet audit

- Les téléchargements client et administrateur utilisent désormais `DocumentDownload` pour le fichier, son nom et les en-têtes. Les contrôles d'accès restent exécutés avant ce service dans chaque contrôleur.
- Le rendu PDF des devis, factures, documents et aperçus utilise une configuration commune dans `CommercialPdf` : A4, police et désactivation des ressources distantes.
- L'administration des tarifs utilise `CommercialCalculator::decimal` au lieu d'une conversion flottante. La validation du formulaire est alignée sur les formats acceptés par ce calculateur.
- Le catalogue complet lit les prix en une seule requête au lieu de deux, sans cache susceptible de masquer une modification de tarif.
- Les alertes et listes de contrôle réutilisent les relations préchargées du tableau de bord. Les paramètres commerciaux sont lus une fois avant la boucle des projets ; les paiements sont préchargés pour éviter une requête par facture.
- La validation d'association d'un devis dans `CommercialDocumentResolver` exige explicitement un projet et passe l'analyse statique.
- Le formatage des tests juridiques a été corrigé. Les scripts de travail du dossier `tmp` sont exclus du contrôle de style applicatif.

Des tests supplémentaires vérifient les montants extrêmes au centime, la lecture unique des tarifs avec visibilité immédiate des modifications, et l'absence de requêtes supplémentaires lors du calcul des alertes et listes de contrôle sur des relations préchargées.

## Unification des contrats encore incomplète

Le projet ne passe pas encore entièrement par un seul moteur de contrats :

1. `ProjectLegalDocuments::prepare` sélectionne une implémentation historique ou `CommercialDocumentResolver` selon `engine_version`.
2. `CommercialDocuments::legalCandidate` ne renseigne pas cette version ; le formulaire courant continue donc d'utiliser le moteur historique. La création en base utilise également la valeur historique par défaut.
3. La génération d'un contrat de maintenance dans l'interface compose encore son texte directement. La branche maintenance du nouveau résolveur n'est pas raccordée à ce parcours.
4. Les révisions de documents doivent conserver explicitement la version du moteur et les relations structurées introduites par la nouvelle migration, avant de pouvoir généraliser ce nouveau parcours.

La suppression immédiate du moteur historique modifierait la priorité des sources, les substitutions et les contrôles des anciens brouillons. Les documents finalisés doivent continuer à restituer leur contenu et leurs PDF figés. L'unification complète nécessite un adaptateur explicite des anciennes données et des tests de migration/révision ; elle n'est pas déclarée terminée par cet audit.

## Limites de vérification

- Les tests utilisent SQLite en mémoire, des fichiers temporaires et des transports de messagerie simulés. Aucune migration n'a été lancée sur la base de travail.
- La concurrence des numéros et des règlements sur MySQL, la livraison SMTP et le déploiement réel restent à vérifier dans leur environnement.
- La compilation des assets ne remplace pas un contrôle interactif de tous les parcours dans un navigateur.
- Le tableau de bord charge encore l'ensemble des dossiers actifs et des factures pour certains indicateurs. Une agrégation SQL ou pagination pourra devenir nécessaire avec un volume important ; aucun benchmark de charge n'a été effectué.
- `Service::currentPrice` peut encore relire le catalogue pour chaque prestation liée. Le cache éventuel doit avoir une invalidation explicite pour conserver la mise à jour immédiate des prix.

## Commandes de vérification

`composer test` exécute le formatage, PHPStan puis Pest. `npm run build` compile les assets. Les cinq tests Fortify ignorés concernent des fonctionnalités désactivées par configuration ; les tests dédiés au comportement sans vérification d'e-mail restent exécutés.

Résultat final : Pint valide, PHPStan sans erreur, 216 tests réussis, 5 ignorés et 1 313 assertions. La compilation front réussit également. `git diff --check` ne relève pas d'erreur d'espacement.
