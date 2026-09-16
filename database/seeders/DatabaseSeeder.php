<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $categories = collect([
            ['name' => 'Formation', 'color' => '#1d4ed8', 'sort_order' => 1],
            ['name' => 'Santé', 'color' => '#0d9488', 'sort_order' => 2],
            ['name' => 'Ressources humaines', 'color' => '#b45309', 'sort_order' => 3],
            ['name' => 'Information', 'color' => '#7c3aed', 'sort_order' => 4],
        ])->mapWithKeys(function (array $data) {
            $category = Category::create($data + ['slug' => Str::slug($data['name'])]);

            return [$category->slug => $category];
        });

        $applications = [
            [
                'name' => 'IFPM',
                'description' => "Institut de Formation Professionnelle. Étudiants, cours, notes, bulletins et préinscriptions.",
                'url' => 'https://ifpm.lamajestueuse.cm',
                'category_id' => $categories['formation']->id,
                'cover' => 'images/apps/ifpm.jpg',
                'icon' => 'academic',
                'color' => '#1d4ed8',
                'client_id' => 'ifpm',
                'sort_order' => 1,
            ],
            [
                'name' => 'GSBM',
                'description' => "Groupe Scolaire Bilingue. Élèves, classes, présences et bulletins.",
                'url' => 'https://gsbm.lamajestueuse.cm',
                'category_id' => $categories['formation']->id,
                'cover' => 'images/apps/gsbm.jpg',
                'icon' => 'school',
                'color' => '#0891b2',
                'client_id' => 'gsbm',
                'sort_order' => 2,
            ],
            [
                'name' => 'ERP NDAZOA',
                'description' => "Gestion administrative de l'institut : personnel, profils de salaire, indemnités et bulletins de paie.",
                'url' => 'https://ndazoa.lamajestueuse.cm',
                'category_id' => $categories['ressources-humaines']->id,
                'cover' => 'images/apps/ndazoa.jpg',
                'icon' => 'briefcase',
                'color' => '#b45309',
                'client_id' => 'ndazoa',
                'sort_order' => 3,
            ],
            [
                'name' => 'Fondation Médicale',
                'description' => "Système d'information hospitalier et hospitalisation à domicile : patients, consultations, prescriptions, stocks.",
                'url' => 'https://fondation.lamajestueuse.cm',
                'category_id' => $categories['sante']->id,
                'cover' => 'images/apps/fondation.jpg',
                'icon' => 'heart',
                'color' => '#0d9488',
                'client_id' => 'fondation-medicale',
                'sort_order' => 4,
            ],
            [
                'name' => 'Annuaire',
                'description' => "Retrouver n'importe quel collaborateur du groupe.",
                'url' => 'https://annuaire.lamajestueuse.cm',
                'category_id' => $categories['information']->id,
                'cover' => 'images/apps/annuaire.jpg',
                'icon' => 'users',
                'color' => '#7c3aed',
                'client_id' => 'annuaire',
                'sort_order' => 5,
            ],
            [
                'name' => 'Formulaires',
                'description' => "Tous les formulaires internes du groupe.",
                'url' => 'https://formulaires.lamajestueuse.cm',
                'category_id' => $categories['information']->id,
                'cover' => 'images/apps/formulaires.jpg',
                'icon' => 'document',
                'color' => '#4f46e5',
                'client_id' => 'formulaires',
                'sort_order' => 6,
            ],
        ];

        foreach ($applications as $data) {
            Application::create($data + ['slug' => Str::slug($data['name']), 'type' => 'application']);
        }

        $quickLinks = [
            [
                'name' => 'Webmail',
                'description' => 'Accéder à votre messagerie professionnelle.',
                'url' => 'https://mail.lamajestueuse.cm',
                'icon' => 'mail',
                'color' => '#2563eb',
                'sort_order' => 1,
            ],
            [
                'name' => 'Site institutionnel',
                'description' => 'Le site public du groupe La Majestueuse.',
                'url' => 'https://www.lamajestueuse.cm',
                'icon' => 'globe',
                'color' => '#0d9488',
                'sort_order' => 2,
            ],
        ];

        foreach ($quickLinks as $data) {
            Application::create($data + [
                'slug' => Str::slug($data['name']),
                'type' => 'quick_link',
                'opens_new_tab' => true,
            ]);
        }

        $admin = User::create([
            'name' => 'Alphonse',
            'lastname' => 'MVELE',
            'matricule' => 'LM-0001',
            'email' => 'alphonsemvele95@gmail.com',
            'phone' => '+237 6 99 00 00 01',
            'poste' => 'Administrateur du portail',
            'entite' => "Direction des systèmes d'information",
            'role' => 'admin',
            'status' => 'active',
            'password' => 'Majestueuse@2026',
        ]);

        $employees = [
            [
                'name' => 'Elvin', 'lastname' => 'YONDOUA', 'matricule' => 'LM-0002',
                'email' => 'elvin.yondoua@lamajestueuse.cm', 'poste' => 'Coordonnateur pédagogique',
                'entite' => 'IFPM', 'apps' => ['ifpm' => 'coordonnateur', 'annuaire' => null, 'formulaires' => null],
            ],
            [
                'name' => 'Sandrine', 'lastname' => 'ABENA', 'matricule' => 'LM-0003',
                'email' => 'sandrine.abena@lamajestueuse.cm', 'poste' => 'Responsable RH',
                'entite' => 'Direction des ressources humaines',
                'apps' => ['erp-ndazoa' => 'admin', 'annuaire' => null, 'formulaires' => null],
            ],
            [
                'name' => 'Joseph', 'lastname' => 'NKOLO', 'matricule' => 'LM-0004',
                'email' => 'joseph.nkolo@lamajestueuse.cm', 'poste' => 'Médecin coordonnateur',
                'entite' => 'Fondation Médicale',
                'apps' => ['fondation-medicale' => 'medecin', 'annuaire' => null],
            ],
        ];

        $bySlug = Application::pluck('id', 'slug');
        $allSlugs = $bySlug->keys()->all();

        // L'administrateur voit tout.
        $admin->applications()->sync($bySlug->values()->mapWithKeys(
            fn ($id) => [$id => ['role_in_app' => 'admin']]
        )->all());

        foreach ($employees as $data) {
            $apps = $data['apps'];
            unset($data['apps']);

            $user = User::create($data + [
                'role' => 'employee',
                'status' => 'active',
                'password' => 'Majestueuse@2026',
            ]);

            $sync = [];
            foreach ($apps as $slug => $roleInApp) {
                if ($bySlug->has($slug)) {
                    $sync[$bySlug[$slug]] = ['role_in_app' => $roleInApp];
                }
            }

            // Les liens rapides sont partages par tout le monde.
            foreach (['webmail', 'site-institutionnel'] as $slug) {
                if ($bySlug->has($slug)) {
                    $sync[$bySlug[$slug]] = ['role_in_app' => null];
                }
            }

            $user->applications()->sync($sync);
        }

        $posts = [
            [
                'title' => "Rentrée académique 2026-2027 : ouverture des préinscriptions",
                'excerpt' => "Les préinscriptions en ligne sont ouvertes pour l'ensemble des filières de l'IFPM et du GSBM.",
                'type' => 'news', 'image' => 'images/news/n2.jpg', 'is_featured' => true,
            ],
            [
                'title' => "La Fondation Médicale ouvre son service d'hospitalisation à domicile",
                'excerpt' => "Le module HAD est désormais actif : tournées, visites, plans de soins et signatures électroniques.",
                'type' => 'news', 'image' => 'images/news/n1.jpg',
            ],
            [
                'title' => "Portail unique : une seule connexion pour toutes vos applications",
                'excerpt' => "À compter de cette semaine, vos applications métier sont accessibles depuis ce portail avec un seul mot de passe.",
                'type' => 'announcement', 'image' => 'images/news/n3.jpg', 'is_featured' => true,
            ],
            [
                'title' => "Maintenance planifiée de l'ERP NDAZOA — samedi 20h",
                'excerpt' => "L'application sera indisponible pendant environ deux heures pour une mise à jour du module de paie.",
                'type' => 'announcement',
            ],
            [
                'title' => "Appel à candidature interne : chef de service pédiatrie",
                'excerpt' => "Les candidatures sont reçues jusqu'au 30 du mois courant auprès de la direction des ressources humaines.",
                'type' => 'billboard',
            ],
        ];

        foreach ($posts as $index => $data) {
            Post::create($data + [
                'slug' => Str::slug($data['title']),
                'body' => $data['excerpt'],
                'published_at' => now()->subDays($index),
                'views' => random_int(3, 140),
                'author_id' => $admin->id,
            ]);
        }
    }
}
