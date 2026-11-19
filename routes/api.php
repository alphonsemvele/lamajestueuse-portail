<?php

use App\Http\Controllers\Api\ApplicationRosterController;
use App\Http\Controllers\Api\ApplicationSyncController;
use App\Http\Controllers\Api\PointagesController;
use App\Http\Controllers\Api\RelaisLecteurController;
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

/*
 * Le relais du lecteur de presence : il vient chercher les ordres deposes
 * par le portail, puis rapporte ce qu'il a fait. Le portail n'atteint pas
 * le lecteur — c'est le relais, sur place, qui sort le chercher.
 */
Route::get('/lecteur/taches', [RelaisLecteurController::class, 'prendre'])
    ->middleware('throttle:120,1')
    ->name('api.lecteur.taches');

Route::post('/lecteur/taches/{tache}', [RelaisLecteurController::class, 'rendre'])
    ->middleware('throttle:120,1')
    ->name('api.lecteur.compte-rendu');

/*
 * Les pointages. Le lecteur pousse ici lui-meme, des qu'un passage a lieu :
 * il ne sait porter qu'un jeton dans l'adresse, d'ou cette forme. Le relais,
 * lui, rattrape ce qui a manque et signe comme pour le reste.
 */
Route::post('/lecteur/pointage/{jeton}', [PointagesController::class, 'recevoir'])
    ->middleware('throttle:600,1')
    ->name('api.lecteur.pointage');

Route::post('/lecteur/pointages', [PointagesController::class, 'rattraper'])
    ->middleware('throttle:60,1')
    ->name('api.lecteur.pointages');
