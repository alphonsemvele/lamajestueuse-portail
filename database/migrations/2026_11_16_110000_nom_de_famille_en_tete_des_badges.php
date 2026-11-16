<?php

use App\Models\DemandeBadge;
use Illuminate\Database\Migrations\Migration;

/**
 * Remet le nom de famille en tete sur les demandes de badge deja deposees.
 *
 * Le nom imprime etait prerempli dans l'ordre « prenom nom » ; un badge le
 * porte dans l'autre sens. Seules les demandes dont le nom n'a pas ete
 * retouche sont corrigees : celui que son titulaire a ecrit lui-meme reste
 * tel quel.
 */
return new class extends Migration
{
    public function up(): void
    {
        DemandeBadge::with('user')->chunkById(200, function ($demandes) {
            foreach ($demandes as $demande) {
                $personne = $demande->user;

                if (! $personne || trim((string) $personne->lastname) === '') {
                    continue;
                }

                if ($demande->nom_affiche === $personne->fullName()) {
                    $demande->update(['nom_affiche' => $personne->nomOfficiel()]);
                }
            }
        });
    }

    public function down(): void
    {
        // Le nom imprime appartient a sa demande : on ne le remet pas a l'envers.
    }
};
