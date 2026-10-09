<?php

namespace App\Services;

class LegalDocumentRules
{
    public static function errorTarget(string $key): string
    {
        $key = preg_replace('/^contract\./', '', $key) ?? $key;

        return match ($key) {
            'form.client_id', 'parties', 'relations' => 'document-client',
            'form.client_project_id', 'project' => 'document-project',
            'form.quote_id', 'quote', 'quote_relation', 'amounts', 'offer_conflict' => 'document-quote',
            'form.invoice_id', 'invoice_relation' => 'document-invoice',
            'form.document_date' => 'document-date',
            'form.template_id' => 'document-template',
            'form.original_id' => 'document-type',
            'audience' => 'legal-client_audience',
            'seller_vat' => 'legal-seller_vat_number',
            'starts_on', 'due_on', 'domain', 'host', 'technologies' => 'legal-project_'.$key,
            'estimated_delay' => 'legal-quote_estimated_delay',
            'payment' => 'legal-quote_conditions',
            'terms_effective' => 'legal-terms_effective_on',
            'review_days_number' => 'legal-review_days',
            'revision_rounds_number' => 'legal-revision_rounds',
            'maintenance_conflict', 'maintenance_source' => 'legal-maintenance_mode',
            'exclusion_conflict' => 'legal-exclusions',
            'content' => 'legal-terms_content',
            'mode', 'upload' => 'document-'.$key,
            default => str_starts_with($key, 'form.') ? 'document-'.substr($key, 5) : 'legal-'.$key,
        };
    }

    /** @return array<string, array<string, string>> */
    public static function formSources(string $type): array
    {
        $groups = [
            'seller' => ['name' => 'Nom commercial', 'legal_name' => 'Raison sociale', 'legal_status' => 'Statut juridique', 'address' => 'Adresse du prestataire', 'siren' => 'SIREN', 'registration' => 'Immatriculation', 'email' => 'E-mail du prestataire', 'phone' => 'Téléphone', 'vat_number' => 'Numéro de TVA', 'tax_mention' => 'Mention de TVA', 'signatory' => 'Signataire du prestataire', 'signatory_role' => 'Qualité du signataire', 'extra_hourly_rate' => 'Tarif horaire supplémentaire (€)', 'external_fees_mention' => 'Frais externes'],
            'client' => ['name' => 'Nom du client', 'company' => 'Société', 'contact' => 'Contact', 'address' => 'Adresse du client', 'country' => 'Pays', 'email' => 'E-mail du client', 'phone' => 'Téléphone', 'registration' => 'Immatriculation du client', 'vat_number' => 'Numéro de TVA', 'audience' => 'Qualité du client', 'signatory' => 'Signataire du client', 'signatory_role' => 'Qualité du signataire'],
        ];
        if ($type === 'contract') {
            $groups['project'] = ['starts_on' => 'Date de début', 'due_on' => 'Date de livraison', 'domain' => 'Nom de domaine', 'host' => 'Hébergeur', 'technologies' => 'Technologies'];
            $groups['quote'] = ['estimated_delay' => 'Durée estimée', 'conditions' => 'Modalités et échéances de paiement'];
        }
        $groups['terms'] = ['name' => 'Nom de la version des CGV', 'revision' => 'Numéro de version', 'effective_on' => 'Date d’effet des CGV', 'content' => 'Texte des CGV applicable'];

        return $groups;
    }

    public const COMPANY = ['legal_status' => 'Statut juridique', 'siren' => 'SIREN', 'vat_number' => 'TVA intracommunautaire', 'signatory' => 'Signataire Codenyr', 'signatory_role' => 'Qualité du signataire'];

    /** @param array<string, mixed> $fields */
    public static function active(string $key, array $fields): bool
    {
        return ! (($key === 'ip_license' && ($fields['ip_mode'] ?? '') === 'cession')
            || (str_starts_with($key, 'ip_') && ! in_array($key, ['ip_mode', 'ip_license']) && ($fields['ip_mode'] ?? '') !== 'cession')
            || (str_starts_with($key, 'domain_') && $key !== 'domain_mode' && ($fields['domain_mode'] ?? '') === 'non_applicable')
            || (str_starts_with($key, 'hosting_') && $key !== 'hosting_mode' && ($fields['hosting_mode'] ?? '') === 'non_applicable')
            || (in_array($key, ['privacy_annex_id', 'annex_state']) && ($fields['privacy_processing'] ?? '') !== 'oui'));
    }

    public const CLAUSES = [
        'order' => 'Commande et démarrage', 'payment' => 'Modalités de paiement',
        'late_professional' => 'Retards de paiement B2B', 'late_consumer' => 'Retards de paiement B2C',
        'disputes_professional' => 'Différends B2B', 'disputes_consumer' => 'Différends, médiation et rétractation B2C',
        'delivery' => 'Validation et livraison', 'rights' => 'Propriété intellectuelle générale',
        'hosting' => 'Domaine et hébergement', 'maintenance' => 'Maintenance générale',
        'liability' => 'Responsabilité et tiers', 'security' => 'Sauvegarde et sécurité',
        'privacy' => 'Données personnelles', 'termination' => 'Résiliation, notification, remédiation et conséquences financières',
        'confidentiality' => 'Confidentialité : durée, exclusions, restitution ou suppression des accès',
        'priority' => 'Ordre de priorité contractuel', 'acceptance' => 'Mention et modalités d’acceptation',
    ];

    /** @return array<string, array<string, string>> */
    public static function sections(): array
    {
        return [
            'Projet et périmètre' => ['object' => 'Objet contractuel', 'deliverables' => 'Livrables', 'exclusions' => 'Exclusions (une par ligne)', 'show_technologies' => 'Afficher les technologies : oui / non'],
            'Commercial' => ['balance_event' => 'Événement déclenchant le solde', 'third_party_fees' => 'Frais tiers et prise en charge'],
            'Planning' => ['start_event' => 'Événement déclenchant le démarrage'],
            'Livraison et validation' => ['review_days' => 'Délai de vérification (jours)', 'revision_rounds' => 'Séries de modifications incluses', 'feedback' => 'Transmission des retours', 'anomalies' => 'Traitement des anomalies', 'validation' => 'Validation finale', 'production' => 'Conditions de mise en production'],
            'Propriété intellectuelle' => ['ip_mode' => 'Régime : cession / licence / autre', 'ip_creations' => 'Créations et droits concernés', 'ip_scope' => 'Étendue des droits', 'ip_uses' => 'Usages et destination', 'ip_media' => 'Supports', 'ip_territory' => 'Territoire', 'ip_duration' => 'Durée', 'ip_payment' => 'Rémunération', 'ip_effect' => 'Prise d’effet', 'ip_license' => 'Conditions de licence ou autre disposition'],
            'Domaine et hébergement' => ['domain_mode' => 'Domaine : codenyr / client / non_applicable / indetermine', 'domain_owner' => 'Titulaire du domaine', 'domain_account' => 'Référence registrar (aucun secret)', 'domain_manager' => 'Gestionnaire du domaine', 'domain_payer' => 'Payeur du domaine', 'domain_renewal' => 'Responsable du renouvellement du domaine', 'domain_cost' => 'Coût et périodicité du domaine', 'hosting_mode' => 'Hébergement : codenyr / client / non_applicable / indetermine', 'hosting_owner' => 'Titulaire du compte hébergeur', 'hosting_manager' => 'Gestionnaire hébergement', 'hosting_payer' => 'Payeur hébergement', 'hosting_renewal' => 'Renouvellement hébergement', 'hosting_cost' => 'Coût et périodicité hébergement'],
            'Maintenance' => ['maintenance_mode' => 'Maintenance : aucune / essentiel / pro / business / personnalisee / indetermine'],
            'Clauses et annexes' => ['privacy_processing' => 'Traitement de données pour le client : oui / non / indetermine', 'privacy_annex_id' => 'Identifiant du document annexe RGPD associé', 'annex_state' => 'Annexe : nécessaire / en préparation / associée', 'additional' => 'Précisions contractuelles complémentaires'],
            'Signatures' => ['signature_place' => 'Lieu de signature (facultatif)'],
        ];
    }

    /** @return array<string, string> */
    public static function defaults(): array
    {
        $values = array_fill_keys(array_keys(self::COMPANY), '');
        foreach (self::CLAUSES as $key => $label) {
            $values['clause_'.$key] = '';
        }
        $values['extra_hourly_rate'] = '60';
        foreach (self::sections() as $fields) {
            foreach ($fields as $key => $label) {
                $values['contract_'.$key] = '';
            }
        }

        return $values;
    }

    /** @return list<string> */
    public static function choices(string $key): array
    {
        return match ($key) {
            'ip_mode' => ['cession', 'licence', 'autre'],
            'domain_mode', 'hosting_mode' => ['codenyr', 'client', 'non_applicable', 'indetermine'],
            'maintenance_mode' => ['aucune', 'essentiel', 'pro', 'business', 'personnalisee', 'indetermine'],
            'privacy_processing' => ['oui', 'non', 'indetermine'],
            'show_technologies' => ['oui', 'non'],
            default => [],
        };
    }
}
