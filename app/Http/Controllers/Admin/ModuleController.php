<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\DemandeBadge;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route as Routeur;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administration des modules servis par le portail.
 *
 * Un module declare dans config/modules.php n'existe qu'une fois pose comme
 * application de type « module ». Cet ecran reunit les deux : ce que le code
 * propose, ce qui est en service, qui y a acces, et par ou on l'administre.
 */
class ModuleController extends Controller
{
    public function index(): Response
    {
        $posees = Application::where('type', 'module')->withCount('users')->get()->keyBy('module_key');

        $modules = collect(config('modules'))->map(function (array $declaration, string $cle) use ($posees) {
            $application = $posees->get($cle);

            return [
                'cle' => $cle,
                'nom' => $declaration['name'],
                'description' => $declaration['description'],
                'icon' => $declaration['icon'] ?? 'grid',
                'color' => $declaration['color'] ?? '#64748b',
                'ouvertATous' => (bool) ($declaration['ouvert_a_tous'] ?? false),
                'rolesGestion' => $declaration['manage_roles'] ?? [],
                'lienAdmin' => $this->lien($declaration['admin_route'] ?? null),
                'lienModule' => $application && $application->is_active
                    ? $this->lien($declaration['route'] ?? null)
                    : null,

                // Ce qui existe reellement en base.
                'pose' => $application !== null,
                'id' => $application?->id,
                'slug' => $application?->slug,
                'actif' => (bool) $application?->is_active,
                'acces' => (int) ($application?->users_count ?? 0),
                'aTraiter' => $application ? $this->aTraiter($cle) : null,
            ];
        })->values()->all();

        return Inertia::render('admin/modules/index', [
            'modules' => $modules,
            // Un module pose sans declaration : le code a change, pas la base.
            'orphelins' => Application::where('type', 'module')
                ->whereNotIn('module_key', array_keys(config('modules')))
                ->orWhere(fn ($q) => $q->where('type', 'module')->whereNull('module_key'))
                ->get()
                ->map(fn (Application $a) => [
                    'id' => $a->id, 'name' => $a->name, 'slug' => $a->slug, 'moduleKey' => $a->module_key,
                ])->all(),
        ]);
    }

    /** Met le module en service, ou le retire, sans toucher a ses donnees. */
    public function toggle(Request $request, Application $application): RedirectResponse
    {
        abort_unless($application->isModule(), 404);

        $application->update(['is_active' => ! $application->is_active]);

        return back()->with('status', $application->is_active
            ? __('« :nom » est en service.', ['nom' => $application->name])
            : __('« :nom » est retiré du portail. Ses données sont conservées.', ['nom' => $application->name]));
    }

    /**
     * Donne la tuile a tous les comptes en service.
     *
     * Une tuile ne parait que si elle a ete attribuee. Pour les modules qui
     * concernent chacun — son profil, son badge, ses bulletins — il faut
     * pouvoir le faire d'un geste, et non compte par compte.
     */
    public function attribuerATous(Application $application): RedirectResponse
    {
        abort_unless($application->isModule(), 404);

        $deja = $application->users()->pluck('users.id')->all();

        $manquants = User::where('status', 'active')
            ->whereNotIn('id', $deja)
            ->pluck('id')->all();

        $application->users()->attach($manquants);

        if ($manquants === []) {
            return back()->with('status', __('« :nom » était déjà attribué à tout le personnel en service.', [
                'nom' => $application->name,
            ]));
        }

        return back()->with('status', trans_choice(
            '{1}« :nom » est désormais sur le tableau de bord d’un compte de plus.'
            .'|[2,*]« :nom » est désormais sur le tableau de bord de :nombre comptes de plus.',
            count($manquants),
            ['nom' => $application->name, 'nombre' => count($manquants)],
        ));
    }

    /** Une route nommee peut avoir disparu : on ne fabrique pas de lien mort. */
    private function lien(?string $nom): ?string
    {
        return $nom && Routeur::has($nom) ? route($nom) : null;
    }

    /**
     * Ce qui attend une main humaine dans le module, quand cela a un sens.
     *
     * @return array{nombre: int, libelle: string}|null
     */
    private function aTraiter(string $cle): ?array
    {
        if ($cle === 'badges') {
            $nombre = DemandeBadge::where('statut', 'en_attente')->count();

            return $nombre > 0
                ? ['nombre' => $nombre, 'libelle' => trans_choice(
                    '{1}une demande en attente|[2,*]:nombre demandes en attente', $nombre, ['nombre' => $nombre])]
                : null;
        }

        return null;
    }
}
