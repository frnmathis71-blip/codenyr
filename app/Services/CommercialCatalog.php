<?php

namespace App\Services;

use App\Models\Service;

class CommercialCatalog
{
    /** Options merged into others or no longer sold on their own. Archived, never deleted: old quotes keep their lines. */
    public const RETIRED = ['automation', 'newsletter', 'changes', 'development', 'gallery', 'blog', 'maps'];

    /**
     * Internal guidance for the admin assistant, keyed by offer / maintenance price key.
     * Never printed on quotes (unlike the description).
     */
    public const PLAN_NOTES = [
        'offer_landing' => '1 page, 6 sections incluses. Pour lancer une activité, un événement ou une offre unique. Dès que le client veut plusieurs pages (services, réalisations, contact séparés), proposer le Site Vitrine.',
        'offer_vitrine' => '5 pages incluses avec formulaire et Google Maps. Offre la plus adaptée aux artisans, commerçants et TPE. Sans administration : si le client veut modifier ses contenus lui-même, comparer avec le Site Pro.',
        'offer_pro' => '10 pages, administration et 2 modules CRUD inclus (ex. actualités + réalisations). À proposer dès que le client veut publier lui-même : moins cher que Vitrine + Espace d’administration + modules.',
        'offer_custom' => 'Base des projets avec logique métier (boutique, réservation avancée, espace client, application). Le prix couvre conception, structure et administration ; ajouter les options correspondantes au besoin.',
        'maintenance_essential' => 'Hébergement, HTTPS, sauvegardes et surveillance. Minimum pour tout site hébergé par Codenyr ; ajouter aussi la ligne « Hébergement par Codenyr ». Ne comprend ni modification ni assistance.',
        'maintenance_pro' => 'Essentiel + mises à jour techniques et assistance. Recommandé pour les sites avec administration (Site Pro).',
        'maintenance_business' => 'Pro + suivi d’une application Laravel. Pour le Sur mesure (boutique, réservation, espace client). Prix « à partir de » à ajuster selon la taille de l’application.',
    ];

    public function initialize(): void
    {
        foreach (app(PricingCatalog::class)->all() as $key => $plan) {
            Service::firstOrCreate(['price_key' => $key], ['category' => $plan['group'] === 'offer' ? 'Création' : 'Maintenance', 'name' => $plan['name'], 'description' => $plan['description'] ?? $plan['intro'], 'ai_notes' => self::PLAN_NOTES[$key] ?? null, 'amount_cents' => $plan['amount_cents'], 'frequency' => $plan['group'] === 'offer' ? 'once' : 'monthly', 'pricing_mode' => $plan['group'] === 'offer' || $key === 'maintenance_business' ? 'starting' : 'monthly']);
        }
        foreach ($this->options() as $position => $option) {
            Service::firstOrCreate(['catalog_key' => $option['catalog_key']], [...$option, 'position' => $position]);
        }
        app(ContractCatalogDefaults::class)->initialize();
    }

    /** Brings an existing catalogue in line with the current definitions and archives retired options. */
    public function revise(): void
    {
        foreach (self::PLAN_NOTES as $key => $notes) {
            Service::where('price_key', $key)->update(['ai_notes' => $notes]);
        }
        foreach (app(PricingCatalog::class)->all() as $key => $plan) {
            Service::where('price_key', $key)->update(['amount_cents' => $plan['amount_cents']]);
        }
        foreach ($this->options() as $position => $option) {
            $service = Service::where('catalog_key', $option['catalog_key'])->first();
            $service?->update([...$option, 'position' => $position, 'active' => true, 'archived_at' => null]);
        }
        Service::whereIn('catalog_key', self::RETIRED)->whereNull('archived_at')->update(['active' => false, 'archived_at' => now()]);
    }

    /** @return list<array<string, mixed>> */
    private function options(): array
    {
        $option = fn (string $key, string $name, string $category, int $amount, string $unit, string $mode, array $offers, string $description, string $notes, string $frequency = 'once'): array => ['catalog_key' => $key, 'name' => $name, 'category' => $category, 'amount_cents' => $amount, 'unit' => $unit, 'pricing_mode' => $mode, 'compatible_offers' => $offers, 'description' => $description, 'ai_notes' => $notes, 'frequency' => $frequency];

        return [
            $option('extra_page', 'Page supplémentaire', 'Contenu', 9000, 'page', 'unit', ['offer_vitrine', 'offer_pro'],
                'Page supplémentaire conçue dans le même style que le reste du site, au-delà des pages incluses dans l’offre.',
                'Vitrine : 5 pages incluses, Pro : 10. Quantité = uniquement les pages en plus (ex. 7 pages en Vitrine → 2).'),
            $option('extra_section', 'Section supplémentaire', 'Contenu', 5000, 'section', 'unit', ['offer_landing'],
                'Bloc de contenu supplémentaire sur la page (témoignages, FAQ, tarifs…), au-delà des 6 sections incluses.',
                'Landing Page uniquement. Quantité = sections au-delà de 6. À partir de 3 sections en plus, ou si le client veut plusieurs pages, proposer le Site Vitrine.'),
            $option('advanced_form', 'Formulaire sur mesure', 'Fonctionnalités', 10000, 'forfait', 'fixed', [],
                'Formulaire adapté à votre activité : demande de devis détaillée, champs conditionnels, envoi de fichiers ou candidature.',
                'Ne pas ajouter pour un simple formulaire de contact : il est inclus dans toutes les offres. Quantité = nombre de formulaires distincts.'),
            $option('admin_area', 'Espace d’administration', 'Administration', 25000, 'forfait', 'fixed', ['offer_landing', 'offer_vitrine'],
                'Interface privée pour modifier vous-même un type de contenu du site (textes, photos ou actualités) sans intervention technique.',
                'Landing et Vitrine uniquement : déjà inclus dans Site Pro et Sur mesure. Couvre un seul type de contenu ; au-delà, proposer le Site Pro.'),
            $option('extra_crud', 'Module CRUD supplémentaire', 'Administration', 18000, 'module', 'unit', ['offer_pro', 'offer_custom'],
                'Rubrique supplémentaire gérable depuis l’administration (produits, réalisations, équipe, galerie, blog…) : ajout, modification et suppression.',
                'Site Pro : 2 modules inclus, quantité = modules à partir du 3e. Une galerie photo ou un blog = un module. Nommer chaque module dans la description de la ligne.'),
            $option('user_accounts', 'Espace client', 'Administration', 25000, 'forfait', 'fixed', [],
                'Comptes pour vos clients ou membres : inscription, connexion, mot de passe oublié et profil.',
                'Socle uniquement : ce que l’utilisateur fait une fois connecté (documents, historique, réservations) se chiffre en plus. Implique des données personnelles.'),
            $option('booking', 'Réservation en ligne', 'Fonctionnalités', 30000, 'forfait', 'starting', [],
                'Prise de rendez-vous ou réservation en ligne : créneaux, disponibilités, confirmation par e-mail et gestion depuis l’administration.',
                'Prix pour un agenda simple. Augmenter si plusieurs agendas, ressources ou règles complexes. Paiement à la réservation : ajouter « Paiement en ligne ». Si le client utilise déjà Planity ou Calendly, préférer « Intégration ou automatisation ».'),
            $option('cart', 'Boutique en ligne', 'Fonctionnalités', 35000, 'forfait', 'starting', [],
                'Catalogue de produits, panier et suivi des commandes depuis l’administration (vente en ligne ou click & collect).',
                'Base conseillée : Site Pro ou Sur mesure. Prix jusqu’à environ 30 produits sans variantes complexes ; ajuster au-delà. Le paiement par carte s’ajoute avec « Paiement en ligne ».'),
            $option('stripe', 'Paiement en ligne', 'Fonctionnalités', 25000, 'forfait', 'fixed', [],
                'Paiement sécurisé par carte bancaire via Stripe, avec confirmation automatique. Les commissions Stripe restent à votre charge.',
                'Toujours avec « Boutique en ligne », « Réservation en ligne » ou un besoin de paiement précis. Codenyr n’encaisse pas les paiements des clients finaux.'),
            $option('language', 'Langue supplémentaire', 'Fonctionnalités', 15000, 'langue', 'unit', [],
                'Version du site dans une autre langue, avec sélecteur de langue. Les traductions sont fournies par vos soins.',
                'Quantité = langues en plus du français. Traduction non comprise ; si le client en veut une, l’ajouter en « Frais tiers ».'),
            $option('api', 'Intégration ou automatisation', 'Fonctionnalités', 15000, 'forfait', 'starting', [],
                'Connexion à un outil externe ou automatisation : newsletter (Brevo, Mailchimp), agenda, avis Google, CRM, e-mails automatiques…',
                'Couvre aussi newsletter et automatisations. Une ligne par outil (quantité). 150 € pour un branchement simple, à ajuster selon la complexité. Abonnements des outils en « Frais tiers ».'),
            $option('seo', 'Référencement local avancé', 'Accompagnement', 25000, 'forfait', 'starting', [],
                'Optimisation poussée pour Google : mots-clés locaux, fiche Google Business Profile, données structurées et optimisation des pages. Aucun classement garanti.',
                'Le SEO de base est déjà inclus dans toutes les offres. À proposer aux artisans et commerces qui veulent être trouvés autour de Chalon-sur-Saône.'),
            $option('migration', 'Reprise d’un ancien site', 'Accompagnement', 20000, 'forfait', 'starting', [],
                'Récupération des contenus de votre site actuel (textes, images, articles) et redirections pour conserver votre référencement.',
                'À proposer quand le client a déjà un site. Prix jusqu’à environ 10 pages ou 20 articles ; ajuster au-delà.'),
            $option('intervention', 'Travail supplémentaire', 'Intervention', 6000, 'heure', 'hourly', [],
                'Modification, ajout ou intervention en dehors du devis initial ou du contrat de maintenance, facturé au temps passé.',
                'Quantité = heures estimées. Pour une nouvelle fonctionnalité qui existe au catalogue, utiliser plutôt l’option correspondante.'),
            $option('third_party', 'Frais tiers', 'Frais tiers', 0, 'unité', 'quote', [],
                'Frais d’un service externe (licence, abonnement, photos, traduction…), avec le prestataire, le montant et la fréquence.',
                'Saisir le montant réel. Préciser qui paie : le client directement (hors total Codenyr) ou refacturé par Codenyr.'),
            $option('domain', 'Nom de domaine', 'Frais tiers', 1500, 'an', 'yearly', [],
                'Réservation et renouvellement annuel du nom de domaine (.fr ou .com) à votre nom, gérés par Codenyr.',
                'Prix indicatif refacturé au coût, à adapter à l’extension. Ne pas ajouter si le client possède et gère déjà son domaine.', 'yearly'),
            $option('contact_form', 'Formulaire de contact', 'Inclus', 0, 'forfait', 'fixed', [],
                'Formulaire de contact avec envoi des demandes par e-mail et protection anti-spam. Inclus dans l’offre.',
                'Ligne à 0 € : l’ajouter dès que le site a un formulaire, pour que le contrat mentionne le traitement des données personnelles.'),
            $option('hosting', 'Hébergement par Codenyr', 'Inclus', 0, 'forfait', 'fixed', [],
                'Hébergement du site sur un serveur géré par Codenyr (OVH, France), avec certificat HTTPS. Inclus dans les formules de maintenance.',
                'Ligne à 0 € à ajouter avec une maintenance, pour que le contrat indique que Codenyr héberge le site. Sans maintenance, le client gère son hébergement.', 'yearly'),
        ];
    }
}
