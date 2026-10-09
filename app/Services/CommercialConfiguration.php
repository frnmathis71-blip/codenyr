<?php

namespace App\Services;

class CommercialConfiguration
{
    /** @return array<string, array<string, string>> */
    public static function sections(): array
    {
        return [
            'Entreprise — adresse et identifiants' => ['address_extra' => 'Complément d’adresse', 'postal_code' => 'Code postal', 'city' => 'Ville', 'country' => 'Pays', 'siret' => 'SIRET', 'website' => 'Site internet', 'signatory_firstname' => 'Prénom du signataire', 'signatory_lastname' => 'Nom du signataire'],
            'Commercial et documents' => ['currency' => 'Devise (code ISO, ex. EUR)', 'payment_method' => 'Moyen de paiement', 'remediation_days' => 'Délai de remédiation (jours)', 'confidentiality_duration' => 'Durée de confidentialité', 'late_rate' => 'Taux des pénalités B2B', 'recovery_fee' => 'Indemnité de recouvrement', 'terms_prefix' => 'Préfixe CGV', 'contract_prefix' => 'Préfixe contrats', 'maintenance_prefix' => 'Préfixe maintenance', 'document_prefix' => 'Préfixe autres documents'],
            'Valeurs techniques par défaut' => ['contract_backups' => 'Sauvegardes', 'contract_source_code' => 'Livraison du code source', 'contract_graphic_files' => 'Livraison des fichiers graphiques', 'contract_modification_rights' => 'Droit de modification', 'contract_security' => 'Sécurité et accès', 'contract_technologies' => 'Technologies habituelles'],
            'Clauses et variantes réutilisables' => ['clause_scope' => 'Objet et champ d’application', 'clause_prices' => 'Prix', 'clause_delays' => 'Délais et exécution', 'clause_client_duties' => 'Obligations du client', 'clause_changes' => 'Anomalies, corrections et nouvelles fonctionnalités', 'clause_force_majeure' => 'Force majeure', 'clause_domain_client' => 'Domaine géré par le client', 'clause_domain_codenyr' => 'Domaine géré par Codenyr', 'clause_hosting_client' => 'Hébergement géré par le client', 'clause_hosting_codenyr' => 'Hébergement géré par Codenyr', 'clause_maintenance_none' => 'Absence de maintenance récurrente'],
        ];
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        $defaults = [];
        foreach (self::sections() as $fields) {
            foreach ($fields as $key => $label) {
                $defaults[$key] = '';
            }
        }

        return array_replace($defaults, ['currency' => 'EUR', 'terms_prefix' => 'CGV', 'contract_prefix' => 'CTR', 'maintenance_prefix' => 'MNT', 'document_prefix' => 'DOC', 'require_contract_quote' => true, 'require_contract_terms' => true]);
    }

    /** @return array<string, string> */
    public static function clientFields(): array
    {
        return ['firstname' => 'Prénom', 'lastname' => 'Nom de famille', 'siren' => 'SIREN', 'siret' => 'SIRET', 'address_extra' => 'Complément d’adresse', 'postal_code' => 'Code postal', 'city' => 'Ville', 'billing_name' => 'Nom de facturation si différent', 'billing_email' => 'E-mail de facturation si différent', 'billing_address' => 'Adresse de facturation si différente'];
    }
}
