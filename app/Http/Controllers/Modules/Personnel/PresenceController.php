<?php

namespace App\Http\Controllers\Modules\Personnel;

use App\Http\Controllers\Concerns\ServesModule;
use App\Http\Controllers\Controller;
use App\Models\Pointage;
use App\Models\RelaisLecteur;
use App\Services\BulletinPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * La presence : ce que le lecteur annonce, a mesure qu'il l'annonce.
 *
 * L'ecran montre une journee ou une periode, se rafraichit tout seul, et se
 * laisse emporter — en PDF pour un registre a signer ou a classer, en
 * tableur pour tout ce qu'on voudra en faire ensuite.
 */
class PresenceController extends Controller
{
    use ServesModule;

    public const MODULE = 'personnel';

    /** Au-dela, une periode devient une archive : on la borne. */
    private const JOURS_MAX = 366;

    public function index(Request $request): Response
    {
        $this->autoriserAcces($request->user());

        [$du, $au] = $this->periode($request);
        $recherche = trim((string) $request->query('q'));

        $pointages = $this->filtrer($du, $au, $recherche)
            ->with('user')
            ->orderByDesc('moment')
            ->paginate(40)
            ->withQueryString()
            ->through(fn (Pointage $pointage) => [
                'id' => $pointage->id,
                'numero' => $pointage->numero,
                'nom' => $pointage->nomAffiche(),
                'matricule' => $pointage->user?->matricule,
                'heure' => $pointage->moment?->format('H:i:s'),
                'jour' => $pointage->moment?->format('d/m/Y'),
                'sens' => $pointage->sens,
                'moyenLibelle' => Pointage::MOYENS[$pointage->moyen] ?? $pointage->moyen,
                'connu' => $pointage->user_id !== null,
            ]);

        return Inertia::render('modules/personnel/presence', [
            'pointages' => $pointages,
            'filtres' => ['du' => $du, 'au' => $au, 'q' => $recherche],
            'resume' => $this->resume($du, $au, $recherche),
            'parPersonne' => $this->parPersonne($du, $au, $recherche)->take(200)->values()->all(),
            'lecteur' => $this->etatDuLecteur(),
            'peutGerer' => $this->peutGerer($request->user()),
        ]);
    }

    /** La meme periode, emportee : registre en PDF, ou tableur. */
    public function exporter(Request $request, BulletinPdf $pdf): SymfonyResponse
    {
        $this->autoriserAcces($request->user());

        [$du, $au] = $this->periode($request);
        $recherche = trim((string) $request->query('q'));

        return $request->query('format') === 'pdf'
            ? $this->registrePdf($du, $au, $recherche, $pdf)
            : $this->tableur($du, $au, $recherche);
    }

    // ------------------------------------------------------------ la lecture

    /**
     * La periode regardee : celle demandee, ou la journee en cours.
     *
     * Les bornes se remettent dans l'ordre si on les inverse, et l'etendue
     * est bornee : demander dix ans de registre n'aiderait personne.
     *
     * @return array{0: string, 1: string}
     */
    private function periode(Request $request): array
    {
        $lire = function (string $champ) use ($request): ?string {
            $valeur = (string) $request->query($champ);

            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur) === 1 ? $valeur : null;
        };

        $du = $lire('du') ?? $lire('jour') ?? now()->toDateString();
        $au = $lire('au') ?? $du;

        if ($du > $au) {
            [$du, $au] = [$au, $du];
        }

        if (Carbon::parse($du)->diffInDays(Carbon::parse($au)) > self::JOURS_MAX) {
            $au = Carbon::parse($du)->addDays(self::JOURS_MAX)->toDateString();
        }

        return [$du, $au];
    }

    /** @return Builder<Pointage> */
    private function filtrer(string $du, string $au, string $recherche): Builder
    {
        return Pointage::query()
            ->whereBetween('moment', [$du.' 00:00:00', $au.' 23:59:59'])
            ->when($recherche !== '', fn ($q) => $q->where(fn ($sous) => $sous
                ->where('numero', 'like', "%{$recherche}%")
                ->orWhere('nom', 'like', "%{$recherche}%")
                ->orWhereHas('user', fn ($u) => $u
                    ->where('name', 'like', "%{$recherche}%")
                    ->orWhere('lastname', 'like', "%{$recherche}%")
                    ->orWhere('matricule', 'like', "%{$recherche}%"))));
    }

    /** @return array<string, mixed> */
    private function resume(string $du, string $au, string $recherche): array
    {
        $base = $this->filtrer($du, $au, $recherche);

        return [
            'passages' => (clone $base)->count(),
            'personnes' => (clone $base)->distinct()->count('numero'),
            'jours' => (clone $base)->distinct()->count(\Illuminate\Support\Facades\DB::raw('date(moment)')),
            'premier' => (clone $base)->min('moment'),
            'dernier' => (clone $base)->max('moment'),
            'inconnus' => (clone $base)->whereNull('user_id')->distinct()->count('numero'),
        ];
    }

    /**
     * Le registre par personne : ce que l'on signe et ce que l'on classe.
     *
     * Une ligne par personne, avec le nombre de jours ou elle s'est
     * presentee, sa premiere et sa derniere marque sur la periode.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function parPersonne(string $du, string $au, string $recherche): Collection
    {
        return $this->filtrer($du, $au, $recherche)
            ->selectRaw('numero, MAX(nom) as nom, MAX(user_id) as user_id, COUNT(*) as passages')
            ->selectRaw('MIN(moment) as premier, MAX(moment) as dernier')
            ->selectRaw('COUNT(DISTINCT date(moment)) as jours')
            ->groupBy('numero')
            ->orderByRaw('MAX(nom)')
            ->get()
            ->map(fn ($ligne) => [
                'numero' => $ligne->numero,
                'nom' => $ligne->nom ?: $ligne->numero,
                'jours' => (int) $ligne->jours,
                'passages' => (int) $ligne->passages,
                'premier' => Carbon::parse($ligne->premier)->format('d/m/Y H:i'),
                'dernier' => Carbon::parse($ligne->dernier)->format('d/m/Y H:i'),
                'connu' => $ligne->user_id !== null,
            ]);
    }

    /** @return array{vu: ?string, present: bool, dernierPointage: ?string} */
    private function etatDuLecteur(): array
    {
        $relais = RelaisLecteur::first();
        $dernier = Pointage::max('moment');

        return [
            'vu' => $relais?->derniere_visite?->diffForHumans(),
            'present' => (bool) $relais?->estPresent(),
            'dernierPointage' => $dernier ? Carbon::parse($dernier)->diffForHumans() : null,
        ];
    }

    // ------------------------------------------------------------ l'emporter

    /** Le registre, mis en page pour le papier. */
    private function registrePdf(string $du, string $au, string $recherche, BulletinPdf $pdf): SymfonyResponse
    {
        $unSeulJour = $du === $au;

        $donnees = [
            'du' => Carbon::parse($du)->translatedFormat('j F Y'),
            'au' => Carbon::parse($au)->translatedFormat('j F Y'),
            'unSeulJour' => $unSeulJour,
            'resume' => $this->resume($du, $au, $recherche),
            'personnes' => $this->parPersonne($du, $au, $recherche),
            // Le detail n'a de sens que sur une journee : sur un mois, il
            // ferait trente pages que personne ne lirait.
            'passages' => $unSeulJour
                ? $this->filtrer($du, $au, $recherche)->with('user')->orderBy('moment')->get()
                : collect(),
            'recherche' => $recherche,
            'editeLe' => now()->translatedFormat('j F Y à H:i'),
            'couleur' => '#0f766e',
            'logo' => $pdf->logoDuGroupe(),
        ];

        $document = Pdf::setOptions($pdf->optionsDocument())
            ->loadView('pdf.presence', $donnees)
            ->setPaper('a4');

        return $document->download('presence-'.$du.($unSeulJour ? '' : '-au-'.$au).'.pdf');
    }

    /**
     * La periode en tableur : une ligne par passage, brut.
     *
     * Le point-virgule et le marqueur d'UTF-8 sont ceux du fichier du
     * personnel : Excel les reconnait sans qu'on ait rien a regler.
     */
    private function tableur(string $du, string $au, string $recherche): SymfonyResponse
    {
        $passages = $this->filtrer($du, $au, $recherche)->with('user')->orderBy('moment');
        $nom = 'presence-'.$du.($du === $au ? '' : '-au-'.$au).'.csv';

        return response()->streamDownload(function () use ($passages) {
            $sortie = fopen('php://output', 'w');

            fwrite($sortie, "\xEF\xBB\xBF");
            fputcsv($sortie, ['Date', 'Heure', 'Matricule', 'Nom', 'Sens', 'Moyen'], ';');

            // Par paquets : un mois de pointages ne tient pas en memoire d'un coup.
            $passages->chunk(500, function ($lot) use ($sortie) {
                foreach ($lot as $pointage) {
                    fputcsv($sortie, [
                        $pointage->moment?->format('d/m/Y'),
                        $pointage->moment?->format('H:i:s'),
                        $pointage->user?->matricule ?: $pointage->numero,
                        $pointage->nomAffiche(),
                        ['entree' => 'Entrée', 'sortie' => 'Sortie'][$pointage->sens] ?? '—',
                        Pointage::MOYENS[$pointage->moyen] ?? $pointage->moyen,
                    ], ';');
                }
            });

            fclose($sortie);
        }, $nom, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
