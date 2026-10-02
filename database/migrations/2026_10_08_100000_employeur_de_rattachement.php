<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'employeur de rattachement, choisi par le service du personnel.
 *
 * Rattache a un seul institut, l'employeur se deduit tout seul : la colonne
 * reste vide et rien n'est a saisir. Rattache a deux, la deduction serait
 * un coup de des : c'est la RH qui tranche, et son choix s'inscrit ici.
 *
 * Elle peut aussi l'inscrire pour quelqu'un qui n'a qu'un institut, afin de
 * le rattacher ailleurs. Le choix pose l'emporte toujours sur la deduction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('employeur_id')->nullable()->after('entite')
                ->constrained('employeurs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employeur_id');
        });
    }
};
