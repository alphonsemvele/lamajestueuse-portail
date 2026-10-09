<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Demande de badge : ce que l'employe veut voir imprime, et ou en est le
 * traitement.
 */
class DemandeBadge extends Model
{
    protected $table = 'demandes_badge';

    protected $fillable = [
        // « poste_affiche » n'est plus ni saisi ni imprime — une mutation
        // rendrait la carte fausse, et un badge se garde des annees. La
        // colonne demeure : les demandes deja deposees la portent.
        'numero', 'user_id', 'depose_par', 'application_id', 'nom_affiche', 'poste_affiche',
        'modele', 'motif', 'photo', 'photo_cadrage', 'commentaire', 'statut', 'motif_refus',
        'traite_par', 'traite_le',
    ];

    protected $casts = ['traite_le' => 'datetime', 'photo_cadrage' => 'array'];

    /** Pourquoi le badge est demande. */
    public const MOTIFS = [
        'premiere' => 'Première demande',
        'renouvellement' => 'Renouvellement',
        'perte' => 'Perte ou vol',
        'changement' => 'Changement de nom ou de fonction',
    ];

    /** Les etapes, dans l'ordre ou elles se suivent. */
    public const STATUTS = [
        'en_attente' => 'En attente',
        'approuvee' => 'Approuvée',
        'imprimee' => 'Imprimée',
        'remise' => 'Remise',
        'refusee' => 'Refusée',
    ];

    /** Une demande en cours occupe la place : on n'en ouvre pas deux. */
    public const EN_COURS = ['en_attente', 'approuvee', 'imprimee'];

    /**
     * Ce qui peut suivre chaque etat.
     *
     * Une demande refusee ou remise est close : les etapes ne la rouvrent
     * pas. Sans cette regle un refus se defaisait d'un clic, et la decision
     * ne voulait plus rien dire.
     *
     * Revenir sur un refus reste possible, mais par la porte nommee pour
     * cela — « rouvrir » — et non en remettant la demande a une etape
     * precedente comme si de rien n'etait.
     */
    public const SUITES = [
        'en_attente' => ['approuvee', 'refusee'],
        'approuvee' => ['imprimee', 'refusee'],
        'imprimee' => ['remise'],
        'remise' => [],
        'refusee' => [],
    ];

    /**
     * Le groupe, choisi en lieu et place d'un institut.
     *
     * « La Majestueuse » n'est pas une application du portail, et on n'en
     * cree pas une pour un logo : en base, le badge du groupe se reconnait a
     * l'absence d'institut. Ce mot est ce que le formulaire envoie.
     */
    public const LOGO_GROUPE = 'groupe';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** L'institut dont le logo figure sur le badge. */
    public function institut(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    public function traitePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traite_par');
    }

    /** Le guichet qui a depose la demande a la place de l'interesse. */
    public function deposePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'depose_par');
    }

    public function scopeEnCours(Builder $query): Builder
    {
        return $query->whereIn('statut', self::EN_COURS);
    }

    /** Photo du badge : celle qui a ete jointe, sinon celle du compte. */
    public function photoUrl(): ?string
    {
        if (filled($this->photo)) {
            return Storage::disk('public')->url($this->photo);
        }

        return $this->user?->avatarUrl();
    }

    /**
     * Le cadrage qui va avec cette photo.
     *
     * Celui de la demande prime : on peut recadrer la photo du compte pour
     * ce badge sans la remplacer, et sans toucher au profil. A defaut, on
     * suit le cadrage du compte, dont la photo est alors reprise telle quelle.
     */
    public function photoCadrage(): ?array
    {
        return $this->photo_cadrage ?? $this->user?->avatar_cadrage;
    }

    /** Le nom qui va avec le logo imprime : l'institut retenu, ou le groupe. */
    public function logoLibelle(): string
    {
        return $this->institut?->name ?? 'LA MAJESTUEUSE';
    }

    /** La demande peut-elle passer a cet etat depuis celui qu'elle occupe ? */
    public function peutPasserA(string $statut): bool
    {
        return in_array($statut, self::SUITES[$this->statut] ?? [], true);
    }

    public function estFigee(): bool
    {
        return in_array($this->statut, ['remise', 'refusee'], true);
    }

    /**
     * Prochain numero : BDG-000123, numerotation continue.
     */
    public static function prochainNumero(): string
    {
        $dernier = static::where('numero', 'like', 'BDG-%')
            ->orderByRaw('CAST(SUBSTRING(numero, 5) AS UNSIGNED) DESC')
            ->value('numero');

        $numero = $dernier ? ((int) substr($dernier, 4)) + 1 : 1;

        return 'BDG-'.str_pad((string) $numero, 6, '0', STR_PAD_LEFT);
    }

    public function toUiArray(): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'userId' => $this->user_id,
            'demandeur' => $this->user?->fullName(),
            'matricule' => $this->user?->matricule,
            'email' => $this->user?->email,
            'nomAffiche' => $this->nom_affiche,
            'posteAffiche' => $this->poste_affiche,
            'modele' => $this->modele,
            'motif' => $this->motif,
            'motifLibelle' => self::MOTIFS[$this->motif] ?? $this->motif,
            'photoUrl' => $this->photoUrl(),
            'photoCadrage' => $this->photoCadrage(),
            'commentaire' => $this->commentaire,
            'statut' => $this->statut,
            'statutLibelle' => self::STATUTS[$this->statut] ?? $this->statut,
            'motifRefus' => $this->motif_refus,
            'institut' => $this->institut ? [
                'id' => $this->institut->id,
                'name' => $this->institut->name,
                'color' => $this->institut->color,
                'logoUrl' => $this->institut->logoUrl(),
            ] : null,
            'deposePar' => $this->deposePar?->fullName(),
            'traitePar' => $this->traitePar?->fullName(),
            'traiteLe' => $this->traite_le?->format('d/m/Y'),
            'demandeLe' => $this->created_at?->format('d/m/Y'),
        ];
    }
}
