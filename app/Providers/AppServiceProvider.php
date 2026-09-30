<?php

namespace App\Providers;

use App\Models\ReglageEmail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Les reglages d'envoi saisis dans l'administration remplacent ceux
        // du serveur. Sans reglages, ou avant les migrations, rien ne change.
        ReglageEmail::appliquer();
    }
}
