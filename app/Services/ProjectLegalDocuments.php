<?php

namespace App\Services;

use App\Models\ClientProject;
use App\Models\CommercialSetting;
use App\Models\Quote;

class ProjectLegalDocuments
{
    public function content(ClientProject $project, string $type, ?Quote $quote = null, string $audience = ''): string
    {
        $seller = CommercialSetting::current();
        $client = $project->client;
        $value = fn ($value, string $label): string => filled($value) ? (string) $value : '[À compléter : '.$label.']';
        $identity = $seller['name']."\n".$value($seller['legal_name'], 'identité légale')."\n".$value($seller['address'], 'adresse professionnelle')."\nSIREN / SIRET : ".$value($seller['registration'], 'identifiant entreprise')."\n".$value($seller['email'], 'e-mail professionnel')."\n".$seller['phone']."\n[À compléter : statut juridique, TVA intracommunautaire si applicable]\n".$value($seller['tax_mention'], 'régime fiscal et mention TVA applicable');
        $parties = $identity."\n\nClient : ".$client->name."\n".$value($client->contact, 'représentant du client')."\n".$value($client->address, 'adresse client')."\n".$value($client->email, 'e-mail client')."\n".$client->phone."\nSIRET : ".$value($client->registration, 'SIRET ou non applicable');
        $services = $quote ? collect($quote->items)->filter(fn ($item) => ! $item['optional'] || $item['selected'])->map(fn ($item) => $item['name'].' — '.$item['description'])->implode("\n") : '[À compléter : prestations et options du devis]';
        $price = $quote ? 'Devis '.$quote->number.' : HT '.CommercialCalculator::euros($quote->totals['initial']['ht']).' ; TVA '.CommercialCalculator::euros($quote->totals['initial']['vat']).' ; TTC '.CommercialCalculator::euros($quote->totals['initial']['ttc']) : '[À compléter : devis, prix HT, TVA et TTC en euros]';
        $payment = $quote ? $value($quote->conditions, 'conditions de règlement')."\nAcompte : ".CommercialCalculator::euros($quote->totals['deposit']).' ; solde contractuel : '.CommercialCalculator::euros($quote->totals['remaining']) : '[À compléter : acompte et solde]';
        if ($quote) {
            foreach ($project->invoices()->where('quote_id', $quote->id)->where('invoice_type', 'deposit')->whereNotNull('finalized_at')->with('payments')->get() as $invoice) {
                $payment .= "\nFacture d’acompte ".$invoice->number.' — échéance '.$invoice->due_on->format('d/m/Y').' — reçu : '.CommercialCalculator::euros($invoice->paidCents()).' — reste à payer : '.CommercialCalculator::euros($invoice->remainingCents());
            }
        }
        $planning = 'Début : '.$value($project->starts_on?->format('d/m/Y'), 'date de début').' ; livraison : '.$value($project->due_on?->format('d/m/Y'), 'date de livraison')."\nDurée : ".$value($quote?->estimated_delay, 'durée estimée')."\nLe calendrier tient compte de la réception des contenus et des validations du client. Tout retard est signalé et un calendrier actualisé est convenu.";
        $rights = 'Les contenus du client restent soumis à ses droits. Les composants, bibliothèques, frameworks et ressources de tiers conservent leurs licences propres. Une éventuelle cession prend effet après paiement intégral dans les limites expressément convenues. [À compléter : créations concernées, droits cédés ou concédés, usages, supports, territoire, durée et rémunération ; droits conservés par Codenyr]';
        $hosting = 'Domaine : '.$value($project->domain, 'domaine ou non concerné').' ; hébergeur : '.$value($project->host, 'hébergeur ou non concerné')."\n[À compléter : titulaire du domaine, propriétaire des comptes, souscripteur, responsable des renouvellements, coûts et périodicité]";
        $maintenance = $project->maintenance->map(fn ($m) => $m->name.' — '.CommercialCalculator::euros($m->amount_cents).' / '.$m->frequency.' ; durée '.$m->duration_months.' mois ; inclus : '.$m->included.' ; contrat document #'.($m->document_id ?? 'à compléter'))->implode("\n");
        $maintenance = ($maintenance ?: '[À compléter : maintenance incluse oui/non, durée, prix, fréquence, inclusions, exclusions et contrat associé]')."\nLa maintenance et les évolutions se distinguent de la création et de la correction des anomalies. [À compléter : garantie de correction, durée et procédure]";
        $termination = 'Les parties définissent les manquements justifiant une résiliation et la procédure de notification. [À compléter : préavis, mise en demeure, délai de remède, règlement du travail réalisé, sort de l’acompte, remise des travaux et accès ; dispositions impératives applicables]';
        $liability = 'Chaque partie répond de ses obligations contractuelles. Les incidents d’hébergement, services externes et API, modifications par des tiers, usages et contenus du client sont examinés selon les responsabilités effectives. Aucune clause ne prive le client de ses droits impératifs. [À compléter : engagements, limites licites et procédure de signalement]';
        $disputes = $audience === 'professional' ? 'Client professionnel. [À compléter : pénalités de retard, conditions d’exigibilité, indemnité de recouvrement applicable et éventuelle attribution de compétence légalement admissible]' : ($audience === 'consumer' ? 'Client consommateur. [À compléter : médiateur compétent, coordonnées et modalités de saisine ; rétractation selon le mode de conclusion, formulaire et conditions d’exécution anticipée ; garanties légales applicables]. Les règles impératives de compétence restent applicables.' : '[À compléter : qualité du client, professionnel ou consommateur, et clauses correspondantes]');
        $sections = $type === 'terms' ? [
            'Identité du prestataire' => $identity,
            'Objet' => 'Ces conditions encadrent les prestations définies au devis : sites vitrines, landing pages, développement sur mesure, fonctionnalités, intégration, maintenance et, lorsqu’ils sont prévus, hébergement, domaine et accompagnement.',
            'Devis et formation du contrat' => 'Le devis décrit le périmètre et les prix. Validité : '.$seller['validity_days'].' jours, sauf date spécifique au devis. Les demandes hors périmètre nécessitent un accord complémentaire. [À compléter : modalités d’acceptation, commande ferme et condition d’acompte]',
            'Prix' => $price."\nLes services tiers (licences, API, abonnements, extensions, domaine, hébergement) ne sont inclus que si le devis les prévoit. [À compléter : frais supplémentaires ou absence de frais]",
            'Modalités de paiement' => $payment."\nDélai configuré : ".$seller['payment_days'].' jours. [À compléter : moyens de paiement, échéance de l’acompte et événement déclenchant le solde]',
            'Retard de paiement' => $disputes."\n[À compléter : procédure de suspension en cas d’impayé]",
            'Délais de réalisation' => $planning,
            'Obligations du client' => 'Le client fournit textes, images, logos, coordonnées, informations légales, accès et validations nécessaires. Il garantit disposer des droits sur les éléments transmis.',
            'Modifications et révisions' => 'Les corrections du périmètre convenu se distinguent des nouvelles fonctionnalités, soumises à estimation et accord écrit. [À compléter : nombre d’allers-retours inclus et procédure de demande]',
            'Validation et livraison' => 'Le site est présenté pour vérification avant validation finale et mise en production. [À compléter : délais de recette, corrections, critères de validation et livrables]',
            'Propriété intellectuelle' => $rights,
            'Nom de domaine et hébergement' => $hosting,
            'Maintenance' => $maintenance,
            'Responsabilité' => $liability,
            'Sauvegardes et sécurité' => '[À compléter : responsables, fréquence et conservation des sauvegardes, restauration, mises à jour, protection des accès et périmètre de sécurité effectivement inclus]',
            'Données personnelles' => 'Les données de la relation commerciale et celles traitées par le site du client sont distinctes. Les rôles sont déterminés selon les traitements réels. Si Codenyr traite des données pour le compte du client, un accord adapté encadre cette sous-traitance. [À compléter : finalités, bases légales, destinataires, conservation, droits et contact ; annexe de sous-traitance si nécessaire]',
            'Résiliation et annulation' => $termination,
            'Force majeure' => 'Un événement répondant aux conditions légales de force majeure peut suspendre les obligations empêchées. La partie concernée informe l’autre et recherche des mesures adaptées ; un empêchement définitif entraîne les conséquences prévues par le droit applicable.',
            'Droit applicable et litiges' => 'Le droit français s’applique sous réserve des protections impératives. Les parties recherchent une solution amiable sans faire obstacle aux recours légaux. '.$disputes,
            'Acceptation des CGV' => 'Projet : '.$project->name.' ; client : '.$client->name.' ; devis : '.($quote->number ?? '[À compléter : référence]')."\n[À compléter : version applicable, date et modalités d’acceptation]",
        ] : [
            'Identification des parties' => $parties."\n".$disputes,
            'Objet du contrat' => $value($project->description, 'description du projet'),
            'Périmètre de la prestation' => $services,
            'Éléments non compris' => '[À compléter : exclusions du projet, rédaction, logo, photographie, SEO avancé, publicité, maintenance, hébergement et domaine selon le devis]',
            'Technologies (facultatif)' => '[À compléter : technologies prévues ou supprimer cette section]',
            'Planning' => $planning,
            'Obligations de Codenyr' => 'Codenyr réalise les fonctionnalités convenues, informe le client de l’avancement utile, respecte le périmètre, corrige les anomalies selon les conditions convenues et protège les accès confiés.',
            'Obligations du client' => 'Le client fournit les contenus, accès et validations, détient les droits nécessaires et règle les échéances convenues.',
            'Prix' => $price,
            'Acompte' => $payment."\n[À compléter : échéance de l’acompte si non facturé]",
            'Solde' => '[À compléter : échéance et conditions de facturation finale]. Les paiements indiqués sont ceux connus lors de la préparation du document.',
            'Demandes supplémentaires' => 'Toute demande hors périmètre nécessite une estimation et un accord écrit : avenant ou devis complémentaire avant réalisation.',
            'Propriété intellectuelle' => $rights,
            'Hébergement et nom de domaine' => $hosting,
            'Maintenance' => $maintenance,
            'Confidentialité' => 'Les informations confidentielles reçues pour le projet sont utilisées pour son exécution et communiquées uniquement aux personnes qui en ont besoin, sous obligation de confidentialité, sous réserve des obligations légales. [À compléter : durée, exclusions et restitution]',
            'Résiliation' => $termination,
            'Responsabilité' => $liability,
            'Documents contractuels' => 'Devis : '.($quote->number ?? '[À compléter : devis accepté]')."\n[À compléter : identifiant et version des CGV, avenants, maintenance et ordre de priorité convenu]",
            'Signatures' => "Pour Codenyr :\nNom :\nQualité :\nDate :\nSignature :\n\nPour le client :\nNom :\nQualité :\nDate :\nMention d’acceptation :\nSignature :",
        ];
        $result = [];
        foreach ($sections as $title => $body) {
            $result[] = 'ARTICLE '.(count($result) + 1).' — '.mb_strtoupper($title)."\n".$body;
        }

        return implode("\n\n", $result);
    }
}
