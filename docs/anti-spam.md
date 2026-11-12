# Protection anti-spam des formulaires publics

Formulaires concernés : **contact** (`/contact`), **demande de devis** (`/devis`) et **inscription** (`/register`).
Le site n’a pas de formulaire de newsletter. La connexion et « mot de passe oublié » restent protégés par la limitation de débit de Fortify.

## Ce qui est en place

| Mesure | Détail | Réglage |
| --- | --- | --- |
| reCAPTCHA v3 | Jeton demandé à chaque envoi, action propre à chaque formulaire (`contact`, `devis`, `inscription`), vérifié côté serveur auprès de Google. Refus si le jeton est absent, invalide, expiré, si l’action ne correspond pas ou si le score est sous le seuil. | `RECAPTCHA_THRESHOLD` |
| Repli reCAPTCHA v2 | Score douteux (entre les deux seuils) : la case « Je ne suis pas un robot » s’affiche au lieu d’un refus. Sans clés v2, un score douteux est refusé. | `RECAPTCHA_CHALLENGE_THRESHOLD` |
| Champ piège | Champ `fax` masqué en CSS. Rempli : refus silencieux (contact et devis affichent la confirmation habituelle, rien n’est enregistré ni envoyé). | — |
| Délai minimum | Refus des envois faits moins de 3 s après l’affichage. | `CODENYR_SPAM_MIN_SECONDS` |
| Limitation de débit | 5 envois par adresse IP et par formulaire sur 10 minutes, puis HTTP 429. Contact et devis : aussi 15 par jour et 3 par heure pour une même adresse e-mail. | `CODENYR_SPAM_MAX_ATTEMPTS` |
| Nettoyage des entrées | HTML supprimé, sauts de ligne et caractères de contrôle retirés des champs d’une ligne (pas d’injection d’en-têtes d’e-mail), formats e-mail et téléphone, longueurs maximales. | — |
| Filtrage du contenu | Refus au-delà de 2 liens ou si le message contient une expression de la liste. | `CODENYR_SPAM_MAX_LINKS`, `CODENYR_SPAM_KEYWORDS` |
| CSRF | Jeton Laravel sur l’inscription (`@csrf`), jeton et somme de contrôle Livewire sur contact et devis. | — |
| Journal | Chaque refus est écrit dans `storage/logs/spam-AAAA-MM-JJ.log` (30 jours) : date, formulaire, motif, score, IP tronquée. Aucun e-mail n’est envoyé pour un envoi refusé. | — |

Le visiteur refusé voit toujours le même message, sans la règle en cause : « Votre demande n’a pas pu être envoyée, merci de réessayer. »

Si Google ne répond pas (panne, délai dépassé), l’envoi est accepté et un avertissement est écrit dans `storage/logs/laravel.log` : les autres protections continuent de s’appliquer et aucune demande réelle n’est perdue.

## Variables d’environnement

| Variable | Rôle | Défaut |
| --- | --- | --- |
| `RECAPTCHA_SITE_KEY` | Clé du site reCAPTCHA **v3** | vide = reCAPTCHA désactivé |
| `RECAPTCHA_SECRET_KEY` | Clé secrète reCAPTCHA **v3** | vide = reCAPTCHA désactivé |
| `RECAPTCHA_V2_SITE_KEY` | Clé du site reCAPTCHA **v2 case à cocher** (repli) | vide = pas de repli |
| `RECAPTCHA_V2_SECRET_KEY` | Clé secrète reCAPTCHA **v2** | vide = pas de repli |
| `RECAPTCHA_THRESHOLD` | Score à partir duquel l’envoi est accepté | `0.5` |
| `RECAPTCHA_CHALLENGE_THRESHOLD` | Score à partir duquel la case à cocher est proposée | `0.3` |
| `CODENYR_SPAM_MIN_SECONDS` | Délai minimum avant envoi (0 désactive) | `3` |
| `CODENYR_SPAM_MAX_ATTEMPTS` | Envois par IP et par formulaire sur 10 minutes | `5` |
| `CODENYR_SPAM_MAX_LINKS` | Liens autorisés dans un message | `2` |
| `CODENYR_SPAM_KEYWORDS` | Expressions de spam séparées par des virgules ; remplace la liste de `config/codenyr.php` | liste par défaut |

Après modification du `.env` en production : `php artisan config:cache`.

## Créer les clés reCAPTCHA

Une clé v3 et une clé v2 sont deux clés différentes : il en faut une paire de chaque type.

1. Ouvrir <https://www.google.com/recaptcha/admin/create> avec le compte Google de Codenyr.
2. Clé v3 : libellé « Codenyr v3 », type **Basé sur un score (v3)**, domaine `codenyr.fr` (ajouter `codenyr.test` ou `localhost` pour les essais locaux). Valider, puis copier la clé du site dans `RECAPTCHA_SITE_KEY` et la clé secrète dans `RECAPTCHA_SECRET_KEY`.
3. Clé v2 : recommencer avec le libellé « Codenyr v2 », type **Test (v2)** puis **Case à cocher « Je ne suis pas un robot »**, mêmes domaines. Copier les clés dans `RECAPTCHA_V2_SITE_KEY` et `RECAPTCHA_V2_SECRET_KEY`.
4. Lancer `php artisan config:cache`, puis recharger `/contact` : la mention « Ce site est protégé par reCAPTCHA » apparaît sous le formulaire.

Les clés secrètes ne figurent que dans le `.env` du serveur, jamais dans le dépôt.

## Consentement (RGPD)

reCAPTCHA dépose des cookies Google : il n’est chargé qu’après accord du visiteur, donné dans le bandeau de cookies ou dans l’encadré « Protection anti-robots » du formulaire. Sans accord, le formulaire ne peut pas être envoyé et l’adresse e-mail de contact est proposée à la place. Le badge flottant est masqué et remplacé par la mention exigée par Google. La politique de confidentialité et le bandeau décrivent reCAPTCHA dès que les clés v3 sont renseignées.

## Tester

Les tests automatisés couvrent tous les cas ci-dessous avec des réponses Google simulées : `php artisan test --filter=AntiSpam`.

Pour suivre les refus pendant les essais manuels : `tail -f storage/logs/spam-*.log`.

1. **Envoi normal** — ouvrir `/contact`, autoriser reCAPTCHA, remplir le formulaire en prenant quelques secondes, envoyer. Attendu : écran de confirmation, demande visible dans `/admin/prospects`, deux e-mails (notification et accusé de réception), rien dans le journal de spam.
2. **Robot simulé, sans jeton** — envoyer l’inscription sans passer par le navigateur :

   ```bash
   curl -i -c jar.txt https://codenyr.fr/register -A "Mozilla/5.0" -o page.html
   TOKEN=$(grep -o 'name="_token" value="[^"]*"' page.html | cut -d'"' -f4)
   STARTED=$(grep -o 'name="started" value="[^"]*"' page.html | cut -d'"' -f4)
   sleep 4
   curl -i -b jar.txt -A "Mozilla/5.0" https://codenyr.fr/register \
     --data-urlencode "_token=$TOKEN" --data-urlencode "started=$STARTED" \
     -d "name=Robot" -d "email=robot@example.com" -d "password=Mot-de-passe-solide-2026" -d "password_confirmation=Mot-de-passe-solide-2026"
   ```

   Attendu : redirection vers le formulaire avec le message générique, aucun compte créé, ligne `captcha_missing` dans le journal. Ajouter `-d "fax=x"` donne `honeypot` ; retirer le `sleep` donne `too_fast`.
3. **Score faible et case à cocher** — mettre provisoirement `RECAPTCHA_THRESHOLD=1.1` (aucun score ne peut l’atteindre) et `RECAPTCHA_CHALLENGE_THRESHOLD=0.1`, puis envoyer un formulaire depuis le navigateur. Attendu : la case « Je ne suis pas un robot » apparaît ; une fois cochée, l’envoi passe. Avec `RECAPTCHA_CHALLENGE_THRESHOLD=1.1` aussi, l’envoi est refusé (`captcha_low_score` et le score réel dans le journal). Remettre ensuite `0.5` et `0.3`.
4. **Contenu indésirable** — envoyer un message contenant trois liens, ou le mot « backlinks ». Attendu : message générique, lignes `too_many_links` ou `spam_keyword`.
5. **Dépassement de la limite** — envoyer six fois de suite le même formulaire en moins de dix minutes (des envois refusés comptent aussi). Attendu : au sixième, HTTP 429 (visible dans l’onglet Réseau) et le message « Trop de tentatives », ligne `rate_limit`. Pour repartir de zéro : `php artisan cache:clear`.

## Ajuster le seuil

Le journal donne le score de chaque refus lié à reCAPTCHA, et la console Google (<https://www.google.com/recaptcha/admin>) la répartition des scores de tout le trafic. Si de vraies demandes sont refusées (`captcha_low_score` suivi d’un e-mail du visiteur), baisser `RECAPTCHA_THRESHOLD` à `0.4` ; si du spam passe, le monter à `0.6` ou `0.7`. Garder `RECAPTCHA_CHALLENGE_THRESHOLD` environ 0,2 en dessous.
