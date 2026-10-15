<?php

namespace App\Services;

/**
 * Sensible defaults for a freelance web developer (micro-entreprise), so documents
 * only ask for what is specific to a client. Used as a fallback layer by
 * CommercialSetting::current(): any value saved in the commercial settings wins.
 *
 * The clauses are a starting point to be reviewed by a legal professional.
 */
class StandardBusinessProfile
{
    /** @return array<string, mixed> */
    public static function values(): array
    {
        return [...self::seller(), ...self::mentions(), ...self::clauses(), ...self::contract()];
    }

    /** @return array<string, string> */
    public static function seller(): array
    {
        $legal = config('codenyr.legal');
        $siret = preg_replace('/\D/', '', (string) ($legal['siret'] ?? ''));
        $address = (string) ($legal['address'] ?? '');
        preg_match('/(\d{5})\s+([^,]+)/u', $address, $city);

        return [
            'legal_name' => trim(($legal['owner'] ?? '').' (EI)'),
            'legal_status' => (string) ($legal['status'] ?? ''),
            'address' => $address,
            'postal_code' => $city[1] ?? '',
            'city' => trim($city[2] ?? ''),
            'country' => 'France',
            'phone' => (string) ($legal['phone'] ?? ''),
            'siret' => $siret,
            'siren' => $siret !== '' ? substr($siret, 0, 9) : '',
            'registration' => $siret !== '' ? 'SIRET '.$siret : 'Immatriculation en cours',
            'tax_mention' => (string) ($legal['vat'] ?? ''),
            'signatory' => (string) ($legal['owner'] ?? ''),
            'signatory_firstname' => explode(' ', (string) ($legal['owner'] ?? ''))[0] ?? '',
            'signatory_lastname' => trim(implode(' ', array_slice(explode(' ', (string) ($legal['owner'] ?? '')), 1))),
            'signatory_role' => 'Entrepreneur individuel',
            'website' => rtrim((string) config('app.url'), '/'),
        ];
    }

    /** Mentions printed on quotes and invoices (Code de commerce, art. L441-9 and L441-10). */
    public static function mentions(): array
    {
        return [
            'payment_method' => 'Virement bancaire',
            'quote_mention' => "Devis valable 30 jours à compter de sa date.\nAcompte à la commande selon le montant indiqué, solde à la livraison, par virement bancaire.\nLe devis signé avec la mention « Bon pour accord » vaut commande.",
            'invoice_mention' => "Paiement par virement bancaire à réception, au plus tard à la date d’échéance indiquée.\nPas d’escompte pour paiement anticipé.\nClient professionnel : en cas de retard, pénalités au taux de trois fois le taux d’intérêt légal et indemnité forfaitaire de 40 € pour frais de recouvrement (art. L441-10 du Code de commerce).",
            'late_rate' => 'Trois fois le taux d’intérêt légal',
            'recovery_fee' => '40 €',
            'remediation_days' => '15',
            'confidentiality_duration' => 'Pendant le contrat et deux ans après sa fin',
        ];
    }

    /** @return array<string, string> */
    public static function clauses(): array
    {
        return [
            'clause_order' => 'La commande est formée par l’acceptation du devis (signature avec la mention « Bon pour accord » ou accord écrit par e-mail) et, le cas échéant, le paiement de l’acompte. Les travaux démarrent après réception de l’acompte et des éléments nécessaires.',
            'clause_payment' => 'Les factures sont payables par virement bancaire à la date d’échéance indiquée. Sauf mention contraire du devis, un acompte est dû à la commande et le solde à la livraison. Aucun escompte n’est accordé pour paiement anticipé.',
            'clause_late_professional' => 'Tout retard de paiement entraîne de plein droit des pénalités au taux de trois fois le taux d’intérêt légal, ainsi qu’une indemnité forfaitaire de 40 € pour frais de recouvrement (art. L441-10 du Code de commerce). Le prestataire peut suspendre les travaux jusqu’au paiement.',
            'clause_late_consumer' => 'En cas de retard de paiement, des intérêts au taux légal peuvent être appliqués après mise en demeure restée sans effet. Le prestataire peut suspendre les travaux jusqu’au paiement.',
            'clause_disputes_professional' => 'Le contrat est soumis au droit français. Les parties recherchent d’abord une solution amiable ; à défaut, le litige est porté devant les tribunaux compétents du ressort du siège du prestataire.',
            'clause_disputes_consumer' => "Le contrat est soumis au droit français. Le client consommateur dispose d’un délai de rétractation de quatorze jours à compter de l’acceptation du devis (art. L221-18 du Code de la consommation). S’il demande expressément le démarrage des travaux avant la fin de ce délai, il reste redevable des prestations déjà réalisées en cas de rétractation, et perd ce droit lorsque la prestation est entièrement exécutée.\nEn cas de litige, le client peut recourir gratuitement au médiateur de la consommation indiqué dans les mentions légales, après une réclamation écrite préalable auprès du prestataire.",
            'clause_delivery' => 'Le site est livré sur un environnement de test pour vérification. Le client dispose du délai de vérification prévu au contrat pour transmettre ses retours en une liste consolidée. Sans retour dans ce délai, ou à la mise en ligne, la livraison est réputée acceptée.',
            'clause_rights' => 'Les droits d’exploitation sur les créations spécifiques réalisées pour le client lui sont cédés dans les conditions prévues au contrat, après paiement intégral du prix. Les outils, composants libres et éléments préexistants du prestataire restent sa propriété ou celle de leurs auteurs. Le prestataire peut citer la réalisation dans ses références, sauf refus écrit du client.',
            'clause_hosting' => 'Sauf mention contraire, le nom de domaine et l’hébergement sont souscrits au nom du client, qui en reste titulaire et en supporte le coût. Le prestataire peut assister le client dans ces démarches.',
            'clause_maintenance' => 'La maintenance n’est pas incluse dans la création du site. Elle peut faire l’objet d’un contrat distinct précisant les prestations, la durée et le prix. Les évolutions et nouvelles fonctionnalités font l’objet d’un devis séparé.',
            'clause_liability' => 'Le prestataire est tenu d’une obligation de moyens. Sa responsabilité est limitée aux dommages directs et plafonnée au montant payé pour la prestation concernée. Il n’est pas responsable des services tiers (hébergeur, registraire, outils externes) ni des contenus fournis par le client.',
            'clause_security' => 'Le prestataire applique les bonnes pratiques de sécurité lors du développement. Sauf contrat de maintenance, les sauvegardes et mises à jour après livraison relèvent du client ou de son hébergeur. Les accès transmis sont conservés de manière confidentielle et supprimés en fin de mission.',
            'clause_privacy' => 'Chaque partie respecte le RGPD pour les traitements qui la concernent. Le client reste responsable des données collectées par son site. Lorsque le prestataire traite des données pour le compte du client, un accord de sous-traitance précise les engagements des parties.',
            'clause_termination' => 'En cas de manquement grave non corrigé dans un délai de quinze jours après mise en demeure par écrit, le contrat peut être résilié par l’autre partie. Les prestations réalisées jusqu’à la résiliation restent dues ; l’acompte versé reste acquis au prestataire à hauteur des travaux engagés.',
            'clause_confidentiality' => 'Les parties gardent confidentielles les informations non publiques échangées, pendant le contrat et deux ans après sa fin, sauf informations déjà publiques ou communication imposée par la loi. En fin de mission, les accès sont restitués ou supprimés.',
            'clause_priority' => 'En cas de contradiction, les documents s’appliquent dans l’ordre suivant : contrat de prestation, devis accepté, conditions générales de vente.',
            'clause_acceptance' => 'L’acceptation du devis emporte acceptation des présentes conditions générales de vente, dont le client reconnaît avoir pris connaissance avant la commande.',
        ];
    }

    /** Contract defaults (keys prefixed with contract_ in the commercial settings). */
    public static function contract(): array
    {
        $values = [
            'deliverables' => 'Le site internet décrit dans le devis accepté, mis en ligne sur l’hébergement prévu, avec les accès d’administration lorsque l’offre en comporte.',
            'exclusions' => "Rédaction des contenus et fourniture des photos, sauf mention au devis\nNom de domaine et hébergement\nMaintenance après livraison\nNouvelles fonctionnalités non prévues au devis",
            'show_technologies' => 'non',
            'balance_event' => 'À la livraison du site, avant sa mise en ligne',
            'third_party_fees' => 'Les frais de services tiers (nom de domaine, hébergement, licences, extensions) sont à la charge du client et réglés directement par lui, sauf mention contraire du devis.',
            'start_event' => 'À réception de l’acompte et des éléments nécessaires (contenus, logo, accès)',
            'review_days' => '7',
            'revision_rounds' => '2',
            'feedback' => 'Par e-mail, en une liste consolidée par série de retours',
            'anomalies' => 'Les anomalies signalées pendant la vérification et relevant du périmètre du devis sont corrigées sans frais.',
            'validation' => 'Validation écrite du client par e-mail, ou absence de retour à l’issue du délai de vérification',
            'production' => 'Mise en ligne après validation et paiement du solde',
            'ip_mode' => 'cession',
            'ip_creations' => 'Créations graphiques et code spécifiques réalisés pour le client',
            'ip_scope' => 'Droits de reproduction, de représentation et d’adaptation',
            'ip_uses' => 'Exploitation du site internet du client',
            'ip_media' => 'Internet et supports numériques',
            'ip_territory' => 'Monde entier',
            'ip_duration' => 'Durée légale de protection des droits d’auteur',
            'ip_payment' => 'Incluse dans le prix du devis',
            'ip_effect' => 'Au paiement intégral du prix',
            'ip_license' => '',
            'domain_mode' => 'client',
            'domain_owner' => 'Le client',
            'domain_account' => 'Compte du client chez son registraire',
            'domain_manager' => 'Le client, avec l’assistance du prestataire si besoin',
            'domain_payer' => 'Le client',
            'domain_renewal' => 'Le client',
            'domain_cost' => 'Selon le tarif du registraire, généralement annuel',
            'hosting_mode' => 'client',
            'hosting_owner' => 'Le client',
            'hosting_manager' => 'Le client, avec l’assistance du prestataire si besoin',
            'hosting_payer' => 'Le client',
            'hosting_renewal' => 'Le client',
            'hosting_cost' => 'Selon le tarif de l’hébergeur choisi',
            'maintenance_mode' => 'aucune',
            'privacy_processing' => 'non',
            'signature_place' => preg_match('/\d{5}\s+([^,]+)/u', (string) config('codenyr.legal.address'), $m) ? trim($m[1]) : '',
        ];

        return array_combine(array_map(fn (string $key): string => 'contract_'.$key, array_keys($values)), $values);
    }

    /** Text of the standard CGV version created when none is active. */
    public static function termsContent(): string
    {
        return 'Codenyr est le nom commercial de '.config('codenyr.legal.owner').', développeur web indépendant. '
            .'Les présentes conditions générales de vente s’appliquent à toutes les prestations de création de sites internet et de développement web proposées par Codenyr, à l’exclusion de tout autre document du client, sauf accord écrit contraire. '
            .'Elles sont communiquées au client avant toute commande et sont disponibles sur simple demande.';
    }

    /** Typical delivery delay per project type, used when a quote has none. */
    public static function estimatedDelay(string $projectType): string
    {
        return match ($projectType) {
            'Landing Page' => '2 semaines après le démarrage',
            'Site vitrine' => '3 à 4 semaines après le démarrage',
            'Site Pro' => '6 semaines après le démarrage',
            default => '6 à 8 semaines après le démarrage',
        };
    }

    /** Number of days matching estimatedDelay(), to plan the delivery date. */
    public static function delayDays(string $projectType): int
    {
        return match ($projectType) {
            'Landing Page' => 14,
            'Site vitrine' => 28,
            'Site Pro' => 42,
            default => 56,
        };
    }
}
