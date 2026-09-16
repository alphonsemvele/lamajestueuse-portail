<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un module est une application rendue PAR le portail lui-meme : il
     * apparait comme une tuile ordinaire, mais l'ouverture mene a une route
     * interne au lieu d'un site exterieur.
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->enum('type', ['application', 'quick_link', 'module'])->default('application')->change();
            $table->string('module_key', 60)->nullable()->after('type');
            $table->string('url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->enum('type', ['application', 'quick_link'])->default('application')->change();
            $table->dropColumn('module_key');
            $table->string('url')->nullable(false)->change();
        });
    }
};
