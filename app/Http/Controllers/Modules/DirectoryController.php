<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Annuaire du personnel : module interne du portail.
 *
 * Recherche par nom, matricule, e-mail ou telephone, affinable par institut et
 * par poste. Seules des informations de contact professionnelles sont
 * exposees, et seuls les comptes actifs apparaissent.
 */
class DirectoryController extends Controller
{
    private const KEY = 'annuaire';

    /** Champs de tri autorises, pour ne jamais injecter une colonne libre. */
    private const TRIS = [
        'nom' => 'lastname',
        'matricule' => 'matricule',
        'entite' => 'entite',
        'poste' => 'poste',
    ];

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $recherche = trim((string) $request->query('q'));
        $institut = $request->query('institut');
        $poste = trim((string) $request->query('poste'));
        $tri = array_key_exists((string) $request->query('tri'), self::TRIS) ? $request->query('tri') : 'nom';
        $ordre = $request->query('ordre') === 'desc' ? 'desc' : 'asc';

        $filtre = $recherche !== '' || $institut || $poste !== '';

        $personnel = User::query()
            ->with(['applications' => fn ($q) => $q->where('applications.type', 'application')])
            ->where('status', 'active')
            ->when($recherche !== '', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$recherche}%")
                ->orWhere('lastname', 'like', "%{$recherche}%")
                ->orWhere('matricule', 'like', "%{$recherche}%")
                ->orWhere('email', 'like', "%{$recherche}%")
                ->orWhere('phone', 'like', "%{$recherche}%")))
            ->when($poste !== '', fn ($q) => $q->where('poste', 'like', "%{$poste}%"))
            ->when($institut, fn ($q, $slug) => $q->whereHas(
                'applications',
                fn ($sub) => $sub->where('applications.slug', $slug)
            ))
            ->orderBy(self::TRIS[$tri], $ordre)
            ->orderBy('name', $ordre);

        return Inertia::render('modules/annuaire/index', [
            // Tant qu'aucun critere n'est pose, on reste sur l'ecran d'accueil.
            'personnel' => $filtre
                ? $personnel->paginate(24)->withQueryString()->through(fn (User $u) => $u->toDirectoryArray())
                : null,
            'filters' => [
                'q' => $recherche,
                'institut' => $institut,
                'poste' => $poste,
                'tri' => $tri,
                'ordre' => $ordre,
            ],
            'instituts' => Application::active()->where('type', 'application')
                ->orderBy('name')->get()
                ->map(fn ($a) => ['slug' => $a->slug, 'name' => $a->name, 'color' => $a->color])
                ->all(),
            // Pour l'autocompletion du champ poste.
            'postes' => User::where('status', 'active')->whereNotNull('poste')
                ->distinct()->orderBy('poste')->pluck('poste')->all(),
            'total' => User::where('status', 'active')->count(),
        ]);
    }

    private function authorizeAccess(Request $request): void
    {
        $module = Application::active()->where('module_key', self::KEY)->first();

        abort_if($module === null, 404);

        abort_unless(
            $request->user()->isAdmin()
                || $request->user()->applications()->where('applications.id', $module->id)->exists(),
            403,
            __("Vous n'avez pas accès à cette application.")
        );
    }
}
