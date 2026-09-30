<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Reglages d'envoi des courriels.
 *
 * Une seule ligne, chargee au demarrage pour remplacer la configuration du
 * serveur quand elle est active. Le mot de passe est chiffre en base et ne
 * ressort jamais vers l'interface.
 */
class ReglageEmail extends Model
{
    protected $table = 'reglages_email';

    protected $fillable = [
        'actif', 'hote', 'port', 'identifiant', 'mot_de_passe',
        'chiffrement', 'expediteur', 'nom_expediteur', 'teste_le',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'port' => 'integer',
        'mot_de_passe' => 'encrypted',
        'teste_le' => 'datetime',
    ];

    /** Les chiffrements proposes, avec le port qui va avec. */
    public const CHIFFREMENTS = [
        'tls' => 'TLS (port 587)',
        'ssl' => 'SSL (port 465)',
        '' => 'Aucun',
    ];

    /** La ligne unique, creee vide au besoin. */
    public static function actuels(): self
    {
        return static::firstOrNew([], [
            'actif' => false,
            'port' => 587,
            'chiffrement' => 'tls',
            'expediteur' => 'info@lamajestueuse.com',
            'nom_expediteur' => 'La Majestueuse',
        ]);
    }

    /**
     * Remplace la configuration d'envoi par celle-ci.
     *
     * Appelee au demarrage : la table peut ne pas exister (installation
     * neuve, migrations non jouees) et l'absence de reglages n'est pas une
     * erreur — on laisse alors le serveur decider.
     */
    public static function appliquer(): void
    {
        try {
            if (! Schema::hasTable('reglages_email')) {
                return;
            }

            $reglages = static::first();
        } catch (Throwable) {
            return;
        }

        if (! $reglages || ! $reglages->actif || blank($reglages->hote)) {
            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $reglages->hote,
            'mail.mailers.smtp.port' => $reglages->port ?: 587,
            'mail.mailers.smtp.username' => $reglages->identifiant,
            'mail.mailers.smtp.password' => $reglages->mot_de_passe,
            'mail.mailers.smtp.encryption' => $reglages->chiffrement ?: null,
            'mail.mailers.smtp.scheme' => $reglages->chiffrement === 'ssl' ? 'smtps' : 'smtp',
        ]);

        if (filled($reglages->expediteur)) {
            config([
                'mail.from.address' => $reglages->expediteur,
                'mail.from.name' => $reglages->nom_expediteur ?: config('app.name'),
            ]);
        }
    }

    /** Ce que l'interface a le droit de voir : jamais le mot de passe. */
    public function toUiArray(): array
    {
        return [
            'actif' => (bool) $this->actif,
            'hote' => $this->hote,
            'port' => $this->port,
            'identifiant' => $this->identifiant,
            'chiffrement' => $this->chiffrement ?? '',
            'expediteur' => $this->expediteur,
            'nomExpediteur' => $this->nom_expediteur,
            'motDePasseEnregistre' => filled($this->mot_de_passe),
            'testeLe' => $this->teste_le?->format('d/m/Y à H:i'),
        ];
    }
}
