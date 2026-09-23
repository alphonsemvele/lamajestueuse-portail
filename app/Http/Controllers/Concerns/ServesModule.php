<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Agent;
use App\Models\Application;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Regles d'acces communes aux modules servis par le portail : la tuile ouvre
 * la consultation, les roles declares dans config/modules.php ouvrent
 * l'ecriture, et un administrateur du portail passe partout.
 */
trait ServesModule
{
    protected function module(): ?Application
    {
        return Application::active()->where('module_key', static::MODULE)->first();
    }

    protected function autoriserAcces(User $utilisateur): Application
    {
        $module = $this->module();

        abort_if($module === null, 404);

        abort_unless(
            $utilisateur->isAdmin()
                || $utilisateur->applications()->where('applications.id', $module->id)->exists(),
            403,
            __("Vous n'avez pas accès à cette application.")
        );

        return $module;
    }

    protected function autoriserGestion(User $utilisateur): Application
    {
        $module = $this->autoriserAcces($utilisateur);

        abort_unless(
            $module->allowsManagementBy($utilisateur),
            403,
            __("Vous n'avez pas le droit de modifier ces données.")
        );

        return $module;
    }

    protected function peutGerer(User $utilisateur): bool
    {
        return (bool) $this->module()?->allowsManagementBy($utilisateur);
    }

    /**
     * Entites que l'utilisateur a le droit de suivre, ou null quand il n'a
     * aucune limite (administrateur du portail).
     *
     * @return array<int, int>|null
     */
    protected function perimetre(Request $request): ?array
    {
        return $request->user()->perimetreRh();
    }

    /** Refuse une entite qui n'est pas dans le perimetre de l'utilisateur. */
    protected function verifierEntite(Request $request, ?int $employeurId): void
    {
        $perimetre = $this->perimetre($request);

        abort_if(
            $perimetre !== null && ($employeurId === null || ! in_array($employeurId, $perimetre, true)),
            403,
            __("Cette entité n'est pas dans votre périmètre.")
        );
    }

    /** Refuse un dossier qui releve d'entites que l'utilisateur ne suit pas. */
    protected function verifierAgent(Request $request, Agent $agent): void
    {
        abort_unless(
            $agent->dansLePerimetre($this->perimetre($request)),
            403,
            __("Ce dossier ne relève pas de votre périmètre.")
        );
    }
}
