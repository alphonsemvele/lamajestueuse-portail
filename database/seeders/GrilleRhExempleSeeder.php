<?php

namespace Database\Seeders;

use App\Models\CategorieRh;
use App\Models\Echelon;
use App\Models\Indemnite;
use App\Models\ProfilSalaire;
use App\Models\Retenue;
use Illuminate\Database\Seeder;

/**
 * Grille salariale de demonstration, pour essayer le module sur un poste de
 * travail. Les montants sont des ordres de grandeur : la grille officielle se
 * saisit depuis « Référentiels » dans l'interface.
 *
 * Ce semeur ne tourne pas au deploiement.
 */
class GrilleRhExempleSeeder extends Seeder
{
    /**
     * Grille de depart, a ajuster depuis l'interface. Les montants sont des
     * ordres de grandeur, pas la grille officielle.
     */
    public function run(): void
    {
        if (CategorieRh::exists()) {
            return;
        }

        $grille = [
            'Enseignants' => [1 => 180000, 2 => 220000, 3 => 270000, 4 => 330000],
            'Personnel administratif' => [1 => 120000, 2 => 150000, 3 => 190000, 4 => 240000],
            'Personnel d’appui' => [1 => 75000, 2 => 90000, 3 => 110000],
            'Encadrement' => [1 => 400000, 2 => 500000, 3 => 650000],
        ];

        $echelons = [];

        foreach ($grille as $libelle => $niveaux) {
            $categorie = CategorieRh::create(['libelle' => $libelle]);

            foreach ($niveaux as $numero => $salaire) {
                $echelons[$libelle][$numero] = Echelon::create([
                    'categorie_rh_id' => $categorie->id,
                    'numero' => $numero,
                    'salaire' => $salaire,
                    'anciennete_min' => ($numero - 1) * 3,
                ]);
            }
        }

        $indemnites = [];

        foreach ([
            ['Logement', true],
            ['Transport', false],
            ['Responsabilité', true],
            ['Sujétion', true],
        ] as [$libelle, $imposable]) {
            $indemnites[$libelle] = Indemnite::create(['libelle' => $libelle, 'imposable' => $imposable]);
        }

        $retenues = [];

        foreach (['CNPS (part salariale)', 'Impôt sur le revenu', 'Avance sur salaire'] as $libelle) {
            $retenues[$libelle] = Retenue::create(['libelle' => $libelle]);
        }

        // Deux profils courants, pour montrer comment les pieces s'assemblent.
        $enseignant = ProfilSalaire::create([
            'nom' => 'Enseignant permanent',
            'description' => "Échelon 1 des enseignants, logement et transport.",
            'categorie_rh_id' => $echelons['Enseignants'][1]->categorie_rh_id,
            'echelon_id' => $echelons['Enseignants'][1]->id,
        ]);

        $enseignant->indemnites()->attach($indemnites['Logement']->id, ['type_calcul' => 'pourcentage', 'valeur' => 10]);
        $enseignant->indemnites()->attach($indemnites['Transport']->id, ['type_calcul' => 'fixe', 'valeur' => 25000]);
        $enseignant->retenues()->attach($retenues['CNPS (part salariale)']->id, ['type_calcul' => 'pourcentage', 'valeur' => 4.2]);

        $administratif = ProfilSalaire::create([
            'nom' => 'Agent administratif',
            'description' => "Échelon 1 du personnel administratif, transport.",
            'categorie_rh_id' => $echelons['Personnel administratif'][1]->categorie_rh_id,
            'echelon_id' => $echelons['Personnel administratif'][1]->id,
        ]);

        $administratif->indemnites()->attach($indemnites['Transport']->id, ['type_calcul' => 'fixe', 'valeur' => 20000]);
        $administratif->retenues()->attach($retenues['CNPS (part salariale)']->id, ['type_calcul' => 'pourcentage', 'valeur' => 4.2]);
    }
}
