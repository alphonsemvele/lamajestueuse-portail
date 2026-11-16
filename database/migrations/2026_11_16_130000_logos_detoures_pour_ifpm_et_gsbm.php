<?php

use App\Models\Application;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Les logos detoures remplacent ceux qui portaient un fond.
 *
 * La migration precedente respectait le logo choisi dans l'administration ;
 * c'est justement celui-la qu'il fallait changer — il arrivait avec un fond
 * blanc, visible sur le badge comme un rectangle autour de la marque. On
 * impose donc les fichiers detoures livres avec le projet.
 *
 * L'ancien fichier reste sur le disque : seule la reference change, et
 * l'administration peut televerser autre chose par-dessus.
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
            $application = Application::where('slug', $slug)->first();

            if (! $application || $application->logo === $chemin) {
                continue;
            }

            Log::info("Logo de {$slug} : « {$application->logo} » devient « {$chemin} ».");

            $application->update(['logo' => $chemin]);
        }
    }

    public function down(): void
    {
        // L'ancien logo n'est pas retabli : on ne sait plus lequel c'etait.
    }
};
