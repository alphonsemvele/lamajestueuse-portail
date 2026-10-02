<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repare les numeros d'echelon restes au garage.
 *
 * En posant la grille officielle, le seeder pousse les echelons existants a
 * numero + 100 pour renumeroter sans se heurter a l'index unique
 * (categorie, numero). Ceux que la grille reprend redescendent aussitot ;
 * ceux qu'elle ne reprend pas — « A (temps partiel) », par exemple — y
 * restaient, et s'affichaient avec un numero a trois chiffres.
 *
 * On les ramene a la suite des echelons de leur categorie, dans l'ordre ou
 * ils s'y trouvent.
 */
return new class extends Migration
{
    private const GARAGE = 100;

    public function up(): void
    {
        $decales = DB::table('echelons')->where('numero', '>=', self::GARAGE)
            ->orderBy('categorie_rh_id')->orderBy('numero')
            ->get(['id', 'categorie_rh_id']);

        foreach ($decales->groupBy('categorie_rh_id') as $categorieId => $echelons) {
            // Le premier rang libre derriere ce que la categorie porte deja.
            $rang = (int) DB::table('echelons')
                ->where('categorie_rh_id', $categorieId)
                ->where('numero', '<', self::GARAGE)
                ->max('numero');

            foreach ($echelons as $echelon) {
                DB::table('echelons')->where('id', $echelon->id)->update(['numero' => ++$rang]);
            }
        }
    }

    /** Un numero range ne se degare pas : il n'y a rien a defaire. */
    public function down(): void {}
};
