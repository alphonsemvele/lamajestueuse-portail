<?php

/*
|--------------------------------------------------------------------------
| Tutoriels du portail
|--------------------------------------------------------------------------
|
| Chaque tutoriel accompagne un module, pas a pas. Ajouter un tutoriel se
| fait ici : aucun code n'est a ecrire, l'ecran se construit a partir de ce
| catalogue.
|
| Un tutoriel comporte :
|   'cle'       l'adresse de la page (/tutoriels/{cle})
|   'module'    la cle du module concerne, pour proposer d'y aller
|   'etapes'    la suite des gestes, chacun avec un titre, un texte, des
|               points facultatifs et une capture facultative
|
| Les prealables — s'inscrire, se connecter, ouvrir le module — ouvrent tous
| les tutoriels : ils sont definis une fois pour toutes ci-dessous, et
| chaque tutoriel les precede automatiquement.
|
| Une capture annoncee mais absente du dossier public n'est pas transmise :
| la page ne montre jamais d'image cassee.
|
*/

return [

    /*
     * Ce par quoi commence tout tutoriel : avoir un compte, puis voir le
     * module sur son tableau de bord. Sans cela, le reste ne sert a rien.
     */
    'prealables' => [
        'titre' => 'Avant de commencer',
        'resume' => "Ces trois étapes valent pour tous les modules du portail : elles vous donnent un compte et l'accès.",
        'etapes' => [
            [
                'titre' => "S'inscrire au portail",
                'texte' => "Depuis la page d'accueil, cliquez sur « Créer un compte ». Renseignez votre identité, votre photo, votre téléphone, et les instituts où vous exercez. Le matricule n'est pas obligatoire : l'administration vous en attribuera un.",
                'points' => [
                    "La photo sert partout dans le portail : sur votre profil, et sur votre badge.",
                    "Indiquez votre adresse professionnelle si vous en avez une : elle vous servira à vous connecter.",
                ],
                'capture' => 'images/support/03-identite.jpg',
            ],
            [
                'titre' => "Attendre la validation",
                'texte' => "Votre demande part à l'administration du portail. Tant qu'elle n'est pas validée, la connexion est refusée. Vous recevez un message dès que le compte est ouvert.",
                'points' => [
                    "C'est à ce moment que votre matricule vous est communiqué, s'il vous en manquait un.",
                ],
                'capture' => 'images/support/06-confirmation.jpg',
            ],
            [
                'titre' => "Se connecter et trouver le module",
                'texte' => "Connectez-vous avec votre matricule — il suffit d'en taper les chiffres — ou avec votre adresse professionnelle. Votre tableau de bord affiche les modules auxquels vous avez accès.",
                'points' => [
                    "Certains modules, comme les badges ou votre profil, sont ouverts à tout le personnel : ils apparaissent sans qu'on ait à vous les attribuer.",
                    "Si un module vous manque, demandez-le à l'administration du portail.",
                ],
                'capture' => 'images/support/07-applications.jpg',
            ],
        ],
    ],

    'tutoriels' => [

        [
            'cle' => 'demander-son-badge',
            'module' => 'badges',
            'titre' => 'Demander son badge professionnel',
            'resume' => "Déposer une demande, choisir ce qui figurera sur la carte, et suivre sa fabrication jusqu'au retrait.",
            'icone' => 'id-card',
            'duree' => '3 minutes',
            'etapes' => [
                [
                    'titre' => 'Ouvrir le module Badges',
                    'texte' => "Depuis votre tableau de bord, cliquez sur la tuile « Badges ». Le module est ouvert à tout le personnel : vous n'avez rien à demander pour y accéder.",
                    'points' => [],
                    'capture' => 'images/tutoriels/badges/01-tuile.jpg',
                ],
                [
                    'titre' => 'Vérifier le nom qui figurera sur la carte',
                    'texte' => "Le portail propose votre nom tel qu'il est enregistré. Corrigez-le si vous préférez une autre forme : c'est exactement ce qui sera imprimé.",
                    'points' => [
                        "Écrivez-le comme vous voulez le lire sur votre badge, accents compris.",
                    ],
                    'capture' => 'images/tutoriels/badges/02-nom.jpg',
                ],
                [
                    'titre' => "Choisir l'institut dont le logo figurera",
                    'texte' => "Si vous relevez d'un seul institut, son logo est retenu d'office. Si vous en servez plusieurs, le portail vous demande lequel doit figurer sur la carte.",
                    'points' => [
                        "Un seul logo par badge : choisissez celui de l'institut où l'on vous verra le plus.",
                    ],
                    'capture' => 'images/tutoriels/badges/03-institut.jpg',
                ],
                [
                    'titre' => 'Joindre une photo, si besoin',
                    'texte' => "Votre photo de profil est reprise automatiquement. Vous pouvez en joindre une autre pour le badge : elle ne remplacera pas celle de votre profil.",
                    'points' => [
                        "Préférez une photo de face, sur fond clair, le visage bien dégagé.",
                    ],
                    'capture' => 'images/tutoriels/badges/04-photo.jpg',
                ],
                [
                    'titre' => 'Envoyer la demande',
                    'texte' => "Un aperçu vous montre le badge tel qu'il sera fabriqué. Vérifiez le nom et le logo, puis envoyez. Vous recevez aussitôt un accusé par message.",
                    'points' => [
                        "Vous ne pouvez avoir qu'une demande en cours à la fois.",
                    ],
                    'capture' => 'images/tutoriels/badges/05-apercu.jpg',
                ],
                [
                    'titre' => 'Suivre la fabrication',
                    'texte' => "Votre demande passe par quatre états : en attente, approuvée, imprimée, puis remise. Le module affiche où elle en est, et vous êtes prévenu dès qu'elle est prête.",
                    'points' => [
                        "Tant que personne n'a traité la demande, vous pouvez l'annuler et la refaire.",
                        "Une demande refusée indique son motif : corrigez ce qui est signalé et redéposez-la.",
                    ],
                    'capture' => 'images/tutoriels/badges/06-suivi.jpg',
                ],
                [
                    'titre' => 'Retirer son badge',
                    'texte' => "Quand la demande passe à « imprimée », présentez-vous au guichet de votre institut avec une pièce d'identité. Le badge vous est remis, et la demande passe à « remise ».",
                    'points' => [],
                    'capture' => 'images/tutoriels/badges/07-remise.jpg',
                ],
            ],
        ],

    ],
];
