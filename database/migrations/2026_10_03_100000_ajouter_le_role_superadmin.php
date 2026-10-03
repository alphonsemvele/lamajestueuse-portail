<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le role de super administrateur.
 *
 * Le tableau de bord de l'administration lui revient desormais a lui seul ;
 * un administrateur garde tout le reste, portail et modules compris.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['superadmin', 'admin', 'manager', 'employee'])
                ->default('employee')->change();
        });

        /*
         * Les administrateurs d'aujourd'hui ouvrent l'administration : c'est
         * leur role qui se dedouble, pas leur pouvoir qui se retire. Sans
         * cette reprise, la mise en service fermerait l'administration a tout
         * le monde — y compris a ceux qui pourraient la rouvrir.
         */
        DB::table('users')->where('role', 'admin')->update(['role' => 'superadmin']);
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'superadmin')->update(['role' => 'admin']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'manager', 'employee'])
                ->default('employee')->change();
        });
    }
};
