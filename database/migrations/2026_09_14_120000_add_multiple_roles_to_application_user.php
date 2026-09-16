<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un employé peut cumuler plusieurs rôles dans une application (ex.
     * enseignant et coordonnateur). role_in_app garde le rôle principal pour
     * les modules du portail qui ne lisent qu'un rôle.
     *
     * Les applications raccordées envoient leur catalogue de rôles : on
     * retient la date de la dernière synchronisation.
     */
    public function up(): void
    {
        Schema::table('application_user', function (Blueprint $table) {
            $table->json('roles')->nullable()->after('role_in_app');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->timestamp('roles_synchronises_le')->nullable()->after('roles');
        });

        DB::table('application_user')->whereNotNull('role_in_app')->orderBy('id')
            ->each(fn ($ligne) => DB::table('application_user')->where('id', $ligne->id)
                ->update(['roles' => json_encode([$ligne->role_in_app])]));
    }

    public function down(): void
    {
        Schema::table('application_user', function (Blueprint $table) {
            $table->dropColumn('roles');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('roles_synchronises_le');
        });
    }
};
