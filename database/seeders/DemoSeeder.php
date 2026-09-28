<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['Atelier & matière', 'atelier-matiere', 'Artisanat', 'Un site vitrine qui laisse parler le savoir-faire.'], ['La Table locale', 'la-table-locale', 'Restauration', 'Une présence soignée pour donner envie de pousser la porte.'], ['Horizon conseil', 'horizon-conseil', 'Services aux entreprises', 'Une présentation claire pour faciliter le premier contact.']] as [$name, $slug, $category, $short]) {
            $image = 'projects/demo-'.$slug.'.svg';
            Storage::disk('public')->put($image, File::get(resource_path('demo/'.$slug.'.svg')));
            Project::firstOrCreate(['slug' => $slug], [
                'name' => $name, 'client' => 'Concept fictif — démonstration Codenyr', 'category' => $category,
                'short_description' => $short,
                'description' => 'Exploration de design et d’architecture pour un site professionnel. Ce concept est fictif : il ne représente ni un client réel ni une mission livrée.',
                'problem' => 'Présenter une activité de façon claire, agréable sur mobile et orientée vers la prise de contact.',
                'solution' => 'Une hiérarchie visuelle simple, des contenus structurés et un parcours court vers le formulaire.',
                'features' => "Présentation de l’activité\nServices et savoir-faire\nFormulaire de contact\nAffichage responsive",
                'technologies' => ['Laravel', 'Blade', 'Livewire', 'Tailwind CSS'],
                'image' => $image,
                'published' => true, 'featured' => true, 'is_demo' => true,
            ]);
        }
    }
}
