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

    /** Le bleu du logo : c'est la couleur du groupe sur les documents. */
    private const COULEUR = '#002484';

    /**
     * L'intensite du filigrane, en pourcentage.
     *
     * Assez pour qu'on voie la marque sous le decompte, pas assez pour gener
     * la lecture d'un chiffre ni pour ressortir sur une photocopie.
     */
    private const FILIGRANE = 9;

    /**
     * Rend le bulletin, a l'ecran ou en telechargement.
     *
     * `$apercu` sert le document en ligne, pour la previsualisation ; sinon
     * le navigateur propose de l'enregistrer.
     */
    public function reponse(Bulletin $bulletin, bool $apercu = false, ?string $mention = null, bool $modele = false): Response
    {
        $pdf = $this->rendre($bulletin, $mention, $modele);
        $nom = $this->nomDuFichier($bulletin);

        return $apercu
            ? $pdf->stream($nom)
            : $pdf->download($nom);
    }

    /** Le document lui-meme, sans decider de ce qu'on en fait. */
    /**
     * @param  bool  $modele  Un bulletin calcule mais jamais emis : il n'a ni
     *                        numero de piece ni etat, et le document le dit.
     */
    public function rendre(Bulletin $bulletin, ?string $mention = null, bool $modele = false): \Barryvdh\DomPDF\PDF
    {
        $bulletin->loadMissing([
            'agent.user', 'employeur.application',
            'contrat.agent.user', 'contrat.echelon.categorie',
            'contrat.profil.echelon.categorie',
        ]);

        // Le dossier du bulletin, ou a defaut celui de son contrat : un
        // bulletin ancien peut avoir perdu son lien direct, et l'identite ne
        // doit pas disparaitre du document pour autant.
        $agent = $bulletin->agent ?? $bulletin->contrat?->agent;

        $enTete = $this->enTete($bulletin);

        return Pdf::setOptions($this->options())->loadView('pdf.bulletin', [
            'bulletin' => $bulletin,
            'enTete' => $enTete,
            // Le logo de l'en-tete, delave, pose en fond de page. A defaut
            // d'un logo d'employeur, c'est la marque du groupe qui filigrane.
            'filigrane' => $this->filigrane($enTete['logo'] ?: $this->logoDuGroupe()),
            'employeur' => $bulletin->employeur,
            'contrat' => $bulletin->contrat,
            'agent' => $agent,
            'salarie' => $agent?->user,
            'modele' => $modele,
            'mention' => $mention ?? "Document remis à titre d'information. Conservez-le : il fait foi de votre rémunération.",
        ])->setPaper('a4');
    }

    /**
     * Les chemins de travail de dompdf, sous storage/ et crees au besoin.
     *
     * Par defaut le moteur ecrit son cache de polices dans storage/fonts et
     * ses fichiers temporaires dans le repertoire temporaire du systeme.
     * Sur un hebergement mutualise, ni l'un ni l'autre n'est garanti : le
     * dossier n'existe pas — storage/ ne vient jamais du depot — et le
     * temporaire du systeme peut etre hors de portee. On les pose donc
     * nous-memes, la ou l'application sait pouvoir ecrire.
     *
     * @return array<string, mixed>
     */
    private function options(): array
    {
        $polices = storage_path('app/dompdf/polices');
        $temporaire = storage_path('app/dompdf/temporaire');

        foreach ([$polices, $temporaire] as $dossier) {
            if (! is_dir($dossier)) {
                @mkdir($dossier, 0775, true);
            }
        }

        return [
            'fontDir' => is_writable($polices) ? $polices : storage_path('fonts'),
            'fontCache' => is_writable($polices) ? $polices : storage_path('fonts'),
            'tempDir' => is_writable($temporaire) ? $temporaire : sys_get_temp_dir(),
            // Les images voyagent en data: URI : rien n'est lu sur le disque.
            'isRemoteEnabled' => false,
            'defaultFont' => 'DejaVu Sans',
        ];
    }

    /**
     * Le logo du groupe, encode pour un document.
     *
     * Sert aux pieces qui ne relevent d'aucun employeur — la liste du
     * personnel, par exemple — la ou le bulletin suit son institut.
     */
    public function logoDuGroupe(): ?string
    {
        return $this->fichierEnBase64('images/marque-la-majestueuse.png');
    }

    /** Les options de dompdf, pour un document qui n'est pas un bulletin. */
    public function optionsDocument(): array
    {
        return $this->options();
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
            // Le document garde le bleu du groupe : la couleur d'un institut
            // sert a le reconnaitre a l'ecran, pas a teinter une piece de paie.
            'couleur' => self::COULEUR,
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
     * Le logo en filigrane : la meme image, rendue transparente.
     *
     * Le fondu se calcule ici plutot que de se confier a une transparence
     * CSS : dompdf ne l'applique pas aux images avec la meme fidelite selon
     * les versions. Surtout, l'image garde sa transparence — un calque pose
     * sur du blanc effacerait ce qu'il recouvre, et le net a payer avec.
     */
    public function filigrane(?string $image): ?string
    {
        if (blank($image) || ! str_contains((string) $image, 'base64,') || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $binaire = base64_decode(substr($image, strpos($image, 'base64,') + 7), true);
        $source = $binaire === false ? false : @imagecreatefromstring($binaire);

        if ($source === false) {
            return null;
        }

        // Un filigrane s'imprime grand mais n'a pas besoin de finesse : on
        // ramene l'image a une taille raisonnable avant de la parcourir.
        $largeur = min(640, imagesx($source));
        $hauteur = max(1, (int) round(imagesy($source) * $largeur / imagesx($source)));

        $pale = imagecreatetruecolor($largeur, $hauteur);
        imagealphablending($pale, false);
        imagesavealpha($pale, true);
        imagefilledrectangle($pale, 0, 0, $largeur - 1, $hauteur - 1,
            (int) imagecolorallocatealpha($pale, 255, 255, 255, 127));
        imagecopyresampled($pale, $source, 0, 0, 0, 0, $largeur, $hauteur, imagesx($source), imagesy($source));

        // Chaque point garde sa couleur et perd de son opacite : 0 est opaque,
        // 127 transparent, et l'on va de l'un vers l'autre.
        for ($y = 0; $y < $hauteur; $y++) {
            for ($x = 0; $x < $largeur; $x++) {
                $point = imagecolorat($pale, $x, $y);
                $opacite = ($point >> 24) & 0x7F;

                imagesetpixel($pale, $x, $y,
                    ((int) round(127 - (127 - $opacite) * self::FILIGRANE / 100) << 24) | ($point & 0xFFFFFF));
            }
        }

        ob_start();
        imagepng($pale);
        $png = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($pale);

        return 'data:image/png;base64,'.base64_encode($png);
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
