<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le logo porte par l'entite qui paie.
 *
 * Le bulletin le prend en premier : c'est l'employeur qui edite la fiche de
 * paie, pas l'institut auquel la personne est rattachee dans le portail. A
 * defaut, on retombe sur le logo de l'institut lie, puis sur celui du module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employeurs', function (Blueprint $table) {
            $table->string('logo')->nullable()->after('sigle');
        });
    }

    public function down(): void
    {
        Schema::table('employeurs', function (Blueprint $table) {
            $table->dropColumn('logo');
        });
    }
};
