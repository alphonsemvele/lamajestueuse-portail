<?php

use App\Models\Application;
use Illuminate\Database\Migrations\Migration;

/**
 * Les logos de l'IFPM et du GSBM, sur fond transparent.
 *
 * Ils sont livres avec le projet (public/images/...) et non televerses : le
 * badge d'un institut doit porter sa marque des la mise en ligne, sans qu'on
 * ait a repasser par l'ecran d'administration. Un logo deja choisi par
 * l'administration n'est pas remplace.
 */
return new class extends Migration
{
    private const LOGOS = [
        'ifpm' => 'images/logo-ifpm.png',
        'gsbm' => 'images/logo-gsbm.png',
    ];

    public function up(): void
    {
        foreach (self::LOGOS as $slug => $chemin) {
            Application::where('slug', $slug)
                ->where(fn ($q) => $q->whereNull('logo')->orWhere('logo', ''))
                ->update(['logo' => $chemin]);
        }
    }

    public function down(): void
    {
        foreach (self::LOGOS as $slug => $chemin) {
            Application::where('slug', $slug)->where('logo', $chemin)->update(['logo' => null]);
        }
    }
};
