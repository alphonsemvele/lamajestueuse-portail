<?php

namespace App\Services;

use App\Models\Pointage;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Lit un evenement du lecteur et en fait un pointage.
 *
 * Hikvision annonce bien plus que des passages : des portes ouvertes, des
 * alarmes, des touches pressees. On ne retient que ce qui designe quelqu'un,
 * et l'on garde le message entier a cote — ce qu'on n'a pas su lire
 * aujourd'hui se relira demain.
 */
class LecturePointage
{
    /** Les sous-types d'evenement qui disent « quelqu'un est passe ». */
    private const PASSAGES = [
        0x01, 0x26, 0x4b, 0x4c, 0x4d, 0x4e, 0x4f, 0x50, 0x51, 0x52, 0x53, 0x54, 0x55, 0x56, 0x57, 0x58,
        0x5a, 0x5b, 0x75, 0x76, 0x77, 0x78,
    ];

    /**
     * Enregistre un evenement. Rend le pointage, ou null si l'evenement ne
     * designe personne.
     *
     * @param  array<string, mixed>  $evenement
     */
    public function enregistrer(array $evenement): ?Pointage
    {
        $acces = $evenement['AccessControllerEvent'] ?? [];
        $numero = trim((string) ($acces['employeeNoString'] ?? $acces['employeeNo'] ?? ''));

        if ($numero === '' || $numero === '0') {
            return null;
        }

        $moment = $this->moment($evenement);
        $empreinte = hash('sha256', implode('|', [
            $acces['serialNo'] ?? '',
            $numero,
            $moment->toIso8601String(),
            $acces['subEventType'] ?? $acces['minorEventType'] ?? '',
        ]));

        $personne = $this->personne($numero);

        return Pointage::updateOrCreate(['empreinte' => $empreinte], [
            'numero' => $numero,
            'user_id' => $personne?->id,
            'nom' => trim((string) ($acces['name'] ?? '')) ?: $personne?->nomOfficiel(),
            'moment' => $moment,
            'sens' => $this->sens($acces),
            'moyen' => $this->moyen($acces),
            'porte' => isset($acces['doorNo']) ? (string) $acces['doorNo'] : null,
            'brut' => $evenement,
        ]);
    }

    /** @param array<string, mixed> $evenement */
    private function moment(array $evenement): Carbon
    {
        $annonce = $evenement['dateTime'] ?? $evenement['AccessControllerEvent']['currentVerifyMode'] ?? null;

        try {
            return Carbon::parse((string) $annonce);
        } catch (\Throwable) {
            return now();
        }
    }

    /**
     * Le compte du portail qui porte ce numero.
     *
     * Le numero du lecteur, ce sont les chiffres du matricule : on retrouve
     * donc la personne par la fin de son matricule, quelle qu'en soit la forme.
     */
    private function personne(string $numero): ?User
    {
        return User::where('matricule', 'LM-'.$numero)
            ->orWhere('matricule', $numero)
            ->first();
    }

    /** @param array<string, mixed> $acces */
    private function sens(array $acces): string
    {
        // Le lecteur d'entree porte le numero 1, celui de sortie le 2.
        return match ((int) ($acces['cardReaderNo'] ?? 0)) {
            1 => 'entree',
            2 => 'sortie',
            default => 'inconnu',
        };
    }

    /** @param array<string, mixed> $acces */
    private function moyen(array $acces): string
    {
        $mode = mb_strtolower((string) ($acces['currentVerifyMode'] ?? ''));
        $carte = trim((string) ($acces['cardNo'] ?? ''));

        /*
         * « currentVerifyMode » dit ce que la porte accepte — souvent
         * « cardOrFaceOrFp » — et non ce qui a servi. Le numero de carte,
         * lui, n'est renseigne que lorsqu'une carte est passee : c'est donc
         * lui qui tranche en premier.
         */
        return match (true) {
            $carte !== '' && trim($carte, '0') !== '' => 'carte',
            str_contains($mode, 'face') => 'visage',
            str_contains($mode, 'fp') || str_contains($mode, 'finger') => 'empreinte',
            str_contains($mode, 'pw') || str_contains($mode, 'code') => 'code',
            default => 'autre',
        };
    }

    /** Un passage, ou une annonce qui ne designe personne ? */
    public function estUnPassage(array $evenement): bool
    {
        $acces = $evenement['AccessControllerEvent'] ?? [];
        $sous = (int) ($acces['subEventType'] ?? $acces['minorEventType'] ?? 0);

        return $sous === 0 || in_array($sous, self::PASSAGES, true);
    }
}
