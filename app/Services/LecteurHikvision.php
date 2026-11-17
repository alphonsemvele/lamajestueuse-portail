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
     * Le numero envoye au lecteur pour un matricule.
     *
     * Les chiffres du matricule, ou le matricule tel quel s'il ne suit pas
     * le gabarit du groupe — un numero repris d'un ancien systeme. Le
     * DS-K1T321MFWX refuse les lettres dans ce champ : « LM-260147 » lui est
     * rendu avec un errorMsg employeeNo, « 260147 » passe.
     */
    public static function numeroDepuisMatricule(?string $matricule): string
    {
        $matricule = trim((string) $matricule);

        return preg_match(self::MATRICULE, $matricule, $trouve) === 1
            ? $trouve[1]
            : $matricule;
    }

    /**
     * Le nom tel que le lecteur l'affiche : coupe a trente-deux caracteres,
     * au-dela l'appareil refuse l'enregistrement.
     */
    public static function nomCourt(?string $nom): string
    {
        return mb_substr(trim((string) $nom), 0, 32);
    }

    public static function numeroDe(User $personne): string
    {
        return self::numeroDepuisMatricule($personne->matricule);
    }

    /** Nom de famille d'abord, comme sur le badge. */
    public static function nomDe(User $personne): string
    {
        return self::nomCourt($personne->nomOfficiel());
    }

    /**
     * Modele, firmware et numero de serie : de quoi verifier qu'on parle au
     * bon appareil.
     *
     * Le DS-K1T321MFWX repond en XML a cet endroit, meme interroge en JSON :
     * on lit donc les deux.
     */
    public function informations(): array
    {
        $this->verifierLeReglage();

        $reponse = $this->requete()->get($this->adresse().'/ISAPI/System/deviceInfo?format=json');
        $corps = $this->verdict($reponse, '/ISAPI/System/deviceInfo');

        if ($corps !== []) {
            return $corps['DeviceInfo'] ?? $corps;
        }

        $xml = @simplexml_load_string($reponse->body());

        return $xml === false ? [] : array_map('strval', (array) $xml);
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
     * Ni empreinte ni badge ne sont touches : ils vivent sous le numero, et
     * une mise a jour du nom les laisse en place.
     *
     * @return 'ajoute'|'mis a jour'
     */
    public function enregistrer(string $numero, string $nom): string
    {
        $deja = $this->chercher($numero) !== null;

        $this->lire(
            $deja ? 'PUT' : 'POST',
            $deja
                ? '/ISAPI/AccessControl/UserInfo/Modify?format=json'
                : '/ISAPI/AccessControl/UserInfo/Record?format=json',
            ['UserInfo' => $this->fiche($numero, self::nomCourt($nom))],
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
        $this->verifierLeReglage();

        $requete = $this->requete();
        $url = $this->adresse().$chemin;

        $reponse = match ($verbe) {
            'GET' => $requete->get($url),
            'PUT' => $requete->put($url, $corps),
            default => $requete->post($url, $corps),
        };

        return $this->verdict($reponse, $chemin);
    }

    /** Sans adresse ni mot de passe, on ne sort pas sur le reseau. */
    private function verifierLeReglage(): void
    {
        if (! $this->estConfigure()) {
            throw new RuntimeException(
                'Le lecteur n’est pas configuré : renseignez HIKVISION_URL et HIKVISION_MOT_DE_PASSE dans le .env.'
            );
        }
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
