<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Category;
use App\Models\Employeur;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Installe les modules servis par le portail : Personnel & paie avec ses
 * employeurs, et Badges.
 *
 * Idempotent, et joue a chaque deploiement : il ne cree que ce qui manque et
 * n'ecrase jamais ce qui existe. La tuile n'est attribuee aux administrateurs
 * qu'a sa creation — si l'un d'eux s'en detache ensuite, le deploiement
 * suivant ne la lui remet pas.
 *
 * La grille salariale de demonstration vit dans son propre semeur
 * (GrilleRhExempleSeeder) : les montants reels se saisissent dans l'interface.
 */
class ModulesPortailSeeder extends Seeder
{
    public function run(): void
    {
        $existante = Application::where('module_key', 'personnel')->exists();

        $module = $this->tuile();
        $this->employeurs();

        if ($existante) {
            return;
        }

        // Premiere installation : les administrateurs du portail voient la
        // tuile d'emblee. Les gestionnaires RH se rajoutent ensuite depuis
        // /admin/applications, avec leurs entites.
        foreach (User::where('role', 'admin')->get() as $administrateur) {
            $administrateur->applications()->syncWithoutDetaching([
                $module->id => ['role_in_app' => 'drh', 'roles' => json_encode(['drh'])],
            ]);
        }
    }

    private function tuile(): Application
    {
        $categorie = Category::firstWhere('slug', 'ressources-humaines');

        // Badges : ouvert a tout le personnel, il se pose de lui-meme sur les
        // tableaux de bord ; la tuile suffit a l'existence du module.
        Application::updateOrCreate(
            ['module_key' => 'badges'],
            [
                'name' => 'Badges',
                'slug' => 'badges',
                'description' => "Demander son badge professionnel et suivre sa fabrication.",
                'type' => 'module',
                'url' => null,
                'category_id' => $categorie?->id,
                'icon' => 'key',
                'color' => '#4f46e5',
                'is_active' => true,
                'sort_order' => 8,
            ],
        );

        return Application::updateOrCreate(
            ['module_key' => 'personnel'],
            [
                'name' => 'Personnel & paie',
                'slug' => 'personnel-paie',
                'description' => "Dossiers du personnel, carrière, contrats et paie mensuelle du groupe.",
                'type' => 'module',
                'url' => null,
                'category_id' => $categorie?->id,
                'icon' => 'briefcase',
                'color' => '#0f766e',
                'is_active' => true,
                'sort_order' => 7,
            ],
        );
    }

    /** Un employeur par institut : chacun a sa CNPS et signe ses bulletins. */
    private function employeurs(): void
    {
        $instituts = [
            'ium' => 'Institut Universitaire La Majestueuse',
            'ifpm' => 'Institut de Formation Professionnelle La Majestueuse',
            'gsbm' => 'Groupe Scolaire Bilingue La Majestueuse',
        ];

        foreach ($instituts as $slug => $nom) {
            $application = Application::firstWhere('slug', $slug);

            Employeur::firstOrCreate(
                ['sigle' => mb_strtoupper($slug)],
                ['nom' => $nom, 'application_id' => $application?->id, 'actif' => true],
            );
        }
    }

}
