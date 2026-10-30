<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Second batch of businesses found in central Chalon: a phone number and no website. */
    private const ROWS = [
        ['En Apparté by Sylvie Martin', 'Salon de coiffure', '6 rue du Docteur Mauchamp, 71100 Chalon-sur-Saône', '03 85 90 80 18', 'landing', 'Appeler en début d’après-midi', '5,0/5 avec plus de 1 000 avis sur Planity, aucune page à elle. Argument : une page à soi, sans les concurrents à côté, avec le bouton vers Planity.'],
        ['Vanessa « De mèche avec vous »', 'Salon de coiffure, coiffure de mariée', '7 rue de la Citadelle, 71100 Chalon-sur-Saône', '03 85 48 41 34', 'landing', 'Appeler en début d’après-midi', '5,0/5 (407 avis Planity). Coiffure de mariée : une galerie photo lui servirait.'],
        ['Sept 1 Coiffeur by Amandine', 'Salon de coiffure', '66 rue aux Fèvres, 71100 Chalon-sur-Saône', '03 85 93 64 73', 'landing', 'Appeler en début d’après-midi', 'Peu d’avis sur Planity (3) : probablement récent.'],
        ['La Garçonnière de Nath', 'Barbier, centre capillaire, head spa', '33 rue de Thiard, 71100 Chalon-sur-Saône', '07 67 73 56 16', 'landing', 'Appeler', 'SAS les Garçons Coiffeurs. Concept original à expliquer (head spa).'],
        ['La Petite Coiffeuse Barbière', 'Coiffure, barbier', '60 avenue de Paris, 71100 Chalon-sur-Saône', '09 87 05 95 35', 'landing', 'Appeler', 'Salon de quartier (Laennec).'],
        ['Airstyliste Coiffure', 'Salon de coiffure (Maîtres Artisans)', '20 avenue Jean Jaurès, 71100 Chalon-sur-Saône', '06 37 66 29 91', 'landing', 'Appeler', 'À vérifier : un autre numéro (03 85 48 32 90) et un autre nom (Cyril D. Coiffure) circulent à cette adresse.'],
        ['Cellier Saint-Vincent', 'Caviste, dégustations', '14 place Saint-Vincent, 71100 Chalon-sur-Saône', '03 85 48 78 25', 'vitrine', 'Appeler', 'Référencé Gault&Millau, personnel anglophone : clientèle touristique du quartier de la cathédrale.'],
        ['Terroirs & Millésimes', 'Caviste, caveau de dégustation', '36 rue de Strasbourg, 71100 Chalon-sur-Saône', '03 85 48 48 28', 'landing', 'Appeler', 'Île Saint-Laurent. Ancien numéro possible : 03 85 48 01 48.'],
        ['La Cave des Tonneliers', 'Caviste', '13 place de l’Hôtel de Ville, 71100 Chalon-sur-Saône', '06 29 41 09 00', 'landing', 'Appeler', 'Adresse incertaine : 13 place de l’Hôtel de Ville ou 9 rue Pasteur selon les sources.'],
        ['Bijouterie Jungo', 'Bijouterie familiale depuis 1959', '104 bis avenue de Paris, 71100 Chalon-sur-Saône', '03 85 43 00 55', 'vitrine', 'Appeler', 'Entreprise familiale depuis 1959 : une histoire à raconter.'],
        ['Atelier Bijou Créatif', 'Atelier et boutique de bijoux', '32 rue aux Fèvres, 71100 Chalon-sur-Saône', '03 85 48 15 32', 'vitrine', 'Appeler l’après-midi (ouvert 14 h – 19 h)', 'Artisan d’art. S’il propose des ateliers : réservation en ligne.'],
        ['Le Clown Gourmand', 'Restaurant gastronomique', '13 rue de Strasbourg, 71100 Chalon-sur-Saône', '03 85 93 64 61', 'vitrine', 'Appeler entre 15 h et 17 h', 'Référencé Gault&Millau, chef Laurent Montgillard, menus 28 – 38 €. Pas de site pour présenter la carte.'],
        ['Basta Cosi', 'Restaurant italien', '23 rue au Change, 71100 Chalon-sur-Saône', '03 85 90 95 08', 'landing', 'Appeler entre 15 h et 17 h', 'Ouvert midi et soir du mardi au samedi.'],
        ['Le Cèdre', 'Restaurant, traiteur, banquets', '55 rue de Strasbourg, 71100 Chalon-sur-Saône', '03 85 93 59 73', 'landing', 'Appeler entre 15 h et 17 h', 'Fait traiteur, banquets et anniversaires : un formulaire de demande lui servirait.'],
    ];

    /** Exact addresses found since the first batch, applied only where the vague one is untouched. */
    private const ADDRESSES = [
        'Bazar de Sido' => ['Angle de la rue du Blé, 71100 Chalon-sur-Saône', '14 rue du Blé, 71100 Chalon-sur-Saône'],
        'L’instant T (brocante)' => ['Rue des Poulets, 71100 Chalon-sur-Saône', '8 rue des Poulets, 71100 Chalon-sur-Saône'],
    ];

    public function up(): void
    {
        $now = now();
        $existing = DB::table('outreaches')->pluck('name')->all();
        $rows = array_filter(self::ROWS, fn (array $row) => ! in_array($row[0], $existing));
        DB::table('outreaches')->insert(array_values(array_map(fn (array $row) => [
            'name' => $row[0], 'activity' => $row[1], 'address' => $row[2], 'phone' => $row[3], 'offer' => $row[4],
            'status' => 'todo', 'channel' => 'phone', 'next_action' => $row[5], 'notes' => $row[6],
            'created_at' => $now, 'updated_at' => $now,
        ], $rows)));

        foreach (self::ADDRESSES as $name => [$old, $new]) {
            DB::table('outreaches')->where('name', $name)->where('address', $old)->update(['address' => $new]);
        }
    }

    public function down(): void
    {
        DB::table('outreaches')->where('status', 'todo')->whereIn('name', array_column(self::ROWS, 0))->delete();
        foreach (self::ADDRESSES as $name => [$old, $new]) {
            DB::table('outreaches')->where('name', $name)->where('address', $new)->update(['address' => $old]);
        }
    }
};
