<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identifiant de l'employe DANS l'application ciblee.
     *
     * Les matricules du portail et ceux des applications metier ne coincident
     * pas : sans cette correspondance, chaque premiere connexion creerait un
     * compte parallele au lieu de retrouver l'existant. La table de liaison est
     * le bon endroit pour la porter — une personne peut avoir une reference
     * differente dans chaque application.
     */
    public function up(): void
    {
        Schema::table('application_user', function (Blueprint $table) {
            $table->string('reference_locale')->nullable()->after('poste');
        });
    }

    public function down(): void
    {
        Schema::table('application_user', function (Blueprint $table) {
            $table->dropColumn('reference_locale');
        });
    }
};
