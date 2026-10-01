# Codenyr — audit RGPD et suivi des factures

État au 1er octobre 2026. Périmètre : code et tests de l’application locale. Le serveur de production, les contrats et les pratiques de l’entreprise ne sont pas vérifiés. Ce document ne constitue pas une certification de conformité.

## Corrections réalisées

- Les demandes d’accès, rectification, effacement, limitation, opposition et portabilité peuvent être enregistrées dans « Mes données personnelles ». Elles sont liées au compte authentifié ; un client ne voit pas celles des autres. Le contact par e-mail reste disponible sans compte.
- L’administration dispose de « Demandes RGPD », avec échéance initiale d’un mois, indication du retard et réponse conservée. Un indicateur figure sur le tableau de bord. Clôturer une demande n’exécute pas un effacement et n’envoie pas d’e-mail : l’administrateur doit effectuer les opérations et communiquer la réponse si le compte n’existe plus.
- Les nouvelles soumissions d’avis enregistrent la date et la version du consentement (`review-publication-2026-10-01`). Le texte accepté est celui affiché à la soumission : publication du nom, de l’entreprise, de la note et de l’avis après modération. Les anciens avis ne reçoivent aucune preuve de consentement inventée.
- Le client peut retirer son accord depuis son espace, même si son ancien prospect a été réattribué. Le retrait masque immédiatement l’avis ; une nouvelle approbation administrative est refusée tant qu’il n’a pas été soumis à nouveau avec accord.
- La suppression du compte efface aussi les sessions en base et les jetons de réinitialisation, retire les partages de documents et les associations au compte. Le texte de confirmation explique la conservation distincte des pièces contractuelles/comptables.
- La politique décrit désormais les dossiers, les documents privés, les règlements, les droits et la distinction entre suppression du compte et obligations de conservation.

## Contrôles existants vérifiés

| Point | Constat local |
| --- | --- |
| Documents | Disque privé, contrôle de l’utilisateur à chaque téléchargement, partage explicite, brouillons exclus. |
| Réattribution | Ancien accès révoqué et partages retirés. Documents historiques conservés. |
| Administration | Autorisation serveur sur les composants, les actions et les téléchargements. |
| Navigateur | Aucun outil de publicité ou mesure d’audience tiers trouvé dans les vues et scripts actifs examinés. Choix facultatif conservé 180 jours ; aucun traceur n’est activé par acceptation. |
| Authentification | Mots de passe hachés, protection CSRF, limitations de tentatives et option de double authentification. |
| Cache | Pages privées et téléchargements servis avec des directives de non-conservation. |
| Avis | Validation, accord explicite et modération avant publication ; e-mails non publics. |

## Points ouverts qui empêchent de conclure à une conformité complète

1. **Identité et prestataires** : les mentions légales comportent encore des TODO. Confirmer identité légale, adresse, immatriculation et statut, hébergeur, service d’e-mail, localisation des données, sous-traitants, transferts éventuels hors EEE et garanties applicables. Ne pas déduire le prestataire de production de la configuration locale.
2. **Conservation et purge** : pas de purge automatique métier. Définir un calendrier par finalité pour prospects, échanges, comptes inactifs, contrats, fichiers de travail, demandes de droits, traces, sauvegardes et e-mails. L’archivage applicatif ne vaut pas effacement ni archivage intermédiaire à accès distinct. Ne pas supprimer les pièces protégées par une obligation ou un litige.
3. **Prospects** : le repère CNIL de trois ans concerne les données de prospection, à compter de la collecte ou du dernier contact venant du prospect. Ne pas assimiler une modification administrative de `updated_at` à un contact entrant. Définir et consigner le point de départ avant toute purge.
4. **Pièces comptables** : prévoir dix ans à compter de la clôture de l’exercice concerné. Le logiciel ne connaît pas encore la date de clôture de chaque exercice et n’applique donc aucune suppression automatique de ces pièces.
5. **Demandes de droits** : organiser la consultation régulière de l’écran et de la boîte e-mail, la vérification d’identité proportionnée, la copie complète des données depuis les différents systèmes et la réponse motivée. Ne transmettre aucune donnée de tiers. Définir aussi la durée de conservation des demandes clôturées et de leurs preuves.
6. **Production** : contrôler HTTPS, `APP_DEBUG=false`, secrets hors dépôt, accès SSH/base, mises à jour, comptes administrateur, sauvegardes chiffrées/testées et durée des journaux. Le mailer de développement peut journaliser des contenus : confirmer le transport réellement utilisé avant publication. Le code seul ne démontre pas ces réglages.
7. **Organisation** : tenir le registre des traitements, encadrer les prestataires et préparer une procédure de violation de données. Recenser les données conservées hors application (messagerie, ordinateur, sauvegardes). Conserver les preuves de consentement des avis anciens obtenus autrement.

Aucune donnée existante n’a été purgée pendant cet audit et aucun e-mail n’a été envoyé.

## Factures : état et corrections

Dans la facture, saisir un règlement met à jour « À régler », « Partiellement payée » ou « Payée » selon les montants réellement reçus. « Renseigner le solde » remplit le montant restant ; il faut ensuite enregistrer le règlement. Les dates futures, les montants supérieurs au solde et les règlements sur brouillons sont refusés.

« Corriger cette saisie » nécessite un motif et conserve le règlement avec une date d’annulation de saisie. Cela corrige le suivi uniquement, ne rembourse pas le client et n’annule pas la facture. Les totaux du projet excluent les saisies annulées. Le contenu d’une facture émise reste figé. La gestion comptable des avoirs reste à ajouter si une facture doit être annulée ou corrigée.

## Références consultées

- [CNIL — information et transparence](https://cnil.fr/fr/conformite-rgpd-information-des-personnes-et-transparence)
- [CNIL — cookies et traceurs](https://www.cnil.fr/fr/cookies-et-autres-traceurs/regles/cookies/comment-mettre-mon-site-web-en-conformite)
- [CNIL — droits des personnes](https://www.cnil.fr/fr/passer-laction/les-droits-des-personnes-sur-leurs-donnees)
- [CNIL — durées de conservation](https://www.cnil.fr/fr/passer-laction/les-durees-de-conservation-des-donnees)
- [CNIL — fichiers clients et prospects](https://www.cnil.fr/fr/questions-reponses-sur-les-referentiels-relatifs-la-gestion-des-activites-commerciales-et-des)
- [Service Public — obligations comptables](https://entreprendre.service-public.gouv.fr/vosdroits/F21852)
- [Service Public — facturation](https://entreprendre.service-public.gouv.fr/vosdroits/F23208)
