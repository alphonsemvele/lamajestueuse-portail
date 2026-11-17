<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Le lecteur d'empreintes Hikvision, vu du portail.
 *
 * Le portail tient la liste du personnel ; le lecteur, lui, tient les
 * empreintes et les badges. On ne lui envoie donc que l'identite : un
 * numero et un nom. L'empreinte et le badge se posent ensuite sur
 * l'appareil, personne par personne, et viennent se ranger sous le numero
 * deja present.
 *
 * Le numero envoye est la partie chiffree du matricule — LM-260147 devient
 * 260147 : beaucoup de lecteurs Hikvision refusent les lettres dans ce
 * champ, et le chiffre suffit a retrouver la personne.
 *
 * Le dialogue est l'ISAPI de Hikvision : du JSON sur des chemins fixes,
 * authentifie en Digest. Le lecteur repond toujours 200 ; c'est le
 * statusCode du corps qui dit si l'ordre est passe.
 */
class LecteurHikvision
{
    /** Ce qui fait un matricule du groupe : LM- suivi de six chiffres. */
    private const MATRICULE = '/^LM-(\d+)$/';

    public function __construct(private readonly array $reglages)
    {
    }

    public static function depuisLaConfiguration(): self
    {
        return new self((array) config('hikvision'));
    }

    /** Le lecteur est-il configure ? Sans adresse ni mot de passe, non. */
    public function estConfigure(): bool
    {
        return filled($this->reglages['url'] ?? null)
            && filled($this->reglages['mot_de_passe'] ?? null);
    }

    public function adresse(): string
    {
        return rtrim((string) ($this->reglages['url'] ?? ''), '/');
    }

    /**
     * Le numero envoye au lecteur pour une personne.
     *
     * Les chiffres du matricule, ou le matricule tel quel s'il ne suit pas
     * le gabarit du groupe — un numero repris d'un ancien systeme.
     */
    public static function numeroDe(User $personne): string
    {
        $matricule = trim((string) $personne->matricule);

        return preg_match(self::MATRICULE, $matricule, $trouve) === 1
            ? $trouve[1]
            : $matricule;
    }

    /**
     * Le nom tel que le lecteur l'affiche.
     *
     * Nom de famille d'abord, comme sur le badge, et coupe a trente-deux
     * caracteres : au-dela, l'appareil refuse l'enregistrement.
     */
    public static function nomDe(User $personne): string
    {
        return mb_substr($personne->nomOfficiel(), 0, 32);
    }

    /** Modele, firmware et numero de serie : de quoi verifier qu'on parle au bon appareil. */
    public function informations(): array
    {
        $corps = $this->lire('GET', '/ISAPI/System/deviceInfo?format=json');

        return $corps['DeviceInfo'] ?? [];
    }

    /** Combien de personnes le lecteur connait deja. */
    public function nombreDePersonnes(): int
    {
        $corps = $this->lire('GET', '/ISAPI/AccessControl/UserInfo/Count?format=json');

        return (int) ($corps['UserInfoCount']['userNumber'] ?? 0);
    }

    /** La fiche d'un numero, ou null si le lecteur ne le connait pas. */
    public function chercher(string $numero): ?array
    {
        $corps = $this->lire('POST', '/ISAPI/AccessControl/UserInfo/Search?format=json', [
            'UserInfoSearchCond' => [
                'searchID' => (string) \Illuminate\Support\Str::uuid(),
                'searchResultPosition' => 0,
                'maxResults' => 1,
                'EmployeeNoList' => [['employeeNo' => $numero]],
            ],
        ]);

        return $corps['UserInfoSearch']['UserInfo'][0] ?? null;
    }

    /**
     * Pose une personne dans le lecteur : ajoutee, ou mise a jour si le
     * numero y est deja. Rend ce qui a ete fait.
     *
     * @return 'ajoute'|'mis a jour'
     */
    public function enregistrer(User $personne): string
    {
        $numero = self::numeroDe($personne);
        $deja = $this->chercher($numero) !== null;

        $this->lire(
            $deja ? 'PUT' : 'POST',
            $deja
                ? '/ISAPI/AccessControl/UserInfo/Modify?format=json'
                : '/ISAPI/AccessControl/UserInfo/Record?format=json',
            ['UserInfo' => $this->fiche($numero, self::nomDe($personne))],
        );

        return $deja ? 'mis a jour' : 'ajoute';
    }

    /** Retire un numero du lecteur. */
    public function retirer(string $numero): void
    {
        $this->lire('PUT', '/ISAPI/AccessControl/UserInfo/Delete?format=json', [
            'UserInfoDelCond' => [
                'EmployeeNoList' => [['employeeNo' => $numero]],
            ],
        ]);
    }

    /** La fiche telle que le lecteur l'attend. */
    private function fiche(string $numero, string $nom): array
    {
        $debut = now()->startOfDay();
        $fin = $debut->copy()->addYears(max(1, (int) ($this->reglages['validite_annees'] ?? 10)));

        return [
            'employeeNo' => $numero,
            'name' => $nom,
            'userType' => 'normal',
            'Valid' => [
                'enable' => true,
                'beginTime' => $debut->format('Y-m-d\TH:i:s'),
                'endTime' => $fin->format('Y-m-d\TH:i:s'),
                'timeType' => 'local',
            ],
            'doorRight' => (string) ($this->reglages['porte'] ?? 1),
            'RightPlan' => [[
                'doorNo' => (int) ($this->reglages['porte'] ?? 1),
                'planTemplateNo' => (string) ($this->reglages['plan_horaire'] ?? '1'),
            ]],
        ];
    }

    /**
     * Un appel au lecteur, dont on verifie la reponse.
     *
     * L'ISAPI repond 200 meme quand il refuse : le verdict est dans le
     * corps, sous statusCode. On leve donc sur les deux.
     */
    private function lire(string $verbe, string $chemin, array $corps = []): array
    {
        if (! $this->estConfigure()) {
            throw new RuntimeException(
                'Le lecteur n’est pas configuré : renseignez HIKVISION_URL et HIKVISION_MOT_DE_PASSE dans le .env.'
            );
        }

        $requete = $this->requete();
        $url = $this->adresse().$chemin;

        $reponse = match ($verbe) {
            'GET' => $requete->get($url),
            'PUT' => $requete->put($url, $corps),
            default => $requete->post($url, $corps),
        };

        return $this->verdict($reponse, $chemin);
    }

    private function requete(): PendingRequest
    {
        return Http::withOptions([
            'auth' => [
                (string) ($this->reglages['utilisateur'] ?? 'admin'),
                (string) ($this->reglages['mot_de_passe'] ?? ''),
                'digest',
            ],
            'verify' => (bool) ($this->reglages['verifier_certificat'] ?? false),
        ])->timeout((int) ($this->reglages['delai'] ?? 20))->acceptJson();
    }

    private function verdict(Response $reponse, string $chemin): array
    {
        if ($reponse->status() === 401) {
            throw new RuntimeException('Le lecteur a refusé l’identifiant ou le mot de passe.');
        }

        if ($reponse->failed()) {
            throw new RuntimeException("Le lecteur a répondu {$reponse->status()} sur {$chemin}.");
        }

        $corps = $reponse->json() ?? [];

        // Les lectures renvoient leurs donnees ; les ecritures, un statut.
        if (array_key_exists('statusCode', $corps) && (int) $corps['statusCode'] !== 1) {
            $detail = $corps['subStatusCode'] ?? $corps['statusString'] ?? 'refus sans motif';

            throw new RuntimeException("Le lecteur a refusé {$chemin} : {$detail}.");
        }

        return $corps;
    }
}
