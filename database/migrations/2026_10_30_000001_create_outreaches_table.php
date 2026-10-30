<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreaches', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('activity', 160)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('phone', 40)->nullable();
            // Key of config('codenyr.offers').
            $table->string('offer', 30)->nullable();
            $table->string('status', 30)->default('todo')->index();
            $table->string('channel', 20)->nullable();
            $table->date('last_contact_on')->nullable();
            $table->string('next_action', 200)->nullable();
            $table->date('next_action_on')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Businesses found while canvassing the Chalon area (October 2026).
        $rows = [
            ['Céline David (potière)', 'Céramique utilitaire en grès', '6 place du Cloître, 71100 Chalon-sur-Saône', '03 58 77 92 03', 'vitrine', 'no_answer', 'phone', '2026-10-30', 'Rappeler (ou passer à l’atelier)', '2026-11-03', 'Pas de réponse le 30/10. Numéro à confirmer : fiche PagesJaunes au nom de Céline Bievre Poulalier. Anime des ateliers pour l’Office de tourisme, fait des séries pour des restaurants.'],
            ['Ojas (jardinerie urbaine)', 'Plantes, vrac, rempotage, ateliers', '2 rue aux Fèvres, 71100 Chalon-sur-Saône', '06 89 41 32 10', 'pro', 'refused', 'phone', '2026-10-30', 'Relancer avec la Landing Page à 390 €', '2027-04-30', 'Refus le 30/10 : pas de budget pour le moment. Autre numéro possible : 03 85 45 74 75.'],
            ['Caféline (bar à chats)', 'Bar à chats, ouverture en octobre 2026', 'Rue des Poulets, 71100 Chalon-sur-Saône', null, 'custom', 'todo', 'visit', null, 'Passer sur place', null, 'Gérante : Isoline Denet. Partenaire de l’association Nouveau Chat’Pitre 71.'],
            ['Déborah B (coiffure)', 'Salon mixte et bien-être', '16 rue des Places, 71100 Chalon-sur-Saône', '07 68 89 44 21', 'landing', 'todo', 'phone', null, 'Appeler (lun., mar., jeu. ou ven.)', null, '5★ sur Planity, déjà utilisé pour les rendez-vous.'],
            ['Willi’s Burger', 'Burger (ancien Neptune)', '33 place de Beaune, 71100 Chalon-sur-Saône', '03 85 46 59 31', 'landing', 'todo', 'phone', null, 'Appeler entre 15 h et 17 h', null, 'Demander Luca Perana. Visible surtout sur Uber Eats.'],
            ['SMG Elec & Solaire', 'Électricité, solaire, bornes', '4 rue du Château, 71100 Saint-Rémy', '06 76 28 14 10', 'vitrine', 'todo', 'phone', null, 'Appeler vers 18 h ou 7 h 30', null, 'Gérant : Matthieu Grivel. Référencé Qualit’EnR.'],
            ['Charlie Poncelet Maçonnerie', 'Maçonnerie, carrelage', '4 rue du Pré de la Mare, 71590 Gergy', null, 'pro', 'todo', 'mail', null, 'Déposer le courrier ou passer', null, 'Pas de numéro trouvé.'],
            ['L’instant T (brocante)', 'Brocante, seconde main', 'Rue des Poulets, 71100 Chalon-sur-Saône', null, 'landing', 'todo', 'visit', null, 'Passer sur place', null, 'Gérant : Frédéric Petiot. Vient de s’agrandir.'],
            ['Bazar de Sido', 'Brocante, bar, romans policiers', 'Angle de la rue du Blé, 71100 Chalon-sur-Saône', null, 'landing', 'todo', 'visit', null, 'Passer sur place', null, 'Gérante : Claudine Pernot.'],
            ['L’Atelier Pâtissier', 'Pâtisserie, traiteur, cours de pâtisserie', '32 rue de Thiard, 71100 Chalon-sur-Saône', '07 68 21 02 00', 'vitrine', 'todo', 'phone', null, 'Appeler', null, 'Gâteaux de fête et pièces montées sur commande : intérêt pour un formulaire de commande.'],
            ['Fournil & Compagnie', 'Boulangerie-pâtisserie', '18 place de la République, 71100 Chalon-sur-Saône', '03 85 48 09 48', 'landing', 'todo', 'phone', null, 'Appeler vers 14 h – 15 h', null, 'Société créée en 2019, devanture refaite récemment, 4,3/5 (230 avis).'],
            ['Boulangerie des Charreaux', 'Boulangerie-pâtisserie', '4 place Claude Bernard, 71100 Chalon-sur-Saône', '03 85 46 11 45', 'landing', 'todo', 'phone', null, 'Appeler vers 14 h – 15 h', null, '4,5/5 (78 avis), ouverte le dimanche.'],
            ['Les jardins de Nathalie', 'Fleuriste', '5 rue de la Citadelle, 71100 Chalon-sur-Saône', '03 85 48 18 84', 'vitrine', 'todo', 'phone', null, 'Appeler', null, 'Numéro issu de l’annuaire chalon-commerces.fr : à confirmer.'],
            ['La Sornaysienne (boucherie)', 'Boucherie', '62 rue aux Fèvres, 71100 Chalon-sur-Saône', '03 85 94 72 98', 'landing', 'todo', 'phone', null, 'Appeler l’après-midi', null, 'Peut-être la boucherie de Laurent Strappazzon (même adresse).'],
            ['Les Beaux Frères (primeur)', 'Fruits et légumes', '26 place de Beaune, 71100 Chalon-sur-Saône', '03 85 91 63 01', 'landing', 'todo', 'phone', null, 'Appeler', null, 'Seulement dans l’annuaire chalon-commerces.fr : vérifier qu’il est toujours ouvert.'],
            ['Autres Ambiances', 'Mode, cadeaux, décoration', '5 rue des Poulets, 71100 Chalon-sur-Saône', '03 85 48 25 16', 'landing', 'todo', 'visit', null, 'Passer sur place', null, 'Même rue que Caféline et L’instant T.'],
            ['Chapeau chic et gants de cuir', 'Chapellerie, gants', '7 rue aux Fèvres, 71100 Chalon-sur-Saône', '03 85 93 46 71', 'landing', 'todo', 'phone', null, 'Appeler', null, 'Seulement dans l’annuaire chalon-commerces.fr : vérifier qu’il est toujours ouvert.'],
            ['L’avenue du Cuir', 'Maroquinerie', '15 rue aux Fèvres, 71100 Chalon-sur-Saône', '03 58 93 42 86', 'landing', 'todo', 'phone', null, 'Appeler', null, 'Seulement dans l’annuaire chalon-commerces.fr : vérifier qu’il est toujours ouvert.'],
            ['Bellissimo Passo', 'Chaussures italiennes (ouvert en octobre 2026)', '17 Grande Rue, 71100 Chalon-sur-Saône', null, 'landing', 'todo', 'visit', null, 'Passer sur place', null, 'Vient d’ouvrir. Pas de numéro trouvé.'],
        ];

        $now = now();
        DB::table('outreaches')->insert(array_map(fn (array $row) => array_combine(
            ['name', 'activity', 'address', 'phone', 'offer', 'status', 'channel', 'last_contact_on', 'next_action', 'next_action_on', 'notes'], $row,
        ) + ['created_at' => $now, 'updated_at' => $now], $rows));
    }

    public function down(): void
    {
        Schema::dropIfExists('outreaches');
    }
};
