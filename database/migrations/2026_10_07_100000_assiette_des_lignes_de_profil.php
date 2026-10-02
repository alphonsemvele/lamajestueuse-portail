<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'assiette d'une ligne de profil.
 *
 * Un pourcentage portait toujours sur le salaire de base. Il peut desormais
 * porter sur une autre ligne du meme profil : une retenue calculee sur une
 * indemnite, une indemnite calculee sur une autre.
 *
 * La colonne garde une reference de la forme « indemnite:12 » ou
 * « retenue:5 ». Vide, c'est le salaire de base, comme avant : les lignes
 * existantes gardent donc exactement leur calcul.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['profil_indemnite', 'profil_retenue'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('base_calcul', 40)->nullable()->after('valeur');
            });
        }
    }

    public function down(): void
    {
        foreach (['profil_indemnite', 'profil_retenue'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('base_calcul');
            });
        }
    }
};
