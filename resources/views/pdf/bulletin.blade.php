@php
    /** Montants en francs CFA : pas de décimales, séparateur d'espace. */
    $fcfa = fn ($montant) => number_format((float) $montant, 0, ',', ' ') . ' F';

    /** Un nombre lisible en français, zéros inutiles retirés. */
    $nombre = fn ($valeur) => rtrim(rtrim(number_format((float) $valeur, 2, ',', ' '), '0'), ',');

    $detail = $bulletin->detail ?? ['indemnites' => [], 'retenues' => []];
    $indemnites = $detail['indemnites'] ?? [];
    $retenues = $detail['retenues'] ?? [];

    /**
     * L'assiette d'une ligne : un pourcentage peut porter sur une autre ligne
     * du profil plutôt que sur le salaire de base.
     */
    $assiette = function (array $ligne) use ($nombre) {
        if ($ligne['type'] !== 'pourcentage') {
            return 'forfait';
        }

        $sur = $ligne['assietteLibelle'] ?? 'le salaire de base';

        // « du salaire de base », mais « de Logement » : l'article suit.
        return $nombre($ligne['valeur']) . ' % '
            . ($sur === 'le salaire de base' ? 'du salaire de base' : 'de ' . $sur);
    };

    $brut = (float) $bulletin->salaire_base + (float) $bulletin->total_indemnites;

    $statuts = ['brouillon' => 'Provisoire', 'valide' => 'Arrêté', 'paye' => 'Payé'];
    $couleur = $enTete['couleur'];
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bulletin de paie — {{ $bulletin->periode() }}</title>
    <style>
        @page { margin: 14mm 14mm 16mm; }

        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #1f2937; line-height: 1.45; }
        table { border-collapse: collapse; width: 100%; }
        td, th { vertical-align: top; }
        .nombre { text-align: right; }

        /* en-tête */
        .entete td { vertical-align: middle; }
        .marque { font-size: 16px; font-weight: bold; color: {{ $couleur }}; letter-spacing: .2px; }
        .coordonnees { font-size: 7.5px; color: #6b7280; line-height: 1.6; margin-top: 3px; }
        .titre-bloc { text-align: right; }
        .titre { font-size: 15px; font-weight: bold; color: {{ $couleur }}; letter-spacing: 1.4px; }
        .periode { font-size: 10.5px; color: #374151; font-weight: bold; margin-top: 3px; }
        .reference { font-size: 7.5px; color: #9ca3af; margin-top: 4px; }
        .filet { height: 1.6px; background: {{ $couleur }}; margin: 12px 0 14px; }
        .sceau { border: .6px solid #c8cee0; color: #6b7280; font-size: 6.8px; font-weight: bold;
                 text-transform: uppercase; letter-spacing: .9px; padding: 2px 6px; }

        /* identité */
        .cartouche { border: .6px solid #dfe3ec; margin-bottom: 13px; }
        .cartouche td { padding: 6px 9px; border-right: .6px solid #f1f2f4; border-bottom: .6px solid #f1f2f4; }
        .cartouche tr:last-child td { border-bottom: none; }
        .cartouche td:last-child { border-right: none; }
        .etiquette { font-size: 6.8px; text-transform: uppercase; letter-spacing: .6px; color: #9ca3af;
                     display: block; margin-bottom: 1px; }
        .valeur { font-size: 9.5px; color: #111827; }
        .valeur-forte { font-weight: bold; }

        /* décompte */
        .decompte th { color: #8a93a6; font-size: 7px; text-transform: uppercase;
                       letter-spacing: .9px; padding: 4px 9px; text-align: left;
                       border-bottom: .6px solid #e2e6ee; }
        .decompte td { padding: 5.5px 9px; border-bottom: .6px solid #f3f4f6; }
        .rubrique td { background: #f4f6fa; color: {{ $couleur }}; font-size: 7.5px; font-weight: bold;
                       text-transform: uppercase; letter-spacing: .9px; padding: 6px 9px;
                       border-top: 1px solid {{ $couleur }}; border-bottom: .6px solid #e2e6ee; }
        .base { font-size: 7.5px; color: #9ca3af; }
        .soustotal td { background: #fbfcfe; font-weight: bold; border-top: .6px solid #e2e6ee;
                        border-bottom: .6px solid #e2e6ee; }
        .rien td { color: #9ca3af; font-style: italic; }

        /* net */
        .net { margin-top: 12px; }
        .net td { padding: 11px 13px; }
        .net-bande { background: {{ $couleur }}; color: #fff; }
        .net-libelle { font-size: 8px; text-transform: uppercase; letter-spacing: 1px; }
        .net-montant { font-size: 20px; font-weight: bold; letter-spacing: .3px; }
        .recapitulatif { border: .6px solid #e5e7eb; }
        .recapitulatif td { padding: 4.5px 9px; font-size: 8.5px; border-bottom: .6px solid #f3f4f6; }
        .recapitulatif tr:last-child td { border-bottom: none; }

        /* pied */
        .signature { margin-top: 20px; }
        .signature td { font-size: 8px; color: #6b7280; }
        .cadre-signature { border-top: .6px solid #d1d5db; padding-top: 4px; width: 60%; }
        .mentions { margin-top: 16px; padding-top: 8px; border-top: .6px solid #e5e7eb;
                    font-size: 7px; color: #9ca3af; line-height: 1.7; }
    </style>
</head>
<body>

<table class="entete">
    <tr>
        <td width="58%">
            @if ($enTete['logo'])
                <img src="{{ $enTete['logo'] }}" style="height: 64px;" alt="">
            @else
                <div class="marque">{{ $enTete['nom'] }}</div>
            @endif

            <div class="coordonnees">
                @if ($enTete['logo'])
                    <strong style="color: #374151;">{{ $employeur?->nom ?? $enTete['nom'] }}</strong><br>
                @endif
                @if ($employeur?->niu)NIU {{ $employeur->niu }}@endif
                @if ($employeur?->niu && $employeur?->numero_cnps) · @endif
                @if ($employeur?->numero_cnps)CNPS {{ $employeur->numero_cnps }}@endif
            </div>
        </td>
        <td width="42%" class="titre-bloc">
            <div class="titre">BULLETIN DE PAIE</div>
            <div class="periode">{{ ucfirst($bulletin->periode()) }}</div>
            <div class="reference">
                Pièce n° {{ str_pad((string) $bulletin->id, 6, '0', STR_PAD_LEFT) }}
                &nbsp;<span class="sceau">{{ $statuts[$bulletin->statut] ?? $bulletin->statut }}</span>
            </div>
        </td>
    </tr>
</table>

<div class="filet"></div>

<table class="cartouche">
    <tr>
        <td width="50%" colspan="2">
            <span class="etiquette">Salarié</span>
            <span class="valeur valeur-forte">{{ $salarie?->fullName() ?? '—' }}</span>
        </td>
        <td width="25%">
            <span class="etiquette">Matricule</span>
            <span class="valeur">{{ $salarie?->matricule ?: '—' }}</span>
        </td>
        <td width="25%">
            <span class="etiquette">N° CNPS</span>
            <span class="valeur">{{ $agent?->numero_cnps ?: '—' }}</span>
        </td>
    </tr>
    <tr>
        <td width="25%">
            <span class="etiquette">Emploi occupé</span>
            <span class="valeur">{{ $contrat?->poste ?: '—' }}</span>
        </td>
        <td width="25%">
            <span class="etiquette">Classification</span>
            @php
                // L'echelon du contrat, ou a defaut celui que son profil porte :
                // c'est bien celui-la qui a servi au calcul.
                $echelonApplique = $contrat?->echelon ?? $contrat?->profil?->echelon;
            @endphp
            <span class="valeur">
                {{ $echelonApplique?->categorie?->libelle ?? '—' }}{{ $echelonApplique?->libelle ? ' · '.$echelonApplique->libelle : '' }}
            </span>
        </td>
        <td width="25%">
            <span class="etiquette">Nature du contrat</span>
            <span class="valeur">{{ $contrat ? (\App\Models\Contrat::TYPES[$contrat->type] ?? '—') : '—' }}</span>
        </td>
        <td width="25%">
            <span class="etiquette">Entrée · Quotité</span>
            <span class="valeur">
                {{ $contrat?->date_debut?->format('d/m/Y') ?? '—' }}{{ $contrat ? ' · '.(int) $contrat->quotite.' %' : '' }}
            </span>
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <span class="etiquette">Profil de salaire</span>
            <span class="valeur">{{ $contrat?->profil?->nom ?: '—' }}</span>
        </td>
        <td colspan="2">
            <span class="etiquette">Mode de règlement</span>
            <span class="valeur">{{ $employeur?->banque ?: 'Virement bancaire' }}</span>
        </td>
    </tr>
</table>

<table class="decompte">
    <tr>
        <th width="46%">Désignation</th>
        <th width="30%">Base de calcul</th>
        <th width="24%" class="nombre">Montant</th>
    </tr>

    <tr class="rubrique"><td colspan="3">Rémunération</td></tr>
    <tr>
        <td>Salaire de base</td>
        <td class="base">
            {{ $echelonApplique ? 'Échelon '.($echelonApplique->libelle ?: $echelonApplique->numero) : 'Contrat' }}{{ $contrat && (int) $contrat->quotite !== 100 ? ' · quotité '.(int) $contrat->quotite.' %' : '' }}
        </td>
        <td class="nombre">{{ $fcfa($bulletin->salaire_base) }}</td>
    </tr>

    @forelse ($indemnites as $ligne)
        <tr>
            <td>{{ $ligne['libelle'] }}</td>
            <td class="base">{{ $assiette($ligne) }}</td>
            <td class="nombre">{{ $fcfa($ligne['montant']) }}</td>
        </tr>
    @empty
        <tr class="rien"><td colspan="3">Aucune indemnité sur la période.</td></tr>
    @endforelse

    <tr class="soustotal">
        <td colspan="2">Salaire brut</td>
        <td class="nombre">{{ $fcfa($brut) }}</td>
    </tr>

    <tr class="rubrique"><td colspan="3">Retenues</td></tr>
    @forelse ($retenues as $ligne)
        <tr>
            <td>{{ $ligne['libelle'] }}</td>
            <td class="base">{{ $assiette($ligne) }}</td>
            <td class="nombre">− {{ $fcfa($ligne['montant']) }}</td>
        </tr>
    @empty
        <tr class="rien"><td colspan="3">Aucune retenue sur la période.</td></tr>
    @endforelse

    <tr class="soustotal">
        <td colspan="2">Total des retenues</td>
        <td class="nombre">− {{ $fcfa($bulletin->total_retenues) }}</td>
    </tr>
</table>

<table class="net">
    <tr>
        <td width="50%" style="padding: 0 8px 0 0;">
            <table class="recapitulatif">
                <tr>
                    <td>Salaire brut</td>
                    <td class="nombre">{{ $fcfa($brut) }}</td>
                </tr>
                <tr>
                    <td>Total des retenues</td>
                    <td class="nombre">− {{ $fcfa($bulletin->total_retenues) }}</td>
                </tr>
                @if ($bulletin->paye_le)
                    <tr>
                        <td>Mis en paiement le</td>
                        <td class="nombre">{{ $bulletin->paye_le->format('d/m/Y') }}</td>
                    </tr>
                @endif
            </table>
        </td>
        <td width="50%" class="net-bande">
            <div class="net-libelle">Net à payer</div>
            <div class="net-montant">{{ $fcfa($bulletin->salaire_net) }}</div>
        </td>
    </tr>
</table>

<table class="signature">
    <tr>
        <td width="55%">
            <div class="cadre-signature">{{ $employeur?->signataire ?: "Pour l'employeur" }}</div>
        </td>
        <td width="45%" class="nombre" style="color: #9ca3af; font-size: 7.5px;">
            Établi le {{ $bulletin->updated_at?->format('d/m/Y') ?? now()->format('d/m/Y') }}
        </td>
    </tr>
</table>

<div class="mentions">
    {{ $mention }}
    <br>Dans votre intérêt et pour vous aider à faire valoir vos droits, conservez ce bulletin sans limitation de durée.
</div>

</body>
</html>
