<?php

use App\Http\Controllers\Admin\AccessLogController;
use App\Http\Controllers\Admin\ApplicationController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\PostController as AdminPostController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ApplicationLaunchController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Modules\DirectoryController;
use App\Http\Controllers\Modules\InformationController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

Route::get('/locale/{locale}', LocaleController::class)->name('locale.switch');

/*
 * L'unique page de connexion de tout l'ecosysteme La Majestueuse.
 * Aucune application metier n'expose de formulaire de login.
 */
Route::middleware('guest')->group(function () {
    Route::get('/connexion', [LoginController::class, 'show'])->name('login');
    Route::post('/connexion', [LoginController::class, 'store'])->middleware('throttle:20,1');

    // Inscription du personnel : cree un compte EN ATTENTE de validation.
    Route::get('/inscription', [RegistrationController::class, 'show'])->name('register');
    Route::post('/inscription', [RegistrationController::class, 'store'])->middleware('throttle:6,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/deconnexion', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/applications/{application}/ouvrir', ApplicationLaunchController::class)->name('applications.open');
    Route::post('/pointage', [CheckInController::class, 'store'])->name('checkin.store');
    Route::get('/actualites/{post}', [PostController::class, 'show'])->name('posts.show');

    /*
     * Annuaire du personnel : module servi par le portail.
     */
    Route::get('/annuaire', [DirectoryController::class, 'index'])->name('annuaire.index');

    /*
     * Centre d'information : module servi par le portail lui-meme. Il apparait
     * comme une tuile ordinaire mais ne sort pas vers un site exterieur.
     */
    Route::prefix('informations')->name('informations.')->group(function () {
        Route::get('/', [InformationController::class, 'index'])->name('index');
        Route::get('/nouvelle', [InformationController::class, 'create'])->name('create');
        Route::post('/', [InformationController::class, 'store'])->name('store');
        Route::get('/{post}/modifier', [InformationController::class, 'edit'])->name('edit');
        Route::put('/{post}', [InformationController::class, 'update'])->name('update');
        Route::delete('/{post}', [InformationController::class, 'destroy'])->name('destroy');
        Route::post('/{post}/visibilite', [InformationController::class, 'toggleVisibility'])->name('visibility');
    });
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('applications/{application}/acces', [ApplicationController::class, 'access'])
        ->name('applications.access');
    Route::put('applications/{application}/acces', [ApplicationController::class, 'updateAccess'])
        ->name('applications.access.update');
    Route::post('applications/{application}/synchroniser', [ApplicationController::class, 'synchronize'])
        ->name('applications.sync');
    Route::resource('applications', ApplicationController::class)->except('show');

    Route::post('users/{user}/valider', [UserController::class, 'approve'])->name('users.approve');
    Route::post('users/{user}/refuser', [UserController::class, 'reject'])->name('users.reject');
    Route::resource('users', UserController::class)->except('show');
    Route::post('posts/{post}/visibilite', [AdminPostController::class, 'toggleVisibility'])->name('posts.visibility');
    Route::resource('posts', AdminPostController::class)->except('show');

    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('journal', [AccessLogController::class, 'index'])->name('logs.index');
});
