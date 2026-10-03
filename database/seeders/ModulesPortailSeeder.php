<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Category;
use App\Models\Employeur;
use App\Models\ReglageEmail;
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
 * Cote paie, deux seeders se suivent : GrilleIumSeeder apporte les
 * indemnites et les profils repris d'IUM, puis GrilleSalarialeSeeder pose la
 * grille officielle du groupe (12 categories, echelons A a F) et retire
 * l'extrait partiel qu'IUM portait. Aucun des deux ne rejoue une fois en
 * place : la grille vit ensuite dans l'ecran des referentiels.
 */
class ModulesPortailSeeder extends Seeder
{
    public function run(): void
    {
        $this->expediteurParDefaut();

        $existante = Application::where('module_key', 'personnel')->exists();

        $module = $this->tuile();
        $this->employeurs();
        $this->call(GrilleIumSeeder::class);
        $this->call(GrilleSalarialeSeeder::class);

        if ($existante) {
            return;
        }

        // Premiere installation : les administrateurs du portail voient la
        // tuile d'emblee. Les gestionnaires RH se rajoutent ensuite depuis
        // /admin/applications, avec leurs entites.
        foreach (User::whereIn('role', ['admin', 'superadmin'])->get() as $administrateur) {
            $administrateur->applications()->syncWithoutDetaching([
                $module->id => ['role_in_app' => 'drh', 'roles' => json_encode(['drh'])],
            ]);
        }
    }

    /**
     * L'adresse d'envoi du groupe, posee une fois. Les reglages du relais se
     * saisissent ensuite dans /admin/email ; on ne touche a rien s'ils
     * existent deja.
     */
    private function expediteurParDefaut(): void
    {
        if (ReglageEmail::exists()) {
            return;
        }

        ReglageEmail::create([
            'actif' => false,
            'port' => 587,
            'chiffrement' => 'tls',
            'expediteur' => 'info@lamajestueuse.com',
            'nom_expediteur' => 'La Majestueuse',
        ]);
    }

    private function tuile(): Application
    {
        $categorie = Category::firstWhere('slug', 'ressources-humaines');

        /*
         * Mon profil : ouvert a tous et pose en tete du tableau de bord.
         * Son logo n'est pas fixe — c'est la photo de celui qui regarde.
         */
        $this->poserTuile(
            ['module_key' => 'profil'],
            [
                'name' => 'Mon profil',
                'slug' => 'mon-profil',
                'description' => 'Vos informations, votre parcours et vos pièces.',
                'type' => 'module',
                'url' => null,
                'category_id' => $categorie?->id,
                'icon' => 'user',
                'color' => '#0f766e',
                'is_active' => true,
                'sort_order' => 0,
            ],
        );

        // Tutoriels : ouvert a tous, juste apres le profil.
        $this->poserTuile(
            ['module_key' => 'tutoriels'],
            [
                'name' => 'Tutoriels',
                'slug' => 'tutoriels',
                'description' => 'Apprendre à se servir du portail, module par module.',
                'type' => 'module',
                'url' => null,
                'category_id' => $categorie?->id,
                'icon' => 'book',
                'color' => '#b45309',
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        // Mes bulletins de paie : chacun consulte les siens.
        $this->poserTuile(
            ['module_key' => 'bulletins'],
            [
                'name' => 'Mon bulletin de paie',
                'slug' => 'mes-bulletins',
                'description' => 'Consulter et télécharger ses bulletins de paie.',
                'type' => 'module',
                'url' => null,
                'category_id' => $categorie?->id,
                'icon' => 'wallet',
                'color' => '#0f766e',
                'is_active' => true,
                'sort_order' => 9,
            ],
        );

        // Badges : ouvert a tout le personnel, il se pose de lui-meme sur les
        // tableaux de bord ; la tuile suffit a l'existence du module.
        $this->poserTuile(
            ['module_key' => 'badges'],
            [
                'name' => 'Badges',
                'slug' => 'badges',
                'description' => 'Demander son badge professionnel et suivre sa fabrication.',
                'type' => 'module',
                'url' => null,
                'category_id' => $categorie?->id,
                'icon' => 'key',
                'color' => '#4f46e5',
                'is_active' => true,
                'sort_order' => 8,
            ],
        );

        return $this->poserTuile(
            ['module_key' => 'personnel'],
            [
                'name' => 'Personnel & paie',
                'slug' => 'personnel-paie',
                'description' => 'Dossiers du personnel, carrière, contrats et paie mensuelle du groupe.',
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

    /**
     * Pose la tuile d'un module sans defaire ce que l'administration a regle.
     *
     * Le deploiement rejoue ce seeder a chaque mise en ligne. Avec un
     * updateOrCreate, il remettait `is_active` a vrai : une tuile desactivee
     * depuis /admin/applications reapparaissait a la mise a jour suivante,
     * et le nom, la couleur ou l'ordre choisis repartaient avec.
     *
     * A la creation, tout est pose. Ensuite, seuls les traits structurels
     * sont maintenus — le type, la route interne, l'absence d'URL — parce
     * qu'ils decrivent ce qu'est le module, pas la facon de le presenter.
     *
     * @param  array<string, mixed>  $cle
     * @param  array<string, mixed>  $valeurs
     */
    private function poserTuile(array $cle, array $valeurs): Application
    {
        $tuile = Application::where($cle)->first();

        if ($tuile === null) {
            return Application::create($cle + $valeurs);
        }

        $tuile->fill(collect($valeurs)->only(['type', 'url'])->all())->save();

        return $tuile;
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
