<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Une piece du dossier d'un agent. Le fichier reste sur le disque prive :
 * on y accede par la route de telechargement, jamais par une URL directe.
 */
class DocumentAgent extends Model
{
    protected $table = 'documents_agent';

    protected $fillable = [
        'agent_id', 'type', 'libelle', 'fichier', 'nom_origine',
        'type_mime', 'taille', 'note', 'depose_par',
    ];

    protected $casts = ['taille' => 'integer'];

    /** Le dossier type d'un agent, dans l'ordre ou on le constitue. */
    public const TYPES = [
        'cv' => 'CV',
        'contrat' => 'Contrat signé',
        'diplome' => 'Diplôme',
        'cni' => "Pièce d'identité",
        'acte' => 'Acte de naissance',
        'cnps' => 'Document CNPS',
        'medical' => 'Certificat médical',
        'attestation' => 'Attestation',
        'autre' => 'Autre pièce',
    ];

    /** Le disque prive : rien de tout cela n'est servi par le serveur web. */
    public const DISQUE = 'local';

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function deposePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'depose_par');
    }

    public function existe(): bool
    {
        return Storage::disk(self::DISQUE)->exists($this->fichier);
    }

    /** Un poids lisible : « 1,4 Mo » plutot que 1 468 006. */
    public function poidsLisible(): string
    {
        $octets = (float) $this->taille;

        foreach (['o', 'Ko', 'Mo'] as $index => $unite) {
            if ($octets < 1024 || $unite === 'Mo') {
                return number_format($octets, $index === 0 ? 0 : 1, ',', ' ').' '.$unite;
            }

            $octets /= 1024;
        }

        return $this->taille.' o';
    }

    public function toUiArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'typeLibelle' => self::TYPES[$this->type] ?? $this->type,
            'libelle' => $this->libelle,
            'nomOrigine' => $this->nom_origine,
            'extension' => mb_strtolower(pathinfo($this->nom_origine, PATHINFO_EXTENSION)),
            'poids' => $this->poidsLisible(),
            'note' => $this->note,
            'deposePar' => $this->deposePar?->fullName(),
            'deposeLe' => $this->created_at?->format('d/m/Y'),
            'manquant' => ! $this->existe(),
        ];
    }
}
