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

    /** Le lecteur n'accepte pas plus de chiffres que cela dans un numero. */
    private const LONGUEUR_MAX = 32;

    /** Ce que l'appareil accepte de rendre d'un coup lors d'une recherche. */
    private const PAR_PAGE = 30;

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

        if (preg_match(self::MATRICULE, $matricule, $trouve) === 1) {
            return $trouve[1];
        }

        // Un numero repris d'un autre systeme — « 1100E-20 » — garde ses
        // chiffres et perd le reste : c'est tout ce que le lecteur accepte.
        return mb_substr(preg_replace('/\D+/', '', $matricule) ?? '', 0, self::LONGUEUR_MAX);
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

        if ($xml === false) {
            return [];
        }

        $valeurs = [];

        // Seules les balises simples nous interessent — modele, firmware,
        // numero de serie ; les blocs imbriques ne sont pas des chaines.
        foreach ($xml->children() as $nom => $valeur) {
            if ($valeur->count() === 0) {
                $valeurs[(string) $nom] = (string) $valeur;
            }
        }

        return $valeurs;
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

    /**
     * Les cartes deja posees : le numero de la personne, et celui de sa carte.
     *
     * @return array<string, string>
     */
    public function cartes(): array
    {
        $cartes = [];
        $position = 0;

        do {
            $corps = $this->lire('POST', '/ISAPI/AccessControl/CardInfo/Search?format=json', [
                'CardInfoSearchCond' => [
                    'searchID' => (string) \Illuminate\Support\Str::uuid(),
                    'searchResultPosition' => $position,
                    'maxResults' => self::PAR_PAGE,
                ],
            ]);

            $trouvees = $corps['CardInfoSearch']['CardInfo'] ?? [];

            foreach ($trouvees as $carte) {
                $cartes[(string) ($carte['employeeNo'] ?? '')] = (string) ($carte['cardNo'] ?? '');
            }

            $position += count($trouvees);
            $suite = ($corps['CardInfoSearch']['responseStatusStrg'] ?? 'NO MATCH') === 'MORE';
        } while ($suite && $trouvees !== []);

        unset($cartes['']);

        return $cartes;
    }

    /**
     * Attend qu'une carte soit presentee au lecteur, et rend son numero.
     *
     * L'appareil ne repond pas tant que rien n'est passe devant lui : c'est
     * la lecture elle-meme qui fait la reponse. Au-dela du temps accorde, il
     * se tait et l'on rend null — personne n'a presente de carte.
     */
    public function capturerCarte(int $attente = 30): ?string
    {
        $this->verifierLeReglage();

        try {
            $reponse = $this->requete($attente + 5)
                ->get($this->adresse().'/ISAPI/AccessControl/CaptureCardInfo?format=json');
        } catch (\Illuminate\Http\Client\ConnectionException) {
            return null;
        }

        $corps = $this->verdict($reponse, '/ISAPI/AccessControl/CaptureCardInfo');
        $carte = trim((string) ($corps['CardInfo']['cardNo'] ?? $corps['cardNo'] ?? ''));

        return $carte === '' ? null : $carte;
    }

    /**
     * Pose une carte sur une personne deja presente dans le lecteur.
     *
     * Rien d'autre n'est touche : ni l'empreinte, ni le nom, ni les droits.
     */
    public function poserCarte(string $numero, string $carte): void
    {
        $this->lire('POST', '/ISAPI/AccessControl/CardInfo/Record?format=json', [
            'CardInfo' => [
                'employeeNo' => $numero,
                'cardNo' => $carte,
                'cardType' => 'normalCard',
            ],
        ]);
    }

    /** Retire une carte du lecteur, sans toucher a la personne. */
    public function retirerCarte(string $carte): void
    {
        $this->lire('PUT', '/ISAPI/AccessControl/CardInfo/Delete?format=json', [
            'CardInfoDelCond' => ['CardNoList' => [['cardNo' => $carte]]],
        ]);
    }

    /**
     * Les personnes que le lecteur porte : leur numero et leur nom.
     *
     * @return array<string, string>
     */
    public function personnes(): array
    {
        $gens = [];
        $position = 0;

        do {
            $corps = $this->lire('POST', '/ISAPI/AccessControl/UserInfo/Search?format=json', [
                'UserInfoSearchCond' => [
                    'searchID' => (string) \Illuminate\Support\Str::uuid(),
                    'searchResultPosition' => $position,
                    'maxResults' => self::PAR_PAGE,
                ],
            ]);

            $trouvees = $corps['UserInfoSearch']['UserInfo'] ?? [];

            foreach ($trouvees as $personne) {
                $gens[(string) ($personne['employeeNo'] ?? '')] = (string) ($personne['name'] ?? '');
            }

            $position += count($trouvees);
            $suite = ($corps['UserInfoSearch']['responseStatusStrg'] ?? 'NO MATCH') === 'MORE';
        } while ($suite && $trouvees !== []);

        unset($gens['']);

        return $gens;
    }

    /**
     * L'heure que porte le lecteur : son mode, son heure locale, son fuseau.
     *
     * Cet endroit-la repond en XML, comme deviceInfo.
     *
     * @return array{mode: ?string, heure: ?string, fuseau: ?string}
     */
    public function heure(): array
    {
        $this->verifierLeReglage();

        $reponse = $this->requete()->get($this->adresse().'/ISAPI/System/time');
        $this->verdict($reponse, '/ISAPI/System/time');

        $xml = @simplexml_load_string($reponse->body());

        return [
            'mode' => $xml === false ? null : (string) $xml->timeMode,
            'heure' => $xml === false ? null : (string) $xml->localTime,
            'fuseau' => $xml === false ? null : (string) $xml->timeZone,
        ];
    }

    /**
     * Met le lecteur a l'heure d'un fuseau donne.
     *
     * Les appareils sortent d'usine a l'heure de Chine : un pointage y porte
     * alors une heure fausse de sept heures, et le registre de presence ne
     * veut plus rien dire.
     *
     * Hikvision ecrit ses fuseaux a l'envers du sens courant — « CST-1:00:00 »
     * designe l'heure d'ete d'Europe centrale, c'est-a-dire UTC+1, celle du
     * Cameroun. Le signe est donc inverse a dessein.
     */
    public function reglerHeure(string $fuseau = 'Africa/Douala'): string
    {
        $this->verifierLeReglage();

        $maintenant = \Illuminate\Support\Carbon::now($fuseau);
        $decalage = $maintenant->getOffset(); // en secondes, positif a l'est

        $heures = intdiv(abs($decalage), 3600);
        $minutes = intdiv(abs($decalage) % 3600, 60);
        $secondes = abs($decalage) % 60;

        $ecrit = sprintf('CST%s%d:%02d:%02d', $decalage >= 0 ? '-' : '+', $heures, $minutes, $secondes);

        $corps = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Time version="2.0" xmlns="http://www.isapi.org/ver20/XMLSchema">'
            .'<timeMode>manual</timeMode>'
            .'<localTime>'.$maintenant->format('Y-m-d\TH:i:s').'</localTime>'
            .'<timeZone>'.$ecrit.'</timeZone>'
            .'</Time>';

        $reponse = $this->requete()
            ->withBody($corps, 'application/xml')
            ->put($this->adresse().'/ISAPI/System/time');

        $this->verdictXml($reponse, '/ISAPI/System/time');

        return $maintenant->format('Y-m-d H:i:s').' ('.$ecrit.')';
    }

    /**
     * Le verdict d'une reponse XML : meme regle que pour le JSON, l'appareil
     * repond 200 quand il refuse et c'est le statusCode qui tranche.
     */
    private function verdictXml(Response $reponse, string $chemin): void
    {
        if ($reponse->status() === 401) {
            throw new RuntimeException('Le lecteur a refusé l’identifiant ou le mot de passe.');
        }

        if ($reponse->failed()) {
            throw new RuntimeException("Le lecteur a répondu {$reponse->status()} sur {$chemin}.");
        }

        $xml = @simplexml_load_string($reponse->body());

        if ($xml !== false && isset($xml->statusCode) && (int) $xml->statusCode !== 1) {
            $detail = (string) ($xml->subStatusCode ?? $xml->statusString ?? 'refus sans motif');

            throw new RuntimeException("Le lecteur a refusé {$chemin} : {$detail}.");
        }
    }

    /**
     * A qui le lecteur pousse ses evenements.
     *
     * @return array{hote: ?string, protocole: ?string, port: ?string, adresse: ?string}
     */
    public function abonnement(): array
    {
        $this->verifierLeReglage();

        $reponse = $this->requete()->get($this->adresse().'/ISAPI/Event/notification/httpHosts/1');
        $this->verdictXml($reponse, '/ISAPI/Event/notification/httpHosts/1');

        $xml = @simplexml_load_string($reponse->body());

        return [
            'hote' => $xml === false ? null : (string) $xml->hostName,
            'protocole' => $xml === false ? null : (string) $xml->protocolType,
            'port' => $xml === false ? null : (string) $xml->portNo,
            'adresse' => $xml === false ? null : (string) $xml->url,
        ];
    }

    /**
     * Abonne le lecteur : il poussera chaque evenement a cette adresse.
     *
     * L'appareil n'accepte ni en-tete ni signature sur ces envois : le jeton
     * voyage donc dans le chemin, et c'est pourquoi il doit etre long.
     */
    public function abonner(string $portail, string $chemin): string
    {
        $this->verifierLeReglage();

        $morceaux = parse_url($portail);
        $hote = $morceaux['host'] ?? $portail;
        $protocole = ($morceaux['scheme'] ?? 'https') === 'http' ? 'HTTP' : 'HTTPS';
        $port = (int) ($morceaux['port'] ?? ($protocole === 'HTTPS' ? 443 : 80));

        $this->ecrireAbonnement([
            'url' => $chemin,
            'protocolType' => $protocole,
            'parameterFormatType' => 'JSON',
            'addressingFormatType' => 'hostname',
            'hostName' => $hote,
            'portNo' => (string) $port,
            'httpAuthenticationMethod' => 'none',
        ]);

        return mb_strtolower($protocole).'://'.$hote;
    }

    /** Coupe l'abonnement : le lecteur ne pousse plus rien. */
    public function desabonner(): void
    {
        $this->ecrireAbonnement([
            'url' => '',
            'protocolType' => 'HTTP',
            'parameterFormatType' => 'XML',
            'addressingFormatType' => 'ipaddress',
            'ipAddress' => '0.0.0.0',
            'portNo' => '0',
            'httpAuthenticationMethod' => 'none',
        ]);
    }

    /** @param array<string, string> $champs */
    private function ecrireAbonnement(array $champs): void
    {
        $corps = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<HttpHostNotification version="2.0" xmlns="http://www.isapi.org/ver20/XMLSchema">'
            .'<id>1</id>';

        foreach ($champs as $nom => $valeur) {
            $corps .= '<'.$nom.'>'.htmlspecialchars($valeur, ENT_XML1).'</'.$nom.'>';
        }

        $corps .= '</HttpHostNotification>';

        $reponse = $this->requete()
            ->withBody($corps, 'application/xml')
            ->put($this->adresse().'/ISAPI/Event/notification/httpHosts/1');

        $this->verdictXml($reponse, '/ISAPI/Event/notification/httpHosts/1');
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

    private function requete(?int $delai = null): PendingRequest
    {
        return Http::withOptions([
            'auth' => [
                (string) ($this->reglages['utilisateur'] ?? 'admin'),
                (string) ($this->reglages['mot_de_passe'] ?? ''),
                'digest',
            ],
            'verify' => (bool) ($this->reglages['verifier_certificat'] ?? false),
        ])->timeout($delai ?? (int) ($this->reglages['delai'] ?? 20))->acceptJson();
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
