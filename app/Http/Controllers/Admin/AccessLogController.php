<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Le journal d'acces : qui est venu, quand, et ou il est alle.
 *
 * Deux lectures. Le detail, dans l'ordre du temps, pour retrouver un
 * passage precis ; et le compte par personne, pour savoir qui se sert
 * vraiment du portail — c'est celui-la qu'on emporte.
 */
class AccessLogController extends Controller
{
    /** Au-dela, une periode devient une archive : on la borne. */
    private const JOURS_MAX = 366;

    /** Les actions que le journal garde, et comment les nommer. */
    public const ACTIONS = [
        'login' => 'Connexion',
        'logout' => 'Déconnexion',
        'visite' => 'Page visitée',
        'open' => 'Ouverture d’application',
        'sso' => 'Ouverture d’application',
        'register' => 'Inscription',
    ];

    public function index(Request $request): Response
    {
        [$du, $au] = $this->periode($request);
        $action = (string) $request->query('action');
        $recherche = trim((string) $request->query('q'));

        $logs = $this->filtrer($du, $au, $action, $recherche)
            ->with(['user', 'application'])
            ->latest()
            ->paginate(30)
            ->withQueryString()
            ->through(fn (AccessLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'user' => $log->user?->nomOfficiel(),
                'application' => $log->application?->name,
                'cible' => $log->cible,
                'ip' => $log->ip_address,
                'date' => $log->created_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('admin/logs/index', [
            'logs' => $logs,
            'parUtilisateur' => $this->parUtilisateur($du, $au, $action, $recherche)->take(300)->values()->all(),
            'resume' => $this->resume($du, $au, $action, $recherche),
            // La vue voyage dans l'adresse : un lien montre ce qu'on voulait montrer.
            'filters' => [
                'action' => $action ?: null, 'du' => $du, 'au' => $au, 'q' => $recherche,
                'vue' => $request->query('vue') === 'utilisateurs' ? 'utilisateurs' : 'detail',
            ],
        ]);
    }

    /** Le journal emporté : le détail, ou le compte par personne. */
    public function exporter(Request $request): SymfonyResponse
    {
        [$du, $au] = $this->periode($request);
        $action = (string) $request->query('action');
        $recherche = trim((string) $request->query('q'));

        return $request->query('vue') === 'utilisateurs'
            ? $this->tableurParUtilisateur($du, $au, $action, $recherche)
            : $this->tableurDetaille($du, $au, $action, $recherche);
    }

    // ------------------------------------------------------------ la lecture

    /** @return array{0: string, 1: string} */
    private function periode(Request $request): array
    {
        $lire = function (string $champ) use ($request): ?string {
            $valeur = (string) $request->query($champ);

            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur) === 1 ? $valeur : null;
        };

        // Sans rien demander, on regarde le mois qui vient de s'ecouler.
        $au = $lire('au') ?? now()->toDateString();
        $du = $lire('du') ?? now()->subDays(29)->toDateString();

        if ($du > $au) {
            [$du, $au] = [$au, $du];
        }

        if (Carbon::parse($du)->diffInDays(Carbon::parse($au)) > self::JOURS_MAX) {
            $au = Carbon::parse($du)->addDays(self::JOURS_MAX)->toDateString();
        }

        return [$du, $au];
    }

    /** @return Builder<AccessLog> */
    private function filtrer(string $du, string $au, string $action, string $recherche): Builder
    {
        return AccessLog::query()
            ->whereBetween('created_at', [$du.' 00:00:00', $au.' 23:59:59'])
            ->when($action !== '', fn ($q) => $action === 'open'
                // « Ouverture » couvre les deux façons d'entrer dans une application.
                ? $q->whereIn('action', ['open', 'sso'])
                : $q->where('action', $action))
            ->when($recherche !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u
                ->where('name', 'like', "%{$recherche}%")
                ->orWhere('lastname', 'like', "%{$recherche}%")
                ->orWhere('matricule', 'like', "%{$recherche}%")));
    }

    /** @return array<string, int> */
    private function resume(string $du, string $au, string $action, string $recherche): array
    {
        $base = $this->filtrer($du, $au, $action, $recherche);

        return [
            'entrees' => (clone $base)->count(),
            'personnes' => (clone $base)->whereNotNull('user_id')->distinct()->count('user_id'),
            'connexions' => (clone $base)->where('action', 'login')->count(),
            'visites' => (clone $base)->where('action', 'visite')->count(),
        ];
    }

    /**
     * Une ligne par personne : ce qu'elle a fait sur la periode.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function parUtilisateur(string $du, string $au, string $action, string $recherche): Collection
    {
        $lignes = $this->filtrer($du, $au, $action, $recherche)
            ->whereNotNull('user_id')
            ->selectRaw('user_id, COUNT(*) as entrees')
            ->selectRaw("SUM(CASE WHEN action = 'login' THEN 1 ELSE 0 END) as connexions")
            ->selectRaw("SUM(CASE WHEN action = 'visite' THEN 1 ELSE 0 END) as visites")
            ->selectRaw("SUM(CASE WHEN action IN ('open', 'sso') THEN 1 ELSE 0 END) as ouvertures")
            ->selectRaw('MIN(created_at) as premier, MAX(created_at) as dernier')
            ->groupBy('user_id')
            ->orderByDesc('entrees')
            ->get();

        $gens = User::whereIn('id', $lignes->pluck('user_id'))->get()->keyBy('id');

        return $lignes->map(function ($ligne) use ($gens) {
            $personne = $gens->get($ligne->user_id);

            return [
                'userId' => (int) $ligne->user_id,
                'nom' => $personne?->nomOfficiel() ?: 'Compte supprimé',
                'matricule' => $personne?->matricule,
                'connexions' => (int) $ligne->connexions,
                'visites' => (int) $ligne->visites,
                'ouvertures' => (int) $ligne->ouvertures,
                'entrees' => (int) $ligne->entrees,
                'premier' => Carbon::parse($ligne->premier)->format('d/m/Y H:i'),
                'dernier' => Carbon::parse($ligne->dernier)->format('d/m/Y H:i'),
            ];
        });
    }

    // ------------------------------------------------------------ l'emporter

    private function tableurParUtilisateur(string $du, string $au, string $action, string $recherche): SymfonyResponse
    {
        $gens = $this->parUtilisateur($du, $au, $action, $recherche);

        return $this->fichier('journal-par-utilisateur-'.$du.'-au-'.$au.'.csv', function () use ($gens, $du, $au) {
            $sortie = fopen('php://output', 'w');

            fwrite($sortie, "\xEF\xBB\xBF");
            fputcsv($sortie, ['Période', $du.' → '.$au], ';');
            fputcsv($sortie, [], ';');
            fputcsv($sortie, [
                'Employé', 'Matricule', 'Connexions', 'Pages visitées',
                'Ouvertures d’application', 'Total', 'Première trace', 'Dernière trace',
            ], ';');

            foreach ($gens as $ligne) {
                fputcsv($sortie, [
                    $ligne['nom'],
                    $ligne['matricule'] ?: '—',
                    $ligne['connexions'],
                    $ligne['visites'],
                    $ligne['ouvertures'],
                    $ligne['entrees'],
                    $ligne['premier'],
                    $ligne['dernier'],
                ], ';');
            }

            fclose($sortie);
        });
    }

    private function tableurDetaille(string $du, string $au, string $action, string $recherche): SymfonyResponse
    {
        $journal = $this->filtrer($du, $au, $action, $recherche)->with(['user', 'application'])->oldest();

        return $this->fichier('journal-acces-'.$du.'-au-'.$au.'.csv', function () use ($journal) {
            $sortie = fopen('php://output', 'w');

            fwrite($sortie, "\xEF\xBB\xBF");
            fputcsv($sortie, ['Date', 'Heure', 'Employé', 'Matricule', 'Action', 'Page ou application', 'Adresse IP'], ';');

            // Par paquets : un mois de journal ne tient pas en mémoire d'un coup.
            $journal->chunk(500, function ($lot) use ($sortie) {
                foreach ($lot as $log) {
                    fputcsv($sortie, [
                        $log->created_at?->format('d/m/Y'),
                        $log->created_at?->format('H:i:s'),
                        $log->user?->nomOfficiel() ?: '—',
                        $log->user?->matricule ?: '—',
                        self::ACTIONS[$log->action] ?? $log->action,
                        $log->application?->name ?: ($log->cible ?: '—'),
                        $log->ip_address,
                    ], ';');
                }
            });

            fclose($sortie);
        });
    }

    private function fichier(string $nom, callable $ecrire): SymfonyResponse
    {
        return response()->streamDownload($ecrire, $nom, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
