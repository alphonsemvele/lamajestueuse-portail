<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Personnel utilisant le logiciel — {{ $editeLe }}</title>
    <style>
        @page { margin: 16mm 14mm 18mm; }

        body { font-family: DejaVu Sans, sans-serif; font-size: 11.5px; color: #1f2937; }
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

        /* liste */
        .liste { margin-top: 15px; }
        .liste thead th { background: #f8fafc; color: #475569; font-size: 8.5px; text-transform: uppercase;
                          letter-spacing: .8px; padding: 7px 9px; text-align: left;
                          border-bottom: 1px solid #e2e8f0; }
        .liste td { padding: 7.5px 10px; border-bottom: .6px solid #f1f2f4; }
        .liste tbody tr:nth-child(even) td { background: #fcfcfd; }
        .rang { width: 34px; color: #c3c7cd; font-size: 9.5px; text-align: right; }
        .nom { text-transform: uppercase; letter-spacing: .2px; }
        .matricule { width: 120px; font-weight: bold; color: #111827; }
        .sans { color: #c3c7cd; font-weight: normal; }
        .rien { padding: 28px 0; text-align: center; color: #9ca3af; }

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
                PERSONNEL UTILISANT LE LOGICIEL<br>
                <span class="portail">{{ $du === $au ? $du : 'DU '.mb_strtoupper($du).' AU '.mb_strtoupper($au) }}</span>
            </div>
            <div class="sous">Édité le {{ $editeLe }}</div>
        </td>
        <td width="16%" class="compte">
            <div class="nombre">{{ $gens->count() }}</div>
            <div class="mot">{{ $gens->count() > 1 ? 'personnes' : 'personne' }}</div>
        </td>
    </tr>
</table>

<div class="filet"></div>

<table class="liste">
    <thead>
        <tr>
            <th class="rang">N°</th>
            <th>Nom et prénom</th>
            <th class="matricule">Matricule</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($gens as $rang => $personne)
            <tr>
                <td class="rang">{{ $rang + 1 }}</td>
                <td class="nom">{{ $personne['nom'] }}</td>
                <td class="matricule {{ $personne['matricule'] ? '' : 'sans' }}">
                    {{ $personne['matricule'] ?: '—' }}
                </td>
            </tr>
        @empty
            <tr><td colspan="3" class="rien">Personne n’a ouvert le logiciel sur cette période.</td></tr>
        @endforelse
    </tbody>
</table>

<table class="pied">
    <tr>
        <td width="60%">{{ config('app.name') }} · document interne</td>
        <td width="40%" class="droite"></td>
    </tr>
</table>

</body>
</html>
