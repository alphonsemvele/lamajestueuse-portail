<?php

/*
|--------------------------------------------------------------------------
| Badges du personnel
|--------------------------------------------------------------------------
|
| Les modeles proposes a la demande. Chacun reprend le logo et la couleur de
| l'institut choisi : on ne dessine pas un badge par institut, on decline un
| modele commun.
|
| 'apercu' decrit ce que l'employe voit dans le choix ; le rendu lui-meme est
| dans resources/js/pages/modules/badges/carte.tsx.
|
*/

return [

    'modeles' => [
        'classique' => [
            'nom' => 'Classique',
            'description' => "Bandeau coloré en tête, photo ronde, mentions centrées.",
            'defaut' => true,
        ],
        'bandeau' => [
            'nom' => 'Bandeau',
            'description' => "Photo en grand, nom et fonction sur un bandeau de couleur en pied.",
            'defaut' => false,
        ],
        'sobre' => [
            'nom' => 'Sobre',
            'description' => "Fond blanc, filet de couleur, logo discret : lisible de loin.",
            'defaut' => false,
        ],
    ],

    // Duree de validite indiquee sur le badge, en annees.
    'validite_annees' => 2,

    // Mention imprimee au dos, sous le matricule.
    'mention' => "Ce badge est la propriété du groupe La Majestueuse. En cas de perte, prévenir le service des ressources humaines.",

];
