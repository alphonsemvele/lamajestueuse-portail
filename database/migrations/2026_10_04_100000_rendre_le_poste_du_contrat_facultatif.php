<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le poste n'est plus exige a la signature du contrat : il arrive souvent
 * qu'on enregistre l'embauche avant que l'intitule exact soit arrete. Quand
 * la RH laisse le champ vide, le poste declare a l'inscription prend le
 * relais ; s'il n'y en a pas, le contrat reste sans poste.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contrats', function (Blueprint $table) {
            $table->string('poste')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('contrats', function (Blueprint $table) {
            $table->string('poste')->nullable(false)->change();
        });
    }
};
