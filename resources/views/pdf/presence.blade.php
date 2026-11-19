@php
    /** L'heure seule d'un horodatage, quand il y en a un. */
    $heure = fn (?string $valeur) => $valeur ? \Illuminate\Support\Carbon::parse($valeur)->format('H:i') : '—';
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Registre de présence — {{ $du }}</title>
    <style>
        @page { margin: 16mm 14mm 18mm; }

        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        table { border-collapse: collapse; width: 100%; }

        /* en-tête */
        .entete td { vertical-align: middle; }
        .titre { font-size: 16px; font-weight: bold; color: #111827; letter-spacing: .2px; line-height: 1.35; }
        .titre .portail { color: {{ $couleur }}; }
        .sous { font-size: 9.5px; color: #9ca3af; margin-top: 5px; }
        .compte { text-align: right; }
        .compte .nombre { font-size: 26px; font-weight: bold; color: {{ $couleur }}; line-height: 1; }
        .compte .mot { font-size: 8.5px; text-transform: uppercase; letter-spacing: .8px; color: #9ca3af; }
        .filet { height: 3px; background: {{ $couleur }}; margin: 11px 0 0; }

        /* bandeau de totaux */
        .totaux { margin-top: 13px; }
        .totaux td { border: .6px solid #e5e7eb; padding: 7px 10px; width: 25%; }
        .totaux .mot { font-size: 8px; text-transform: uppercase; letter-spacing: .7px; color: #9ca3af; }
        .totaux .valeur { font-size: 14px; font-weight: bold; color: #111827; margin-top: 2px; }

        /* tableaux */
        .liste { margin-top: 0; }
        /* dompdf centre une legende de tableau : on la pose a cote. */
        .intitule { margin: 15px 0 5px; font-size: 9px; text-transform: uppercase;
                    letter-spacing: .9px; color: {{ $couleur }}; font-weight: bold; }
        .liste thead th { background: #f8fafc; color: #475569; font-size: 8.5px; text-transform: uppercase;
                          letter-spacing: .8px; padding: 6px 9px; text-align: left;
                          border-bottom: 1px solid #e2e8f0; }
        .liste td { padding: 6px 9px; border-bottom: .6px solid #f1f2f4; }
        .liste tbody tr:nth-child(even) td { background: #fcfcfd; }
        .rang { width: 30px; color: #c3c7cd; font-size: 9px; text-align: right; }
        .matricule { width: 86px; font-weight: bold; color: #111827; }
        .nombre { text-align: center; width: 48px; }
        .heure { width: 104px; color: #374151; white-space: nowrap; }
        .sans { color: #b91c1c; font-size: 9px; }
        .rien { padding: 24px 0; text-align: center; color: #9ca3af; }

        /* pied */
        .pied { position: fixed; bottom: -11mm; left: 0; right: 0;
                font-size: 8px; color: #9ca3af; border-top: .6px solid #e5e7eb; padding-top: 5px; }
        .pied td { vertical-align: middle; }
        .pied .droite { text-align: right; }
        .pied .droite:after { content: "Page " counter(page); }
    </style>
</head>
<body>

<table class="entete">
    <tr>
        <td width="11%">
            @if ($logo)
                <img src="{{ $logo }}" style="height: 42px;" alt="">
            @endif
        </td>
        <td width="73%">
            <div class="titre">
                REGISTRE DE PRÉSENCE<br>
                <span class="portail">{{ $unSeulJour ? $du : 'DU '.mb_strtoupper($du).' AU '.mb_strtoupper($au) }}</span>
            </div>
            <div class="sous">
                Édité le {{ $editeLe }}
                @if ($recherche !== '') · filtré sur « {{ $recherche }} » @endif
            </div>
        </td>
        <td width="16%" class="compte">
            <div class="nombre">{{ $resume['personnes'] }}</div>
            <div class="mot">{{ $resume['personnes'] > 1 ? 'personnes' : 'personne' }}</div>
        </td>
    </tr>
</table>

<div class="filet"></div>

<table class="totaux">
    <tr>
        <td>
            <div class="mot">Passages</div>
            <div class="valeur">{{ $resume['passages'] }}</div>
        </td>
        <td>
            <div class="mot">Jours avec passage</div>
            <div class="valeur">{{ $resume['jours'] }}</div>
        </td>
        <td>
            <div class="mot">Premier</div>
            <div class="valeur">{{ $heure($resume['premier']) }}</div>
        </td>
        <td>
            <div class="mot">Dernier</div>
            <div class="valeur">{{ $heure($resume['dernier']) }}</div>
        </td>
    </tr>
</table>

<div class="intitule">Par personne</div>

<table class="liste">
    <thead>
        <tr>
            <th class="rang">N°</th>
            <th class="matricule">Numéro</th>
            <th>Nom</th>
            <th class="nombre">Jours</th>
            <th class="nombre">Passages</th>
            <th class="heure">Premier</th>
            <th class="heure">Dernier</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($personnes as $rang => $personne)
            <tr>
                <td class="rang">{{ $rang + 1 }}</td>
                <td class="matricule">{{ $personne['numero'] }}</td>
                <td>
                    {{ $personne['nom'] }}
                    @if (! $personne['connu'])
                        <span class="sans">· inconnu du portail</span>
                    @endif
                </td>
                <td class="nombre">{{ $personne['jours'] }}</td>
                <td class="nombre">{{ $personne['passages'] }}</td>
                <td class="heure">{{ $personne['premier'] }}</td>
                <td class="heure">{{ $personne['dernier'] }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="rien">Aucun passage sur cette période.</td></tr>
        @endforelse
    </tbody>
</table>

@if ($unSeulJour && $passages->isNotEmpty())
    <div class="intitule">Le détail de la journée</div>

    <table class="liste">
        <thead>
            <tr>
                <th class="heure" style="width: 70px;">Heure</th>
                <th class="matricule">Numéro</th>
                <th>Nom</th>
                <th style="width: 80px;">Sens</th>
                <th style="width: 90px;">Moyen</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($passages as $passage)
                <tr>
                    <td class="heure">{{ $passage->moment?->format('H:i:s') }}</td>
                    <td class="matricule">{{ $passage->user?->matricule ?: $passage->numero }}</td>
                    <td>{{ $passage->nomAffiche() }}</td>
                    <td>{{ ['entree' => 'Entrée', 'sortie' => 'Sortie'][$passage->sens] ?? '—' }}</td>
                    <td>{{ \App\Models\Pointage::MOYENS[$passage->moyen] ?? $passage->moyen }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<table class="pied">
    <tr>
        <td width="60%">{{ config('app.name') }} · registre tenu par le lecteur de présence</td>
        <td width="40%" class="droite"></td>
    </tr>
</table>

</body>
</html>
