<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Date de la derniere diffusion reussie de l'effectif vers l'application.
 * Elle sert a l'ecran d'administration : on voit d'un coup d'oeil si une
 * application travaille sur une liste de personnel a jour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->timestamp('effectif_diffuse_le')->nullable()->after('roles_synchronises_le');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('effectif_diffuse_le');
        });
    }
};
