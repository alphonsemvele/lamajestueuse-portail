<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Le lien de redirection : c'est vers cette URL que le portail envoie
            // l'employe lorsqu'il ouvre l'application depuis son tableau de bord.
            $table->string('url');

            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['application', 'quick_link'])->default('application');
            $table->string('cover')->nullable();
            $table->string('icon', 40)->default('grid');
            $table->string('color', 20)->default('#2563eb');
            $table->boolean('is_active')->default(true);
            $table->boolean('opens_new_tab')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            // Reserve pour le branchement SSO (phase 2) : identifiant client OIDC.
            $table->string('client_id')->nullable()->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
