<?php

use App\Mail\Badge\BadgePret;
use App\Mail\Badge\BadgeRefuse;
use App\Mail\Badge\DemandeEnregistree;
use App\Mail\Compte\CompteCree;
use App\Mail\Compte\CompteValide;
use App\Mail\Compte\DemandeRefusee;
use App\Mail\Compte\InscriptionRecue;
use App\Mail\EssaiEnvoi;

/*
|--------------------------------------------------------------------------
| Courriels du portail
|--------------------------------------------------------------------------
|
| Le catalogue des messages que le portail envoie, groupe par procedure. Il
| sert a l'ecran /admin/email/modeles : chacun s'y relit et s'y essaie sans
| avoir a reproduire la situation qui le declenche.
|
| Ajouter un courriel ici ne l'envoie pas : il faut aussi l'appeler dans la
| procedure concernee. La classe doit etendre CourrielDuPortail et savoir se
| construire avec des donnees d'exemple.
|
*/

return [

    'Compte' => [
        'inscription-recue' => [
            'titre' => "Accusé d'inscription",
            'description' => "À l'inscription en ligne, avant toute validation.",
            'classe' => InscriptionRecue::class,
        ],
        'compte-valide' => [
            'titre' => 'Compte validé',
            'description' => "Quand un administrateur ouvre le compte depuis /admin/users.",
            'classe' => CompteValide::class,
        ],
        'demande-refusee' => [
            'titre' => "Demande d'inscription refusée",
            'description' => "Quand un administrateur refuse la demande.",
            'classe' => DemandeRefusee::class,
        ],
        'compte-cree' => [
            'titre' => 'Compte ouvert par le service du personnel',
            'description' => "Quand les RH créent la fiche d'un nouvel arrivant.",
            'classe' => CompteCree::class,
        ],
    ],

    'Badges' => [
        'badge-enregistre' => [
            'titre' => 'Demande de badge enregistrée',
            'description' => "À l'envoi de la demande par l'employé.",
            'classe' => DemandeEnregistree::class,
        ],
        'badge-pret' => [
            'titre' => 'Badge prêt à être retiré',
            'description' => "Quand le guichet marque la demande imprimée.",
            'classe' => BadgePret::class,
        ],
        'badge-refuse' => [
            'titre' => 'Demande de badge refusée',
            'description' => "Quand le guichet refuse, avec le motif saisi.",
            'classe' => BadgeRefuse::class,
        ],
    ],

    'Divers' => [
        'essai' => [
            'titre' => "Essai d'envoi",
            'description' => "Le message d'essai des réglages e-mail.",
            'classe' => EssaiEnvoi::class,
        ],
    ],

];
