<?php

/*
|--------------------------------------------------------------------------
| Modules internes du portail
|--------------------------------------------------------------------------
|
| Un module se declare ici, puis s'ajoute comme application depuis
| /admin/applications en choisissant le type « Module du portail ». Il reçoit
| alors une tuile comme n'importe quelle autre application, mais s'ouvre sur
| une route interne.
|
| 'manage_roles' : les valeurs de application_user.role_in_app qui donnent le
| droit d'administrer le contenu du module. Un administrateur du portail y a
| toujours acces.
|
*/

return [

    'informations' => [
        'name' => "Centre d'information",
        'description' => "Actualités, annonces et affichage du groupe.",
        'route' => 'informations.index',
        'icon' => 'newspaper',
        'color' => '#7c3aed',
        'manage_roles' => ['admin', 'editeur', 'redacteur'],
    ],

    'annuaire' => [
        'name' => 'Annuaire',
        'description' => "Rechercher un membre du personnel et consulter ses informations de contact.",
        'route' => 'annuaire.index',
        'icon' => 'users',
        'color' => '#7c3aed',
        // L'annuaire se consulte : personne ne l'administre depuis le module,
        // les fiches se modifient dans /admin/users.
        'manage_roles' => [],
    ],

];
