<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La date de debut du contrat devient facultative.
 *
 * On enregistre souvent une embauche avant que la date exacte soit arretee,
 * ou en reprenant un ancien dossier dont elle s'est perdue. Sans elle, le
 * contrat produit quand meme ses bulletins : il est repute avoir toujours
 * couru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contrats', function (Blueprint $table) {
            $table->date('date_debut')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('contrats', function (Blueprint $table) {
            $table->date('date_debut')->nullable(false)->change();
        });
    }
};
