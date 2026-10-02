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
| 'admin_route' : l'ecran depuis lequel on administre le module. C'est le lien
| que propose /admin/modules ; null quand le module se consulte seulement.
|
| 'ouvert_a_tous' : le module se pose de lui-meme sur le tableau de bord de
| tout le personnel, sans attribution individuelle.
|
*/

return [

    'profil' => [
        'name' => 'Mon profil',
        'description' => "Vos informations, votre parcours et vos pièces.",
        'route' => 'profil.index',
        'admin_route' => null,
        'icon' => 'user',
        'color' => '#0f766e',
        'ouvert_a_tous' => true,
        'manage_roles' => [],
    ],

    'tutoriels' => [
        'name' => 'Tutoriels',
        'description' => "Apprendre à se servir du portail, module par module.",
        'route' => 'tutoriels.index',
        'admin_route' => null,
        'icon' => 'book',
        'color' => '#b45309',
        'ouvert_a_tous' => true,
        'manage_roles' => [],
    ],

    'informations' => [
        'name' => "Centre d'information",
        'description' => "Actualités, annonces et affichage du groupe.",
        'route' => 'informations.index',
        'admin_route' => 'admin.posts.index',
        'icon' => 'newspaper',
        'color' => '#7c3aed',
        'manage_roles' => ['admin', 'editeur', 'redacteur'],
    ],

    'badges' => [
        'name' => 'Badges',
        'description' => "Demander son badge professionnel et suivre sa fabrication.",
        'route' => 'badges.index',
        'admin_route' => 'badges.gestion',
        'icon' => 'key',
        'color' => '#4f46e5',
        // Chacun demande son badge : la tuile se pose d'elle-meme sur le
        // tableau de bord de tout le personnel, sans attribution prealable.
        'ouvert_a_tous' => true,
        // Seuls ces roles traitent les demandes et impriment les badges.
        'manage_roles' => ['admin', 'drh', 'rh', 'accueil'],
    ],

    'bulletins' => [
        'name' => 'Mon bulletin de paie',
        'description' => "Consulter et télécharger ses bulletins de paie.",
        'route' => 'mes-bulletins.index',
        // Chacun consulte les siens : la tuile se pose d'elle-meme.
        'ouvert_a_tous' => true,
        'admin_route' => 'personnel.paie.index',
        'icon' => 'wallet',
        'color' => '#0f766e',
        // Rien a administrer ici : la paie se gere dans Personnel & paie.
        'manage_roles' => [],
    ],

    'personnel' => [
        'name' => 'Personnel & paie',
        'description' => "Dossiers du personnel, carrière, contrats et paie mensuelle du groupe.",
        'route' => 'personnel.index',
        'admin_route' => 'personnel.index',
        'icon' => 'briefcase',
        'color' => '#0f766e',
        // Les donnees sont sensibles : la tuile donne la consultation, ces
        // roles seuls ouvrent la saisie et la mise en paiement.
        'manage_roles' => ['admin', 'drh', 'rh', 'gestionnaire_paie'],
    ],

    'annuaire' => [
        'name' => 'Annuaire',
        'description' => "Rechercher un membre du personnel et consulter ses informations de contact.",
        'route' => 'annuaire.index',
        // L'annuaire se consulte : les fiches se modifient dans /admin/users.
        'admin_route' => null,
        'icon' => 'users',
        'color' => '#7c3aed',
        // L'annuaire se consulte : personne ne l'administre depuis le module,
        // les fiches se modifient dans /admin/users.
        'manage_roles' => [],
    ],

];
