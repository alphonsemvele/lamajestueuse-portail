<?php

use App\Http\Controllers\Api\ApplicationRosterController;
use App\Http\Controllers\Api\ApplicationSyncController;
use Illuminate\Support\Facades\Route;

/*
 * Echanges entre applications, sans session : chaque requete porte un jeton
 * signe avec le secret partage de l'application emettrice.
 */
Route::post('/applications/synchronisation', ApplicationSyncController::class)
    ->middleware('throttle:20,1')
    ->name('api.applications.sync');

/*
 * Rattrapage : l'application vient chercher son effectif, celui que le
 * portail lui diffuse deja a chaque changement d'acces.
 */
Route::get('/applications/effectif', ApplicationRosterController::class)
    ->middleware('throttle:20,1')
    ->name('api.applications.effectif');
