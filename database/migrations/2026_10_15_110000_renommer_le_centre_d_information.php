<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La tuile prend le nom du module : LA MAJESTUEUSE Info.
 *
 * Le libelle affiche vient de la ligne « applications », pas de la
 * configuration : sans cela, le portail continuerait d'annoncer
 * « Centre d'information ». On ne renomme que si personne n'a deja choisi
 * autre chose.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('applications')
            ->where('module_key', 'informations')
            ->where('name', "Centre d'information")
            ->update([
                'name' => 'LA MAJESTUEUSE Info',
                'description' => 'La salle de presse du groupe : articles, images et documents, publiés sur le site public.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('applications')
            ->where('module_key', 'informations')
            ->where('name', 'LA MAJESTUEUSE Info')
            ->update(['name' => "Centre d'information", 'updated_at' => now()]);
    }
};
