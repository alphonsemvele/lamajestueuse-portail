<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Secret partage avec l'application, servant a signer le jeton de
     * connexion. Stocke chiffre (cast 'encrypted' sur le modele) : un vidage
     * de la base ne le livre pas en clair.
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->text('client_secret')->nullable()->after('client_id');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('client_secret');
        });
    }
};
