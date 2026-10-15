<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('category', 60);
            $table->string('title', 200);
            $table->text('details')->nullable();
            // Optional automatic check (see AdminTask::AUTO_CHECKS): the task completes itself once true.
            $table->string('auto_check', 60)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
        });

        $tasks = [
            ['Administratif', 'Souscrire une domiciliation d’entreprise', 'Elle évite de publier votre adresse personnelle. Une fois souscrite, remplacez l’adresse dans CODENYR_LEGAL_ADDRESS (fichier .env de production).', null],
            ['Administratif', 'Déclarer la micro-entreprise sur le guichet unique', 'formalites.entreprises.gouv.fr — activité de développement web (prestation de services). Vous recevrez ensuite votre SIRET. Ne facturez et ne signez aucun devis avant.', null],
            ['Administratif', 'Renseigner le SIRET sur le site', 'Ajoutez CODENYR_LEGAL_SIRET=… dans le .env de production : les mentions légales se mettent à jour. Cette tâche se coche toute seule.', 'legal_siret'],
            ['Administratif', 'Confirmer le régime de TVA', 'Par défaut : franchise en base (« TVA non applicable, art. 293 B du CGI »). Si ce n’est pas votre cas, modifiez CODENYR_LEGAL_VAT et les paramètres commerciaux.', null],
            ['Administratif', 'Vérifier l’éligibilité à l’ACRE', 'Réduction de cotisations la première année. Ensuite, cochez l’option ACRE dans Comptabilité pour les mois concernés.', null],
            ['Administratif', 'Ouvrir un compte bancaire dédié', 'Obligatoire si le chiffre d’affaires dépasse 10 000 € deux années civiles de suite ; recommandé dès le départ pour séparer vos comptes.', null],
            ['Administratif', 'Souscrire une assurance responsabilité civile professionnelle', 'Non obligatoire pour le développement web, mais fortement recommandée (erreur, perte de données, retard client).', null],
            ['Juridique', 'Adhérer à un médiateur de la consommation', 'Obligatoire dès le premier client particulier. Ajoutez ensuite son nom et son site dans CODENYR_LEGAL_MEDIATOR. Cette tâche se coche toute seule.', 'legal_mediator'],
            ['Juridique', 'Faire relire les CGV', 'Vérifier notamment le droit de rétractation de 14 jours pour les particuliers et la renonciation si la prestation commence avant la fin du délai.', null],
            ['Juridique', 'Prouver la licence des photos des démos', 'Retrouver la page d’origine de chaque photo (Atelier, Canopée, Sillage), garder le lien et une capture datée de la licence. Remplacer celles introuvables.', null],
            ['Juridique', 'Compléter la politique de confidentialité', 'Nommer le prestataire d’envoi d’e-mails et préciser la région du serveur OVH (France / UE).', null],
            ['Juridique', 'Définir les durées de conservation des données', 'Repères CNIL : prospects 3 ans après le dernier contact, données de contrat 5 ans, factures 10 ans. Puis activer la suppression automatique.', null],
            ['Mise en ligne', 'Supprimer le compte admin de test', 'admin-test@codenyr.test, créé pour les vérifications visuelles. Cette tâche se coche toute seule une fois le compte supprimé.', 'test_admin_removed'],
            ['Mise en ligne', 'Configurer le serveur de production', 'APP_ENV=production, APP_DEBUG=false, HTTPS, envoi d’e-mails réel, sauvegardes de la base.', null],
            ['Mise en ligne', 'Enregistrer et déployer les modifications', 'Les dernières modifications du site ne sont pas encore commitées.', null],
        ];

        $now = now();
        DB::table('admin_tasks')->insert(array_map(fn (array $task, int $index) => [
            'category' => $task[0], 'title' => $task[1], 'details' => $task[2], 'auto_check' => $task[3],
            'position' => $index + 1, 'created_at' => $now, 'updated_at' => $now,
        ], $tasks, array_keys($tasks)));
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_tasks');
    }
};
