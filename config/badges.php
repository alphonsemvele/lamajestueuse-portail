<?php

/*
|--------------------------------------------------------------------------
| Badges du personnel
|--------------------------------------------------------------------------
|
| Un seul dessin, decline par institut : fond blanc, bleu de la maison, et la
| couleur de l'institut choisi pour les filets et le cercle du portrait. On ne
| dessine pas un badge par institut, chacun reconnait le sien a sa couleur et
| a son logo.
|
| Le rendu — recto et verso — est dans resources/js/pages/modules/badges/
| carte.tsx ; ajouter une entree ici ne suffit pas a creer un modele, il faut
| l'y dessiner. Les coordonnees imprimees au verso y sont aussi.
|
*/

return [

    'modeles' => [
        'classique' => [
            'nom' => 'Classique',
            'description' => "Logo de l'institut en tête, portrait cerclé, devise et matricule ; coordonnées au verso.",
            'defaut' => true,
        ],
    ],

    // Duree de validite indiquee sur le badge, en annees.
    'validite_annees' => 2,

    // Mention imprimee au verso, sous les coordonnees.
    'mention' => 'Ce badge est la propriété du groupe La Majestueuse. En cas de perte, prévenir le service des ressources humaines.',

];
