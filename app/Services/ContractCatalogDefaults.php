<?php

namespace App\Services;

use App\Models\Service;

/** Initial data only: once saved, the database is authoritative. */
class ContractCatalogDefaults
{
    public function initialize(): void
    {
        $optional = ['contact_form' => 'Formulaire de contact', 'booking' => 'Réservation en ligne', 'stripe' => 'Paiement Stripe', 'gallery' => 'Galerie', 'blog' => 'Blog', 'language' => 'Multilingue', 'user_accounts' => 'Espace client', 'admin_area' => 'Espace administrateur', 'seo' => 'SEO avancé', 'hosting' => 'Hébergement Codenyr', 'domain' => 'Gestion du domaine'];
        foreach (['contact_form', 'gallery', 'blog', 'maps', 'hosting', 'domain'] as $key) {
            Service::firstOrCreate(['catalog_key' => $key], ['name' => $optional[$key] ?? 'Google Maps', 'category' => 'Options', 'amount_cents' => 0, 'pricing_mode' => 'quote', 'frequency' => in_array($key, ['hosting', 'domain']) ? 'yearly' : 'once', 'description' => 'Périmètre et tarif à définir dans le devis.']);
        }
        foreach (Service::whereNull('contract_rules')->get() as $service) {
            $key = $service->price_key ?: $service->catalog_key;
            $rules = ['deliverables' => [], 'exclusions' => [], 'effects' => [], 'clauses' => []];
            if (str_starts_with($key ?? '', 'offer_')) {
                $rules['object'] = 'Conception et réalisation : '.$service->name.'. '.$service->description;
                $rules['exclusions'] = $optional;
                if ($key !== 'offer_custom') {
                    $rules['deliverables'] = ['responsive' => 'Adaptation ordinateur, tablette et mobile', 'seo_base' => 'SEO de base et favicon', 'production' => 'Mise en production'];
                }
                if ($key === 'offer_landing') {
                    $rules['deliverables']['single_page'] = 'Une page unique de présentation avec appels à l’action';
                }
            }
            if (in_array($key, ['contact_form', 'advanced_form', 'booking', 'newsletter', 'user_accounts'], true)) {
                $rules['effects']['personal_data'] = true;
            }
            if ($key === 'booking') {
                $rules['clauses']['Réservation'] = 'Le service de réservation est limité aux fonctions et aux règles de disponibilité décrites au devis.';
            }
            if ($key === 'stripe') {
                $rules['clauses']['Paiement tiers'] = 'Le paiement est assuré par Stripe. Codenyr réalise l’intégration technique et n’est pas l’établissement de paiement. Les frais du prestataire sont distincts sauf mention expresse au devis.';
            }
            if (in_array($key, ['maps', 'newsletter', 'api'], true)) {
                $rules['clauses']['Service tiers'] = 'Le service tiers utilisé reste soumis aux conditions du fournisseur identifié dans le devis. Ses frais ne sont inclus que si le devis le prévoit.';
            }
            if (str_starts_with($key ?? '', 'maintenance_')) {
                $rules['effects']['maintenance_mode'] = $key === 'maintenance_essential' ? 'essentiel' : substr($key, 12);
                $rules['clauses']['Maintenance'] = $service->name.' : '.$service->description;
            }
            if (in_array($key, ['hosting', 'domain'], true)) {
                foreach (['mode', 'owner', 'manager', 'payer', 'renewal'] as $field) {
                    $rules['effects'][$key.'_'.$field] = 'codenyr';
                }
            }
            $service->update(['contract_rules' => $rules]);
        }
    }
}
