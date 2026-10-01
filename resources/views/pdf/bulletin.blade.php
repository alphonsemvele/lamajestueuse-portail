@php
    /** Montants en francs CFA : pas de décimales, séparateur d'espace. */
    $fcfa = fn ($montant) => number_format((float) $montant, 0, ',', ' ') . ' F';
    $detail = $bulletin->detail ?? ['indemnites' => [], 'retenues' => []];

    /** Le taux d'une ligne, en français : virgule décimale, zéros inutiles retirés. */
    $regle = fn (array $ligne) => $ligne['type'] === 'pourcentage'
        ? rtrim(rtrim(number_format((float) $ligne['valeur'], 2, ',', ' '), '0'), ',') . ' % du salaire de base'
        : 'montant fixe';
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bulletin de paie — {{ $bulletin->periode() }}</title>
    <style>
        @page { margin: 18mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; }
        .bandeau { border-bottom: 2.5px solid {{ $enTete['couleur'] }}; padding-bottom: 10px; margin-bottom: 16px; }
        .bandeau td { vertical-align: middle; }
        .marque { font-size: 15px; font-weight: bold; color: {{ $enTete['couleur'] }}; letter-spacing: .3px; }
        .employeur { font-size: 8.5px; color: #6b7280; line-height: 1.5; }
        .titre { font-size: 13px; font-weight: bold; }
        .periode { font-size: 9px; color: #6b7280; }

        .identite { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .identite td { padding: 5px 8px; border: .5px solid #e5e7eb; width: 25%; }
        .etiquette { font-size: 7.5px; text-transform: uppercase; letter-spacing: .5px; color: #6b7280; display: block; }

        .lignes { width: 100%; border-collapse: collapse; }
        .lignes th { background: {{ $enTete['couleur'] }}; color: #fff; font-size: 8px; text-transform: uppercase;
                     letter-spacing: .5px; padding: 6px 8px; text-align: left; }
        .lignes td { padding: 5px 8px; border-bottom: .5px solid #f0f1f3; }
        .nombre { text-align: right; }
        .section td { background: #f8fafc; font-weight: bold; font-size: 8.5px; text-transform: uppercase;
                      letter-spacing: .4px; color: #475569; padding-top: 8px; }
        .total td { font-weight: bold; border-top: .8px solid #cbd5e1; border-bottom: none; }
        .net td { background: {{ $enTete['couleur'] }}; color: #fff; font-size: 12px; font-weight: bold; padding: 9px 8px; }
        .regle { font-size: 7.5px; color: #9ca3af; }

        .pied { margin-top: 18px; font-size: 7.5px; color: #6b7280; line-height: 1.6; }
        .signature { margin-top: 26px; font-size: 8.5px; }
    </style>
</head>
<body>

<table class="bandeau" width="100%">
    <tr>
        <td width="58%">
            @if ($enTete['logo'])
                <img src="{{ $enTete['logo'] }}" style="height: 34px;" alt="">
            @else
                <div class="marque">{{ $enTete['nom'] }}</div>
            @endif

            {{-- L'employeur est toujours nommé : c'est lui qui déclare. --}}
            <div class="employeur" style="margin-top: 5px;">
                {{ $employeur?->nom ?? '—' }}<br>
                @if ($employeur?->niu) NIU {{ $employeur->niu }} @endif
                @if ($employeur?->numero_cnps) · CNPS {{ $employeur->numero_cnps }} @endif
            </div>
        </td>
        <td width="42%" align="right">
            <div class="titre">Bulletin de paie</div>
            <div class="periode">{{ $bulletin->periode() }}</div>
            <div class="periode">Édité le {{ now()->translatedFormat('d F Y') }}</div>
        </td>
    </tr>
</table>

<table class="identite">
    <tr>
        <td colspan="2">
            <span class="etiquette">Salarié</span>
            <strong>{{ $agent?->user?->fullName() ?? '—' }}</strong>
        </td>
        <td>
            <span class="etiquette">Matricule</span>
            {{ $agent?->user?->matricule ?? '—' }}
        </td>
        <td>
            <span class="etiquette">N° CNPS</span>
            {{ $agent?->numero_cnps ?? '—' }}
        </td>
    </tr>
    <tr>
        <td colspan="2">
            <span class="etiquette">Emploi</span>
            {{ $contrat?->poste ?? '—' }}
        </td>
        <td>
            <span class="etiquette">Catégorie &amp; échelon</span>
            {{ $contrat?->echelonApplique()?->nomComplet() ?? '—' }}
        </td>
        <td>
            <span class="etiquette">Quotité</span>
            {{ $contrat?->quotite ?? 100 }} %
        </td>
    </tr>
</table>

<table class="lignes">
    <tr>
        <th width="56%">Désignation</th>
        <th width="22%">Base ou taux</th>
        <th width="22%" class="nombre">Montant</th>
    </tr>

    <tr>
        <td><strong>Salaire de base</strong></td>
        <td class="regle">
            {{ $contrat?->echelonApplique()?->nomComplet() ?? '' }}
            @if (($contrat?->quotite ?? 100) < 100) · quotité {{ $contrat->quotite }} % @endif
        </td>
        <td class="nombre"><strong>{{ $fcfa($bulletin->salaire_base) }}</strong></td>
    </tr>

    @if ($detail['indemnites'] ?? [])
        <tr class="section"><td colspan="3">Indemnités</td></tr>
        @foreach ($detail['indemnites'] as $ligne)
            <tr>
                <td>{{ $ligne['libelle'] }}</td>
                <td class="regle">
                    {{ $regle($ligne) }}
                    {{ ($ligne['source'] ?? '') === 'ajustement' ? ' · exceptionnel ce mois' : '' }}
                </td>
                <td class="nombre">{{ $fcfa($ligne['montant']) }}</td>
            </tr>
        @endforeach
        <tr class="total">
            <td colspan="2">Total des indemnités</td>
            <td class="nombre">{{ $fcfa($bulletin->total_indemnites) }}</td>
        </tr>
    @endif

    @if ($detail['retenues'] ?? [])
        <tr class="section"><td colspan="3">Retenues</td></tr>
        @foreach ($detail['retenues'] as $ligne)
            <tr>
                <td>{{ $ligne['libelle'] }}</td>
                <td class="regle">
                    {{ $regle($ligne) }}
                    {{ ($ligne['source'] ?? '') === 'ajustement' ? ' · exceptionnel ce mois' : '' }}
                </td>
                <td class="nombre">− {{ $fcfa($ligne['montant']) }}</td>
            </tr>
        @endforeach
        <tr class="total">
            <td colspan="2">Total des retenues</td>
            <td class="nombre">− {{ $fcfa($bulletin->total_retenues) }}</td>
        </tr>
    @endif

    <tr class="net">
        <td colspan="2">Net à payer</td>
        <td class="nombre">{{ $fcfa($bulletin->salaire_net) }}</td>
    </tr>
</table>

<div class="pied">
    {{ $mention }}<br>
    @if ($bulletin->statut === 'paye')
        Payé le {{ $bulletin->paye_le?->format('d/m/Y') ?? '—' }}.
    @else
        Validé le {{ $bulletin->valide_le?->format('d/m/Y') ?? '—' }} ; le versement suit.
    @endif
</div>

@if ($employeur?->signataire)
    <div class="signature" align="right">
        Pour {{ $employeur->sigle }}<br>
        <strong>{{ $employeur->signataire }}</strong>
    </div>
@endif

</body>
</html>
