<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'lastname', 'sexe', 'matricule', 'email', 'phone', 'poste', 'entite',
        'avatar', 'role', 'status', 'locale', 'password', 'last_login_at',
        'self_registered', 'approved_at', 'dans_le_personnel', 'employeur_id',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'approved_at' => 'datetime',
            'self_registered' => 'boolean',
            'dans_le_personnel' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function applications(): BelongsToMany
    {
        return $this->belongsToMany(Application::class)
            ->withPivot(['role_in_app', 'roles', 'poste', 'reference_locale', 'is_pinned', 'opens_count', 'last_opened_at'])
            ->withTimestamps();
    }

    /**
     * Employeurs dont cet utilisateur gere le personnel. L'administrateur du
     * portail les designe depuis l'ecran d'acces du module.
     */
    public function employeursRh(): BelongsToMany
    {
        return $this->belongsToMany(Employeur::class)->withTimestamps();
    }

    /**
     * Le personnel du groupe : ceux dont le service RH tient le dossier.
     *
     * Un administrateur technique ou un compte de service entre dans le
     * portail sans figurer dans les effectifs.
     */
    public function scopeDuPersonnel(Builder $query): Builder
    {
        return $query->where('dans_le_personnel', true);
    }

    /**
     * Personnel relevant d'un perimetre RH. Une personne en fait partie par
     * son contrat, ou — tant qu'elle n'en a pas encore — par l'institut
     * auquel le portail la rattache.
     *
     * @param  array<int, int>|null  $employeurs  null : aucune limite
     */
    /** L'employeur choisi par la RH, quand elle en a designe un. */
    public function employeur(): BelongsTo
    {
        return $this->belongsTo(Employeur::class);
    }

    /**
     * L'employeur dont cette personne releve.
     *
     * Le choix de la RH l'emporte. A defaut, il se deduit — mais seulement
     * quand la deduction est sure : un seul institut rattache, donc un seul
     * employeur possible. Rattache a deux, la personne attend que la RH
     * tranche, et cette methode rend null.
     */
    public function employeurDeRattachement(): ?Employeur
    {
        // Un choix qui ne mene plus nulle part vaut absence de choix : sans
        // cle etrangere pour l'empecher, une entite peut disparaitre sous le
        // rattachement, et la personne ne doit pas disparaitre avec elle.
        if ($this->employeur_id && $this->employeur) {
            return $this->employeur;
        }

        // Le contrat passe avant l'institut : signer avec une entite, c'est
        // en relever, quel que soit l'institut du portail auquel on est
        // rattache. Deux contrats, aucune evidence : la RH tranche.
        $parContrat = $this->employeursParContrat();

        if ($parContrat !== []) {
            return count($parContrat) === 1 ? $parContrat[0] : null;
        }

        $parInstitut = $this->employeursParInstitut();

        return count($parInstitut) === 1 ? $parInstitut[0] : null;
    }

    /**
     * Les employeurs avec lesquels elle a un contrat en cours.
     *
     * @return list<Employeur>
     */
    public function employeursParContrat(): array
    {
        $ids = $this->agent?->contrats()->where('statut', 'actif')
            ->distinct()->pluck('employeur_id') ?? collect();

        return $ids->isEmpty()
            ? []
            : Employeur::whereIn('id', $ids)->orderBy('sigle')->get()->all();
    }

    /**
     * Les employeurs vers lesquels ses instituts pointent.
     *
     * @return list<Employeur>
     */
    public function employeursParInstitut(): array
    {
        $instituts = $this->relationLoaded('applications')
            ? $this->applications->where('type', 'application')->pluck('id')
            : $this->applications()->where('applications.type', 'application')->pluck('applications.id');

        return $instituts->isEmpty()
            ? []
            : Employeur::whereIn('application_id', $instituts)->orderBy('sigle')->get()->all();
    }

    /**
     * La RH doit-elle trancher ? Rien ne se deduit, alors que plusieurs
     * pistes existent.
     */
    public function rattachementATrancher(): bool
    {
        if ($this->employeur_id && $this->employeur) {
            return false;
        }

        return $this->employeurDeRattachement() === null
            && (count($this->employeursParContrat()) > 1 || count($this->employeursParInstitut()) > 1);
    }

    public function scopeDuPerimetreRh(Builder $query, ?array $employeurs): Builder
    {
        if ($employeurs === null) {
            return $query;
        }

        $applications = Employeur::whereIn('id', $employeurs)
            ->whereNotNull('application_id')->pluck('application_id')->all();

        // Sans choix pose, ou quand il ne mene plus nulle part.
        $sansChoix = fn ($q) => $q->where(fn ($sub) => $sub
            ->whereNull('employeur_id')
            ->orWhereNotIn('employeur_id', Employeur::select('id')));

        return $query->where(fn ($sub) => $sub
            // 1. Le choix de la RH prime : rattachee ici, la personne y reste.
            ->whereIn('employeur_id', $employeurs)
            // 2. A defaut, le contrat en cours.
            ->orWhere(fn ($q) => $sansChoix($q)
                ->whereHas('agent.contrats', fn ($c) => $c
                    ->where('statut', 'actif')->whereIn('employeur_id', $employeurs)))
            // 3. A defaut de tout, l'institut du portail.
            ->orWhere(fn ($q) => $sansChoix($q)
                ->whereDoesntHave('agent.contrats', fn ($c) => $c->where('statut', 'actif'))
                ->whereHas('applications', fn ($a) => $a->whereIn('applications.id', $applications))));
    }

    /** Cette personne releve-t-elle du perimetre donne ? */
    public function releveDuPerimetreRh(?array $employeurs): bool
    {
        if ($employeurs === null) {
            return true;
        }

        return static::whereKey($this->id)->duPerimetreRh($employeurs)->exists();
    }

    /**
     * Perimetre RH : la liste des employeurs visibles, ou null quand il n'y a
     * aucune limite (administrateur du portail).
     *
     * @return array<int, int>|null
     */
    public function perimetreRh(): ?array
    {
        if ($this->isAdmin()) {
            return null;
        }

        return $this->employeursRh()->pluck('employeurs.id')->map(fn ($id) => (int) $id)->all();
    }

    /** Dossier du module Personnel & paie, quand il a ete ouvert. */
    public function agent(): HasOne
    {
        return $this->hasOne(Agent::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(AccessLog::class);
    }

    /**
     * Photo de profil : fichier televerse, visuel du depot ou URL externe.
     */
    public function avatarUrl(): ?string
    {
        if (blank($this->avatar)) {
            return null;
        }

        if (str_starts_with($this->avatar, 'http://') || str_starts_with($this->avatar, 'https://')) {
            return $this->avatar;
        }

        if (str_starts_with($this->avatar, 'images/')) {
            return asset($this->avatar);
        }

        return Storage::disk('public')->url($this->avatar);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Valide une demande d'inscription : le compte devient utilisable avec les
     * instituts qu'il a demandes.
     */
    public function approve(): void
    {
        $this->forceFill(['status' => 'active', 'approved_at' => now()])->save();
    }

    public function fullName(): string
    {
        return trim($this->name.' '.$this->lastname);
    }

    public function initials(): string
    {
        return Str::upper(Str::substr($this->name, 0, 1).Str::substr($this->lastname ?? '', 0, 1));
    }

    /**
     * @return array<string, mixed>
     */
    public function toUiArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'lastname' => $this->lastname,
            'fullName' => $this->fullName(),
            'initials' => $this->initials(),
            'avatarUrl' => $this->avatarUrl(),
            'sexe' => $this->sexe,
            'matricule' => $this->matricule,
            'email' => $this->email,
            'phone' => $this->phone,
            'poste' => $this->poste,
            'entite' => $this->entite,
            'role' => $this->role,
            'status' => $this->status,
            'locale' => $this->locale,
            'selfRegistered' => (bool) $this->self_registered,
            'dansLePersonnel' => (bool) $this->dans_le_personnel,
            'inscritLe' => $this->created_at?->format('d/m/Y'),
            'inscritLeIso' => $this->created_at?->toDateString(),
            'applicationsCount' => $this->applications_count ?? null,
            'pivot' => $this->pivot ? [
                'roleInApp' => $this->pivot->role_in_app,
                'roles' => Application::pivotRoles($this->pivot),
                'poste' => $this->pivot->poste,
            ] : null,
            // Instituts accessibles et roles tenus dans chacun (liste du personnel).
            'acces' => $this->relationLoaded('applications')
                ? $this->applications->map(fn (Application $application) => [
                    'slug' => $application->slug,
                    'name' => $application->name,
                    'color' => $application->color,
                    'roles' => $application->roleLabels(Application::pivotRoles($application->pivot)),
                ])->values()->all()
                : null,
        ];
    }

    /**
     * Fiche d'annuaire : uniquement des informations de contact
     * professionnelles. Ni mot de passe, ni role dans le portail, ni
     * journal d'activite, ni acces applicatifs detailles.
     *
     * @return array<string, mixed>
     */
    public function toDirectoryArray(): array
    {
        return [
            'id' => $this->id,
            'fullName' => $this->fullName(),
            'initials' => $this->initials(),
            'avatarUrl' => $this->avatarUrl(),
            'poste' => $this->poste,
            'entite' => $this->entite,
            'matricule' => $this->matricule,
            'email' => $this->email,
            'phone' => $this->phone,
            'sexe' => $this->sexe,
            'instituts' => $this->relationLoaded('applications')
                ? $this->applications
                    ->where('type', 'application')
                    ->map(fn ($app) => [
                        'name' => $app->name,
                        'color' => $app->color,
                        'poste' => $app->pivot->poste,
                    ])
                    ->values()
                    ->all()
                : [],
        ];
    }

    /**
     * Les applications metier auxquelles l'employe a acces, ordonnees.
     */
    public function visibleApplications(string $type = 'application')
    {
        return $this->applications()
            ->where('applications.is_active', true)
            ->where('applications.type', $type)
            ->orderBy('applications.sort_order')
            ->orderBy('applications.name')
            ->get();
    }
}
