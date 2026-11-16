<?php

use App\Models\Application;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Le logo de l'IUM, detoure.
 *
 * Celui qui servait jusqu'ici etait un JPEG : le damier gris et blanc qui
 * marque la transparence y avait ete aplati, et le badge le montrait comme
 * un rectangle gris autour de la marque. On impose le fichier detoure livre
 * avec le projet, comme pour l'IFPM et le GSBM.
 *
 * L'ancien fichier reste sur le disque : seule la reference change, et
 * l'administration peut televerser autre chose par-dessus.
 */
return new class extends Migration
{
    private const SLUG = 'ium';

    private const LOGO = 'images/logo-ium.png';

    public function up(): void
    {
        $application = Application::where('slug', self::SLUG)->first();

        if (! $application || $application->logo === self::LOGO) {
            return;
        }

        Log::info('Logo de l’IUM : « '.$application->logo.' » devient « '.self::LOGO.' ».');

        $application->update(['logo' => self::LOGO]);
    }

    public function down(): void
    {
        // L'ancien logo n'est pas retabli : on ne sait plus lequel c'etait.
    }
};
