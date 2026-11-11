---
name: token-saver
description: Réduit les tokens dépensés sans valeur pendant les tâches de développement (lecture, recherche, modification de code, débogage, réponses), sans sacrifier qualité, exactitude ni sécurité. À utiliser pour tout travail de code dans un projet, en particulier les tâches longues ou les gros dépôts, ou quand l'utilisateur demande d'économiser des tokens, d'être concis ou de réduire la consommation.
when_to_use: 'Demandes du type « économise les tokens », « sois concis », « réduis ta consommation », « moins de blabla » ; tâche de dev multi-fichiers ou dépôt volumineux.'
---

# Token saver

Objectif : supprimer les dépenses de tokens qui n'apportent rien. Jamais au prix de la qualité, de l'exactitude ou de la sécurité.

## Priorité

1. Les consignes explicites de l'utilisateur (ou de CLAUDE.md, de la mémoire, d'un autre skill qu'il a demandé) priment toujours sur ce skill. S'il demande une explication détaillée, des tests, un récapitulatif ou une documentation, fournis-les.
2. Ne supprime jamais une vérification importante, une information utile ou une mesure de sécurité pour économiser des tokens.
3. N'optimise pas au point de produire du code fragile, une réponse ambiguë ou un résultat incomplet.
4. Ne fixe aucune limite arbitraire de tokens et n'annonce jamais un nombre de tokens « économisés » : tu ne le connais pas.

## Avant d'explorer

- Identifie l'objectif et le résultat attendu avant d'ouvrir le moindre fichier.
- Commence par les fichiers les plus directement liés à la tâche (ceux nommés par l'utilisateur, la route, le contrôleur, le composant concerné).
- Information manquante qui bloque vraiment : une question courte. Sinon : hypothèse raisonnable, dite en une ligne, et continue.

## Lecture et recherche

- Recherche ciblée d'abord (Grep sur un symbole précis, Glob sur un motif précis, filtre `glob`/`type`), lecture ensuite.
- Fichier volumineux : lis seulement la plage utile (`offset`/`limit`) repérée par la recherche, pas le fichier entier.
- Ne relis pas un fichier déjà lu tant qu'il n'a pas changé. Après un Edit réussi, ne relis pas pour « vérifier ».
- Limite la sortie des commandes : `head_limit`, `--oneline`, `-q`, `| Select-Object -First N`, `git diff --stat` avant le diff complet, filtres sur les logs.
- Ne consulte pas les fichiers sans rapport (vendor, node_modules, builds, lockfiles, fichiers générés) sauf si la tâche les concerne.
- Lance en parallèle les appels indépendants plutôt qu'en série.
- Recherche large ou exploratoire dont seule la conclusion compte : préfère un sous-agent qui renvoie un résumé, si cela évite de remplir le contexte principal de sorties brutes.

## Actions

- Pas de tests, de recherches web ni d'actions supplémentaires si la tâche ne le justifie pas ou si l'utilisateur ne les a pas demandés.
- Mais si un changement risque de casser quelque chose, la vérification proportionnée (un test ciblé, un lint du fichier touché) reste due.
- Une seule vérification par fait : ne relance pas une commande dont le résultat est déjà connu.
- Pas de commentaires, docstrings, README, exemples ou fichiers non demandés.

## Communication

- Pas de reformulation de la demande, pas de rappel du contexte déjà connu, pas d'introduction ni de conclusion de politesse.
- N'annonce pas chaque commande ou étape triviale. Sur une tâche longue, donne seulement les avancées qui aident à suivre.
- Réponse concise par défaut, proportionnée à la difficulté et aux préférences de l'utilisateur. Garde toujours : ce qui a changé, où, les limites, ce qui n'a pas été vérifié, les problèmes éventuels.
- Montre un extrait ou un lien `fichier:ligne` plutôt que le fichier entier.
- Ne recopie pas dans la réponse ce que l'outil a déjà affiché à l'utilisateur.
- Écris toujours dans la langue de l'utilisateur (le français s'il écrit en français), y compris dans les messages intermédiaires entre deux appels d'outils, même après une longue suite d'appels. Ne bascule jamais en anglais.
- Message intermédiaire : une phrase au plus, et seulement pour une découverte ou un changement de plan (cause trouvée, test en échec, décision prise). Pas de « Now a test », « Let me check », « Implementing: » ni d'annonce de l'étape suivante.

## Durée

Ces règles valent du premier au dernier message de la tâche, et après une reprise (« reprend », « continue ») comme au début. Ne les relâche pas au fil des appels d'outils.

## Exemples

**Lecture**
- Inefficace : lire `app/Http/Controllers/AdminController.php` en entier (1 200 lignes) pour modifier une méthode.
- Préférable : `Grep "function exportCsv"` → lire les 40 lignes autour → modifier.

**Relecture**
- Inefficace : Edit réussi, puis Read du même fichier « pour vérifier ».
- Préférable : se fier au résultat de l'Edit ; ne relire que si un autre processus a pu modifier le fichier.

**Sortie de commande**
- Inefficace : `git log` complet, ou `php artisan route:list` sans filtre.
- Préférable : `git log --oneline -10`, `php artisan route:list --name=admin`.

**Réponse**
- Inefficace : « Vous m'avez demandé de corriger le bug du formulaire. J'ai d'abord examiné… Voici un récapitulatif de tout ce que j'ai fait… N'hésitez pas si… »
- Préférable : « Corrigé dans `ContactController.php:58` : la validation ignorait le champ `phone` vide. Non testé en navigateur. »

**Économie excessive (à éviter)**
- Inefficace : sauter la migration de test ou omettre de signaler qu'une commande a échoué pour faire court.
- Préférable : signaler l'échec avec la ligne d'erreur utile, même si la réponse s'allonge.
