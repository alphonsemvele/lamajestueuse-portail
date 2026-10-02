@php
    /** Un nom propre, meme quand une moitie manque. */
    $lisible = fn (?string $valeur) => filled($valeur) ? $valeur : '—';
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Liste du personnel — {{ $editeLe }}</title>
    <style>
        @page { margin: 16mm 14mm 18mm; }

        body { font-family: DejaVu Sans, sans-serif; font-size: 11.5px; color: #1f2937; }
        table { border-collapse: collapse; width: 100%; }

        /* en-tête */
        .entete td { vertical-align: middle; }
        /* Les deux lignes du titre pesent pareil : c'est un seul intitule. */
        .titre { font-size: 16px; font-weight: bold; color: #111827; letter-spacing: .2px; line-height: 1.35; }
        .titre .portail { color: {{ $couleur }}; }
        .sous { font-size: 9.5px; color: #9ca3af; margin-top: 5px; }
        .compte { text-align: right; }
        .compte .nombre { font-size: 26px; font-weight: bold; color: {{ $couleur }}; line-height: 1; }
        .compte .mot { font-size: 8.5px; text-transform: uppercase; letter-spacing: .8px; color: #9ca3af; }
        .filet { height: 3px; background: {{ $couleur }}; margin: 11px 0 0; }

        /* tableau */
        .liste { margin-top: 14px; }
        .liste thead th { background: #f8fafc; color: #475569; font-size: 8.5px; text-transform: uppercase;
                          letter-spacing: .8px; padding: 7px 9px; text-align: left;
                          border-bottom: 1px solid #e2e8f0; }
        .liste td { padding: 7.5px 10px; border-bottom: .6px solid #f1f2f4; }
        .liste tbody tr:nth-child(even) td { background: #fcfcfd; }
        .rang { width: 34px; color: #c3c7cd; font-size: 9.5px; text-align: right; }
        .matricule { width: 90px; font-weight: bold; color: #111827; }
        .sans { color: #c3c7cd; font-weight: normal; }
        .nom { text-transform: uppercase; letter-spacing: .2px; }
        .prenom { color: #374151; }

        /* pied */
        .pied { position: fixed; bottom: -11mm; left: 0; right: 0;
                font-size: 8px; color: #9ca3af; border-top: .6px solid #e5e7eb; padding-top: 5px; }
        .pied td { vertical-align: middle; }
        .pied .droite { text-align: right; }
        /* dompdf sait compter les pages tout seul : pas besoin de script. */
        .pied .droite:after { content: "Page " counter(page); }

        /* La barre n'existe que dans le navigateur, et jamais sur le papier. */
        .barre { margin-bottom: 14px; padding: 10px 12px; background: #f1f5f9;
                 border-radius: 8px; font-size: 11px; color: #475569; }
        .barre button { background: #0f766e; color: #fff; border: 0; border-radius: 6px;
                        padding: 7px 12px; font-size: 11px; font-weight: 600; cursor: pointer;
                        margin-right: 10px; }
        @media print { .barre { display: none; } }
    </style>
</head>
<body>

@if ($navigateur ?? false)
    {{-- Servie dans le navigateur : on y ajoute de quoi imprimer. --}}
    <div class="barre">
        <button type="button" onclick="window.print()">Imprimer ou enregistrer en PDF</button>
        <span>Dans la fenêtre d’impression, choisissez « Enregistrer au format PDF ».</span>
    </div>
@endif

<table class="entete">
    <tr>
        <td width="11%">
            @if ($logo)
                <img src="{{ $logo }}" style="height: 42px;" alt="">
            @endif
        </td>
        <td width="73%">
            <div class="titre">
                LISTE DU PERSONNEL<br>
                <span class="portail">INSCRIT DANS LE PORTAIL WEB LA MAJESTUEUSE</span>
            </div>
            <div class="sous">Arrêtée au {{ $editeLe }}</div>
        </td>
        <td width="16%" class="compte">
            <div class="nombre">{{ $personnel->count() }}</div>
            <div class="mot">{{ $personnel->count() > 1 ? 'personnes' : 'personne' }}</div>
        </td>
    </tr>
</table>

<div class="filet"></div>

<table class="liste">
    <thead>
        <tr>
            <th class="rang">N°</th>
            <th style="width: 110px;">Matricule</th>
            <th>Nom</th>
            <th>Prénom</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($personnel as $rang => $membre)
            <tr>
                <td class="rang">{{ $rang + 1 }}</td>
                <td class="matricule {{ $membre->matricule ? '' : 'sans' }}">
                    {{ $lisible($membre->matricule) }}
                </td>
                <td class="nom">{{ $lisible($membre->lastname) }}</td>
                <td class="prenom">{{ $lisible($membre->name) }}</td>
            </tr>
        @endforeach
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
