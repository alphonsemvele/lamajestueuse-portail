<?php

use App\Models\DemandeBadge;
use Illuminate\Database\Migrations\Migration;

/**
 * Remet le nom de famille en tete sur les demandes deja deposees, y compris
 * celles ou il avait ete saisi a la main dans l'autre sens.
 *
 * La premiere passe ne corrigeait que le nom prerempli, au caractere pres.
 * Celle-ci reconnait les memes mots dans un autre ordre, et la casse qui
 * change — « claire nkoa » comme « Claire NKOA ». Un nom qui dit vraiment
 * autre chose qu'un etat civil n'est pas touche : c'est la regle que suit
 * desormais chaque depot.
 */
return new class extends Migration
{
    public function up(): void
    {
        DemandeBadge::with('user')->chunkById(200, function ($demandes) {
            foreach ($demandes as $demande) {
                $remis = $demande->user?->nomOfficielSi($demande->nom_affiche);

                if ($remis !== null && $remis !== '' && $remis !== $demande->nom_affiche) {
                    $demande->update(['nom_affiche' => $remis]);
                }
            }
        });
    }

    public function down(): void
    {
        // Le nom imprime appartient a sa demande : on ne le remet pas a l'envers.
    }
};
