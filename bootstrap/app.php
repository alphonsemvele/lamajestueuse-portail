<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
            EnsureAccountIsActive::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * Jeton de session expire (419).
         *
         * Une page laissee ouverte assez longtemps perd son jeton. Laravel
         * repond alors une page d'erreur brute, et cote Inertia l'envoi
         * semble simplement ne rien faire : on charge, on revient, sans un
         * mot. On renvoie plutot l'utilisateur sur son formulaire, avec sa
         * saisie et une explication — seuls les mots de passe repartent,
         * qu'on ne remet jamais en session.
         */
        $exceptions->respond(function (SymfonyResponse $reponse, Throwable $erreur, Request $requete) {
            if ($reponse->getStatusCode() !== 419) {
                return $reponse;
            }

            return back()
                ->withInput($requete->except(['password', 'password_confirmation', '_token']))
                ->withErrors([
                    'session' => __('Votre page est restée ouverte trop longtemps et la session a expiré. Vérifiez votre saisie et renvoyez le formulaire.'),
                ]);
        });
    })->create();
