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

    /*
     * Un bulletin tient sur une page, quel que soit le profil de salaire.
     * Au-dela d'une dizaine de lignes, interlignes et corps se resserrent
     * progressivement, jusqu'a vingt-quatre lignes.
     */
    $lignes = count($indemnites) + count($retenues);
    $serre = max(0.0, min(1.0, ($lignes - 9) / 15));

    /*
     * Au-dela de vingt-quatre lignes — plus du double de ce que porte le plus
     * charge des profils de la grille — tout le gabarit se reduit encore,
     * d'un cran par ligne supplementaire : le document reste sur une page.
     */
    $reduction = max(0.72, 1 - 0.022 * max(0, $lignes - 24));

    /** Une mesure qui va de « au large » a « au plus serre » avec le profil. */
    $px = fn (float $large, float $etroit) => round(($large - ($large - $etroit) * $serre) * $reduction, 2) . 'px';

    /** Un interligne, resserre de meme, sans jamais coller les lignes. */
    $interligne = fn (float $large, float $etroit) => round(max(1.08, ($large - ($large - $etroit) * $serre) * $reduction), 3);

    /** Hauteur du logo : il recule lui aussi quand les lignes s'accumulent. */
    $hauteurLogo = $px(64, 46);
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bulletin de paie — {{ $bulletin->periode() }}</title>
    <style>
        @page { margin: 12mm 13mm 12mm; }

        body { font-family: DejaVu Sans, sans-serif; font-size: {{ $px(11.5, 9.4) }}; color: #111827;
               line-height: {{ $interligne(1.4, 1.23) }}; }
        table { border-collapse: collapse; width: 100%; }
        td, th { vertical-align: top; }
        .nombre { text-align: right; }

        /* en-tête */
        .entete td { vertical-align: middle; }
        .marque { font-size: 18px; font-weight: bold; color: {{ $couleur }}; letter-spacing: .2px; }
        .coordonnees { font-size: 9.5px; color: #1f2937; line-height: 1.6; margin-top: 3px; }
        .titre-bloc { text-align: right; }
        .titre { font-size: 17px; font-weight: bold; color: {{ $couleur }}; letter-spacing: 1.4px; }
        .periode { font-size: 12.5px; color: #374151; font-weight: bold; margin-top: 3px; }
        .reference { font-size: 9px; color: #374151; margin-top: 4px; }
        .filet { height: 1.6px; background: {{ $couleur }}; margin: {{ $px(12, 7) }} 0 {{ $px(14, 8) }}; }
        .sceau { border: .8px solid {{ $couleur }}; color: {{ $couleur }}; font-size: 8.4px; font-weight: bold;
                 text-transform: uppercase; letter-spacing: .9px; padding: 2.5px 7px; }

        /* identité */
        .cartouche { border: .6px solid #dfe3ec; margin-bottom: {{ $px(13, 8) }}; }
        .cartouche td { padding: {{ $px(7, 4) }} 10px; border-right: .6px solid #f1f2f4; border-bottom: .6px solid #f1f2f4; }
        .cartouche tr:last-child td { border-bottom: none; }
        .cartouche td:last-child { border-right: none; }
        .etiquette { font-size: 8.4px; text-transform: uppercase; letter-spacing: .6px; color: #4b5563;
                     display: block; margin-bottom: 1px; }
        .valeur { font-size: {{ $px(11.5, 9.6) }}; color: #000; }
        .valeur-forte { font-weight: bold; }

        /* décompte */
        .decompte th { color: #374151; font-size: 8.6px; text-transform: uppercase;
                       letter-spacing: .9px; padding: {{ $px(4, 2.6) }} 9px; text-align: left;
                       border-bottom: .6px solid #e2e6ee; }
        .decompte td { padding: {{ $px(6.5, 2.9) }} 10px; border-bottom: .6px solid #f3f4f6; }
        .rubrique td { background: #f4f6fa; color: {{ $couleur }}; font-size: 8.5px; font-weight: bold;
                       text-transform: uppercase; letter-spacing: .9px; padding: {{ $px(6, 3) }} 9px;
                       border-top: 1px solid {{ $couleur }}; border-bottom: .6px solid #e2e6ee; }
        .base { font-size: {{ $px(9.2, 7.4) }}; color: #374151; }
        .soustotal td { background: #fbfcfe; font-weight: bold; border-top: .6px solid #e2e6ee;
                        border-bottom: .6px solid #e2e6ee; }
        .rien td { color: #9ca3af; font-style: italic; }

        /* net */
        .net { margin-top: {{ $px(12, 7) }}; }
        .net td { padding: {{ $px(11, 6.5) }} 13px; }
        .net-bande { background: {{ $couleur }}; color: #fff; }
        .net-libelle { font-size: 9.5px; text-transform: uppercase; letter-spacing: 1px; }
        .net-montant { font-size: {{ $px(23, 18) }}; font-weight: bold; letter-spacing: .3px; }
        .recapitulatif { border: .6px solid #e5e7eb; }
        .recapitulatif td { padding: {{ $px(5.5, 3.2) }} 9px; font-size: {{ $px(10.5, 9.2) }}; border-bottom: .6px solid #f3f4f6; }
        .recapitulatif tr:last-child td { border-bottom: none; }

        /* pied */
        .signature { margin-top: {{ $px(20, 9) }}; }
        .signature td { font-size: 10px; color: #111827; }
        .cadre-signature { border-top: .6px solid #d1d5db; padding-top: 4px; width: 60%; }
        .mentions { margin-top: {{ $px(14, 7) }}; padding-top: {{ $px(8, 5) }}; border-top: .6px solid #d1d5db;
                    font-size: {{ $px(8.8, 8) }}; color: #374151; line-height: {{ $interligne(1.6, 1.35) }}; }
    </style>
</head>
<body>

<table class="entete">
    <tr>
        <td width="58%">
            @if ($enTete['logo'])
                <img src="{{ $enTete['logo'] }}" style="height: {{ $hauteurLogo }};" alt="">
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
                {{-- Un modele n'a pas ete emis : il n'a donc ni numero de piece
                     ni etat, et le dire vaut mieux qu'afficher « n° 000000 ». --}}
                @if (! empty($modele))
                    <span class="sceau">Modèle — aucun bulletin émis</span>
                @else
                    Pièce n° {{ str_pad((string) $bulletin->id, 6, '0', STR_PAD_LEFT) }}
                    &nbsp;<span class="sceau">{{ $statuts[$bulletin->statut] ?? $bulletin->statut }}</span>
                @endif
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
        <td width="21%">
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
        <td width="29%">
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
        <td width="45%" class="nombre" style="color: #374151; font-size: 9.5px;">
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
