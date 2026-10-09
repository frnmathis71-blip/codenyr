<?php

namespace App\Services;

use App\Models\Service;

class CommercialCatalog
{
    public function initialize(): void
    {
        foreach (app(PricingCatalog::class)->all() as $key => $plan) {
            Service::firstOrCreate(['price_key' => $key], ['category' => $plan['group'] === 'offer' ? 'Création' : 'Maintenance', 'name' => $plan['name'], 'description' => $plan['description'] ?? $plan['intro'], 'amount_cents' => $plan['amount_cents'], 'frequency' => $plan['group'] === 'offer' ? 'once' : 'monthly', 'pricing_mode' => $plan['group'] === 'offer' || $key === 'maintenance_business' ? 'starting' : 'monthly']);
        }
        foreach ($this->options() as $position => $option) {
            [$key, $name, $category, $amount, $unit, $mode, $offers, $description] = $option;
            Service::firstOrCreate(['catalog_key' => $key], ['name' => $name, 'category' => $category, 'amount_cents' => $amount, 'unit' => $unit, 'pricing_mode' => $mode, 'compatible_offers' => $offers, 'description' => $description, 'frequency' => 'once', 'position' => $position]);
        }
        app(ContractCatalogDefaults::class)->initialize();
    }

    /** @return array<int, array{string, string, string, int, string, string, array<int, string>, string}> */
    private function options(): array
    {
        return [
            ['extra_page', 'Page supplémentaire', 'Contenu / Structure', 9000, 'page', 'unit', ['offer_vitrine', 'offer_pro'], 'Au-delà des pages incluses. Saisir uniquement le nombre de pages supplémentaires.'],
            ['extra_section', 'Section supplémentaire Landing Page', 'Contenu / Structure', 5000, 'section', 'unit', ['offer_landing'], 'Au-delà des 6 sections incluses. Saisir uniquement les sections supplémentaires.'],
            ['advanced_form', 'Formulaire avancé', 'Formulaires', 10000, 'forfait', 'fixed', [], 'Champs nombreux, conditions, upload, traitement particulier ou formulaire métier. Le formulaire de contact classique prévu dans l’offre reste inclus.'],
            ['extra_crud', 'Module CRUD supplémentaire', 'Administration', 18000, 'module', 'unit', ['offer_pro', 'offer_custom'], 'Site Pro : les 2 premiers CRUD sont inclus. Saisir uniquement les modules supplémentaires, à partir du troisième, et décrire chaque module : produits, actualités, réservations, employés, catégories…'],
            ['admin_area', 'Espace administrateur', 'Administration', 25000, 'forfait', 'fixed', ['offer_landing', 'offer_vitrine'], 'Pour les offres sans administration incluse. Déjà compris dans Site Pro.'],
            ['user_accounts', 'Comptes clients / utilisateurs', 'Administration', 25000, 'forfait', 'fixed', [], 'Inscription, connexion, déconnexion et profil utilisateur. Les fonctions métier supplémentaires restent facturables séparément.'],
            ['booking', 'Réservation en ligne', 'Fonctionnalités', 30000, 'forfait', 'starting', [], 'Prix à adapter selon les créneaux, disponibilités, administration et confirmations.'],
            ['cart', 'Commande / panier', 'Fonctionnalités', 35000, 'forfait', 'starting', [], 'Prix à adapter au catalogue, panier, variantes et gestion des commandes.'],
            ['stripe', 'Paiement Stripe', 'Fonctionnalités', 25000, 'forfait', 'fixed', [], 'Intégration technique du paiement. Les frais Stripe et du prestataire ne sont pas inclus.'],
            ['newsletter', 'Newsletter', 'Communication', 12000, 'forfait', 'fixed', [], 'Formulaire d’inscription, connexion au service choisi et intégration. Les abonnements du prestataire sont séparés.'],
            ['language', 'Langue supplémentaire', 'Multilingue', 15000, 'langue', 'unit', [], 'Traductions fournies par le client. La traduction professionnelle du contenu n’est pas incluse.'],
            ['api', 'Intégration API / service externe', 'API et automatisations', 15000, 'forfait', 'starting', [], 'Prix à adapter à la complexité de l’intégration.'],
            ['automation', 'Automatisation spécifique', 'API et automatisations', 15000, 'forfait', 'starting', [], 'E-mails automatiques, traitements programmés, synchronisation ou automatisation métier. Prix selon complexité.'],
            ['migration', 'Migration d’un ancien site', 'Migration', 20000, 'forfait', 'starting', [], 'Selon les pages, données, images, technologie et quantité de contenu.'],
            ['seo', 'SEO avancé', 'SEO', 25000, 'forfait', 'starting', [], 'Complément du SEO de base. Aucune garantie de classement ou de première position sur Google.'],
            ['changes', 'Modification supplémentaire', 'Intervention', 6000, 'heure', 'hourly', [], 'Au-delà des séries de modifications incluses dans l’offre.'],
            ['development', 'Développement supplémentaire', 'Intervention', 6000, 'heure', 'hourly', [], 'Nouvelle fonctionnalité après validation du périmètre initial. Maintenance ≠ modification ≠ nouvelle fonctionnalité.'],
            ['intervention', 'Intervention ponctuelle', 'Intervention', 6000, 'heure', 'hourly', [], 'Intervention non comprise dans le contrat de maintenance.'],
            ['third_party', 'Frais tiers', 'Frais tiers', 0, 'unité', 'quote', [], 'Nom de domaine, licence, plugin, API, e-mail, SaaS, abonnement externe ou frais de plateforme. Préciser le prestataire, le montant, la fréquence et qui le règle.'],
        ];
    }
}
