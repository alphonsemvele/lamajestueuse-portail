<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),

            'appName' => config('app.name'),

            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'lastname' => $user->lastname,
                    'fullName' => $user->fullName(),
                    'initials' => $user->initials(),
                    'avatarUrl' => $user->avatarUrl(),
                    'email' => $user->email,
                    'poste' => $user->poste,
                    'entite' => $user->entite,
                    'role' => $user->role,
                    'isAdmin' => $user->isAdmin(),
                ] : null,
            ],

            'locale' => App::getLocale(),

            // Les traductions sont chargees cote client pour que le meme
            // dictionnaire JSON serve au serveur et a l'interface React.
            'translations' => fn () => $this->translations(),

            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'registered' => fn () => $request->session()->get('registered'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function translations(): array
    {
        $locale = App::getLocale();
        $path = lang_path("{$locale}.json");

        if ($locale === config('app.fallback_locale') || ! File::exists($path)) {
            return [];
        }

        return json_decode(File::get($path), true) ?: [];
    }
}
