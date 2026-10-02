<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Bulletin;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Le bulletin de paie en PDF.
 *
 * Un seul endroit le fabrique, pour que le service du personnel et le
 * salarie aient sous les yeux rigoureusement le meme document : meme
 * en-tete, meme logo, meme detail. Seule la mention de bas de page change,
 * selon qui regarde.
 */
class BulletinPdf
{
    /** La tuile dont le logo sert de dernier recours. */
    private const MODULE = 'bulletins';

    private const COULEUR = '#0f766e';

    /**
     * Rend le bulletin, a l'ecran ou en telechargement.
     *
     * `$apercu` sert le document en ligne, pour la previsualisation ; sinon
     * le navigateur propose de l'enregistrer.
     */
    public function reponse(Bulletin $bulletin, bool $apercu = false, ?string $mention = null): Response
    {
        $pdf = $this->rendre($bulletin, $mention);
        $nom = $this->nomDuFichier($bulletin);

        return $apercu
            ? $pdf->stream($nom)
            : $pdf->download($nom);
    }

    /** Le document lui-meme, sans decider de ce qu'on en fait. */
    public function rendre(Bulletin $bulletin, ?string $mention = null): \Barryvdh\DomPDF\PDF
    {
        $bulletin->loadMissing(['agent.user', 'employeur.application', 'contrat.echelon.categorie', 'contrat.profil']);

        return Pdf::loadView('pdf.bulletin', [
            'bulletin' => $bulletin,
            'enTete' => $this->enTete($bulletin),
            'employeur' => $bulletin->employeur,
            'contrat' => $bulletin->contrat,
            'agent' => $bulletin->agent,
            'salarie' => $bulletin->agent?->user,
            'mention' => $mention ?? "Document remis a titre d'information. Conservez-le : il fait foi de votre remuneration.",
        ])->setPaper('a4');
    }

    public function nomDuFichier(Bulletin $bulletin): string
    {
        $qui = $bulletin->agent?->user?->matricule ?: 'agent-'.$bulletin->agent_id;

        return sprintf(
            'bulletin-%s-%04d-%02d.pdf',
            str_replace(['/', ' ', '\\'], '-', (string) $qui),
            $bulletin->annee,
            $bulletin->mois,
        );
    }

    /**
     * L'en-tete : c'est l'employeur qui edite la fiche de paie, donc c'est
     * son identite qui s'affiche.
     *
     * Le logo se cherche en trois temps — celui de l'employeur, puis celui
     * de l'institut auquel il est rattache, enfin celui du module — et le
     * premier trouve l'emporte.
     *
     * @return array{nom: string, logo: ?string, couleur: string, groupe: bool}
     */
    public function enTete(Bulletin $bulletin): array
    {
        $employeur = $bulletin->employeur;

        if ($employeur === null) {
            return [
                'nom' => 'LA MAJESTUEUSE',
                'logo' => $this->fichierEnBase64($this->logoDuModule()),
                'couleur' => self::COULEUR,
                'groupe' => true,
            ];
        }

        $institut = $employeur->application;

        return [
            'nom' => $employeur->nom,
            'logo' => $this->premierLogo([$employeur->logo, $institut?->logo, $this->logoDuModule()]),
            'couleur' => $institut?->color ?: self::COULEUR,
            'groupe' => false,
        ];
    }

    /**
     * Le premier chemin de la liste qui donne reellement une image.
     *
     * @param  list<?string>  $chemins
     */
    private function premierLogo(array $chemins): ?string
    {
        foreach ($chemins as $chemin) {
            if ($encode = $this->fichierEnBase64($chemin)) {
                return $encode;
            }
        }

        return null;
    }

    private function logoDuModule(): ?string
    {
        return Application::where('module_key', self::MODULE)->value('logo');
    }

    /**
     * L'image encodee dans le document : dompdf ne va pas chercher les
     * images sur le reseau, et une URL laisserait un cadre vide.
     */
    private function fichierEnBase64(?string $chemin): ?string
    {
        if (blank($chemin) || str_starts_with($chemin, 'http')) {
            return null;
        }

        foreach ([Storage::disk('public')->path($chemin), public_path($chemin)] as $fichier) {
            if (is_file($fichier)) {
                return 'data:'.(mime_content_type($fichier) ?: 'image/png')
                    .';base64,'.base64_encode((string) file_get_contents($fichier));
            }
        }

        return null;
    }
}
