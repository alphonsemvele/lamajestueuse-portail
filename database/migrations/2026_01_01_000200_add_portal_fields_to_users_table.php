<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('lastname')->nullable()->after('name');
            $table->string('matricule')->nullable()->unique()->after('lastname');
            $table->string('phone')->nullable()->after('email');
            $table->string('poste')->nullable()->after('phone');
            $table->string('entite')->nullable()->after('poste');
            $table->string('avatar')->nullable()->after('entite');
            $table->enum('role', ['admin', 'manager', 'employee'])->default('employee')->after('avatar');
            $table->enum('status', ['active', 'suspended', 'pending'])->default('active')->after('role');
            $table->string('locale', 5)->default('fr')->after('status');
            $table->timestamp('last_login_at')->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'lastname', 'matricule', 'phone', 'poste', 'entite',
                'avatar', 'role', 'status', 'locale', 'last_login_at',
            ]);
        });
    }
};
