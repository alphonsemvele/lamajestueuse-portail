<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Concerns\ServesModule;
use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tutoriels du portail : chaque module explique pas a pas.
 *
 * Le catalogue vit dans config/tutoriels.php : ajouter un tutoriel ne
 * demande aucun code. Tous commencent par les memes prealables — s'inscrire,
 * attendre la validation, trouver le module — parce que sans compte ni
 * acces, le reste ne sert a rien.
 *
 * Les pages sont publiques, pour cette raison meme : qui n'a pas encore de
 * compte doit pouvoir lire comment en obtenir un. La tuile du portail reste
 * la pour ceux qui sont connectes.
 */
class TutorielController extends Controller
{
    use ServesModule;

    public const MODULE = 'tutoriels';

    public function index(): Response
    {
        $this->exigerLeModuleEnService();

        return Inertia::render('modules/tutoriels/index', [
            'prealables' => $this->prealables(),
            'tutoriels' => collect(config('tutoriels.tutoriels', []))
                ->map(fn (array $tutoriel) => [
                    'cle' => $tutoriel['cle'],
                    'titre' => $tutoriel['titre'],
                    'resume' => $tutoriel['resume'],
                    'icone' => $tutoriel['icone'] ?? 'book',
                    'duree' => $tutoriel['duree'] ?? null,
                    'etapes' => count($tutoriel['etapes'] ?? []),
                ])
                ->values()->all(),
        ]);
    }

    public function show(string $cle): Response
    {
        $this->exigerLeModuleEnService();

        $tutoriel = collect(config('tutoriels.tutoriels', []))->firstWhere('cle', $cle);

        abort_if($tutoriel === null, 404);

        return Inertia::render('modules/tutoriels/show', [
            'tutoriel' => [
                'cle' => $tutoriel['cle'],
                'titre' => $tutoriel['titre'],
                'resume' => $tutoriel['resume'],
                'duree' => $tutoriel['duree'] ?? null,
                'etapes' => $this->etapes($tutoriel['etapes'] ?? []),
            ],
            'prealables' => $this->prealables(),
            // Le module concerne, pour proposer d'y aller une fois lu.
            'module' => $this->moduleDuTutoriel($tutoriel['module'] ?? null),
        ]);
    }

    /**
     * Les pages sont publiques, mais le module reste un module : retire du
     * portail, il ne repond plus — sinon « retirer » ne voudrait rien dire.
     */
    private function exigerLeModuleEnService(): void
    {
        abort_if($this->module() === null, 404);
    }

    /** @return array<string, mixed> */
    private function prealables(): array
    {
        $prealables = config('tutoriels.prealables', []);

        return [
            'titre' => $prealables['titre'] ?? 'Avant de commencer',
            'resume' => $prealables['resume'] ?? null,
            'etapes' => $this->etapes($prealables['etapes'] ?? []),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $etapes
     * @return array<int, array<string, mixed>>
     */
    private function etapes(array $etapes): array
    {
        return collect($etapes)
            ->map(fn (array $etape) => [
                'titre' => $etape['titre'],
                'texte' => $etape['texte'],
                'points' => $etape['points'] ?? [],
                'capture' => $this->capture($etape['capture'] ?? null),
            ])
            ->values()->all();
    }

    /**
     * Une capture annoncee mais absente du dossier public n'est pas
     * transmise : la page ne montre jamais d'image cassee.
     */
    private function capture(?string $chemin): ?string
    {
        if (blank($chemin) || ! file_exists(public_path($chemin))) {
            return null;
        }

        return asset($chemin);
    }

    /** @return array<string, mixed>|null */
    private function moduleDuTutoriel(?string $cle): ?array
    {
        if (blank($cle)) {
            return null;
        }

        $module = Application::active()->where('module_key', $cle)->first();
        $route = config("modules.{$cle}.route");

        if ($module === null || blank($route) || ! Route::has($route)) {
            return null;
        }

        return [
            'nom' => $module->name,
            'url' => route($route),
        ];
    }
}
