<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roles acceptes par l'application.
     *
     * Chaque employe tient un role DANS chaque application, et ce role lui est
     * transmis a l'ouverture. Les declarer ici evite la saisie libre : on
     * propose une liste au lieu d'un champ texte ou une faute de frappe
     * enverrait un role que l'application ne reconnait pas.
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->json('roles')->nullable()->after('client_secret');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('roles');
        });
    }
};
