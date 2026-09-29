<?php

/*
|--------------------------------------------------------------------------
| Badges du personnel
|--------------------------------------------------------------------------
|
| Le badge du groupe reprend le logo et la couleur de l'institut choisi : on
| ne dessine pas un badge par institut, on decline un modele commun.
|
| Le rendu est dans resources/js/pages/modules/badges/carte.tsx ; ajouter une
| entree ici ne suffit pas a creer un modele, il faut l'y dessiner.
|
*/

return [

    'modeles' => [
        'classique' => [
            'nom' => 'Classique',
            'description' => "Logo de l'institut en tête, photo ronde, mentions centrées.",
            'defaut' => true,
        ],
    ],

    // Duree de validite indiquee sur le badge, en annees.
    'validite_annees' => 2,

    // Mention imprimee au dos, sous le matricule.
    'mention' => "Ce badge est la propriété du groupe La Majestueuse. En cas de perte, prévenir le service des ressources humaines.",

];
