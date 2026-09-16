<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('sexe', ['M', 'F'])->nullable()->after('lastname');
            // Trace l'origine du compte : saisi par l'administration ou
            // demande par l'employe lui-meme via le formulaire public.
            $table->boolean('self_registered')->default(false)->after('status');
            $table->timestamp('approved_at')->nullable()->after('self_registered');
        });

        Schema::table('application_user', function (Blueprint $table) {
            // Le poste occupe DANS cet institut : une meme personne peut etre
            // enseignante a l'IFPM et comptable a NDAZOA.
            $table->string('poste')->nullable()->after('role_in_app');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['sexe', 'self_registered', 'approved_at']);
        });

        Schema::table('application_user', function (Blueprint $table) {
            $table->dropColumn('poste');
        });
    }
};
