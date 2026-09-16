<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'description', 'url', 'category_id', 'type',
        'module_key', 'cover', 'logo', 'icon', 'color', 'is_active', 'opens_new_tab',
        'sort_order', 'client_id', 'client_secret', 'roles',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'opens_new_tab' => 'boolean',
            // Chiffre au repos : un vidage de la base ne livre pas le secret.
            'client_secret' => 'encrypted',
            'roles' => 'array',
            'roles_synchronises_le' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role_in_app', 'roles', 'poste', 'reference_locale', 'is_pinned', 'opens_count', 'last_opened_at'])
            ->withTimestamps();
    }

    /**
     * Catalogue des roles declares par l'application. Il est saisi a la main
     * (simples codes) ou envoye par l'application elle-meme (code, libelle,
     * description) lors d'une synchronisation.
     *
     * @return array<int, array{code: string, libelle: string, description: string|null}>
     */
    public function roleCatalogue(): array
    {
        return collect($this->roles ?? [])
            ->map(fn ($role) => is_array($role)
                ? ['code' => (string) ($role['code'] ?? ''), 'libelle' => (string) ($role['libelle'] ?? $role['code'] ?? ''), 'description' => $role['description'] ?? null]
                : ['code' => (string) $role, 'libelle' => (string) $role, 'description' => null])
            ->filter(fn (array $role) => $role['code'] !== '')
            ->unique('code')
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function roleCodes(): array
    {
        return array_column($this->roleCatalogue(), 'code');
    }

    /**
     * Range des roles dans l'ordre du catalogue : le premier est le role
     * principal, celui que l'application retient quand elle n'en garde qu'un.
     *
     * @param  iterable<string>  $roles
     * @return array<int, string>
     */
    public function sortRoles(iterable $roles): array
    {
        $ordre = array_flip($this->roleCodes());

        return collect($roles)->unique()
            ->sortBy(fn ($role) => $ordre[$role] ?? PHP_INT_MAX)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $roles
     * @return array<int, string>
     */
    public function roleLabels(array $roles): array
    {
        $libelles = array_column($this->roleCatalogue(), 'libelle', 'code');

        return array_map(fn ($role) => $libelles[$role] ?? $role, $roles);
    }

    /**
     * Roles attribues a un employe, lus sur la ligne d'acces (pivot).
     *
     * @return array<int, string>
     */
    public static function pivotRoles(?object $pivot): array
    {
        if (! $pivot) {
            return [];
        }

        $roles = $pivot->roles ?? null;

        if (is_string($roles)) {
            $roles = json_decode($roles, true);
        }

        $roles = array_values(array_filter((array) $roles, fn ($role) => is_string($role) && $role !== ''));

        return $roles ?: (filled($pivot->role_in_app ?? null) ? [$pivot->role_in_app] : []);
    }

    /**
     * Colonnes du pivot pour une liste de roles deja ordonnee.
     *
     * @param  array<int, string>  $roles
     * @return array{roles: string|null, role_in_app: string|null}
     */
    public static function rolesAttributes(array $roles): array
    {
        $roles = array_values($roles);

        return [
            'roles' => $roles ? json_encode($roles) : null,
            'role_in_app' => $roles[0] ?? null,
        ];
    }

    /**
     * Saisie d'un formulaire : une liste de roles, ou un role seul.
     *
     * @return array<int, mixed>
     */
    public static function normalizeRoleInput(mixed $valeur): array
    {
        return collect(is_array($valeur) ? $valeur : [$valeur])
            ->map(fn ($role) => is_string($role) ? trim($role) : $role)
            ->filter(fn ($role) => filled($role))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * L'application est-elle raccordee au portail ? Si oui, l'ouverture lui
     * transmet une identite signee au lieu d'un simple lien.
     */
    public function usesPortalSignOn(): bool
    {
        return ! $this->isModule()
            && filled($this->client_id)
            && filled($this->client_secret);
    }

    public function isModule(): bool
    {
        return $this->type === 'module';
    }

    /**
     * Definition du module dans config/modules.php, si l'application en est un.
     *
     * @return array<string, mixed>|null
     */
    public function module(): ?array
    {
        return $this->isModule() ? config("modules.{$this->module_key}") : null;
    }

    /**
     * Adresse d'ouverture : route interne pour un module, URL declaree sinon.
     */
    public function destination(): ?string
    {
        if ($this->isModule()) {
            $route = $this->module()['route'] ?? null;

            return $route ? route($route) : null;
        }

        return $this->url;
    }

    /**
     * L'employe peut-il administrer le contenu de ce module ?
     */
    public function allowsManagementBy(User $user): bool
    {
        if (! $this->isModule()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $pivot = $this->users()->where('users.id', $user->id)->first()?->pivot;

        return (bool) array_intersect(self::pivotRoles($pivot), $this->module()['manage_roles'] ?? []);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function coverUrl(): ?string
    {
        return $this->mediaUrl($this->cover);
    }

    public function logoUrl(): ?string
    {
        return $this->mediaUrl($this->logo);
    }

    /**
     * Trois provenances possibles : une URL externe, un visuel livre avec le
     * projet (public/images/...) ou un fichier televerse par l'administrateur
     * (disque public).
     */
    protected function mediaUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, 'images/')) {
            return asset($path);
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * Representation transmise a l'interface React. On expose uniquement ce
     * qui est affiche, jamais l'entite Eloquent complete.
     *
     * @return array<string, mixed>
     */
    public function toUiArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'url' => $this->url,
            'destination' => $this->destination(),
            'host' => $this->host(),
            'moduleKey' => $this->module_key,
            'type' => $this->type,
            'icon' => $this->icon,
            'color' => $this->color,
            'cover' => $this->cover,
            'logo' => $this->logo,
            'coverUrl' => $this->coverUrl(),
            'logoUrl' => $this->logoUrl(),
            'isActive' => (bool) $this->is_active,
            'opensNewTab' => (bool) $this->opens_new_tab,
            'sortOrder' => $this->sort_order,
            'clientId' => $this->client_id,
            'hasClientSecret' => filled($this->client_secret),
            'usesPortalSignOn' => $this->usesPortalSignOn(),
            'roles' => $this->roleCodes(),
            'roleCatalogue' => $this->roleCatalogue(),
            'rolesSyncedAt' => $this->roles_synchronises_le?->format('d/m/Y H:i'),
            'categoryId' => $this->category_id,
            'category' => $this->relationLoaded('category') && $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
                'color' => $this->category->color,
            ] : null,
            'usersCount' => $this->users_count ?? null,
            'pivot' => $this->pivot ? [
                'roleInApp' => $this->pivot->role_in_app,
                'roles' => self::pivotRoles($this->pivot),
                'poste' => $this->pivot->poste,
            ] : null,
        ];
    }

    /**
     * Domaine affiche sous le lien, pour que l'admin verifie d'un coup d'oeil
     * ou pointe la redirection.
     */
    public function host(): string
    {
        if ($this->isModule()) {
            return __('Module du portail');
        }

        return parse_url((string) $this->url, PHP_URL_HOST) ?: (string) $this->url;
    }
}
