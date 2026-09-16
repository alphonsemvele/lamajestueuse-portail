<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'self_registered', 'approved_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'approved_at' => 'datetime',
            'self_registered' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function applications(): BelongsToMany
    {
        return $this->belongsToMany(Application::class)
            ->withPivot(['role_in_app', 'roles', 'poste', 'reference_locale', 'is_pinned', 'opens_count', 'last_opened_at'])
            ->withTimestamps();
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
