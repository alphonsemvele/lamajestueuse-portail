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

        $possibles = $this->employeursPossibles();

        return count($possibles) === 1 ? $possibles[0] : null;
    }

    /**
     * Les employeurs vers lesquels ses instituts pointent.
     *
     * @return list<Employeur>
     */
    public function employeursPossibles(): array
    {
        $instituts = $this->relationLoaded('applications')
            ? $this->applications->where('type', 'application')->pluck('id')
            : $this->applications()->where('applications.type', 'application')->pluck('applications.id');

        return Employeur::whereIn('application_id', $instituts)->orderBy('sigle')->get()->all();
    }

    /**
     * La RH doit-elle trancher ? Plusieurs employeurs possibles, et aucun
     * choix pose.
     */
    public function rattachementATrancher(): bool
    {
        return $this->employeur === null && count($this->employeursPossibles()) > 1;
    }

    public function scopeDuPerimetreRh(Builder $query, ?array $employeurs): Builder
    {
        if ($employeurs === null) {
            return $query;
        }

        $applications = Employeur::whereIn('id', $employeurs)
            ->whereNotNull('application_id')->pluck('application_id')->all();

        return $query->where(fn ($sub) => $sub
            // Le choix de la RH prime : rattachee ici, la personne y reste,
            // quels que soient ses instituts.
            ->whereIn('employeur_id', $employeurs)
            ->orWhereHas('agent.contrats', fn ($c) => $c->whereIn('employeur_id', $employeurs))
            // A defaut de choix — ou quand celui-ci ne mene plus nulle part —
            // c'est l'institut rattache qui designe l'entite.
            ->orWhere(fn ($ni) => $ni
                ->where(fn ($sans) => $sans
                    ->whereNull('employeur_id')
                    ->orWhereNotIn('employeur_id', Employeur::select('id')))
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
