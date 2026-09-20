<?php

/*
|--------------------------------------------------------------------------
| Centre d'aide
|--------------------------------------------------------------------------
|
| Contenu de la page /support : questions frequentes et tutoriels illustres.
| Il se modifie ici, sans toucher au code de l'interface.
|
| Chaque rubrique : 'question', 'reponse', et au choix 'etapes' (suite
| d'etapes, chacune avec un titre, un texte et une capture facultative) ou
| 'points' (simple liste). Les captures sont des fichiers de public/images.
|
*/

return [

    'contact' => [
        'email' => env('SUPPORT_EMAIL', 'support@lamajestueuse.cm'),
        'telephone' => env('SUPPORT_TELEPHONE', '+237 6 99 00 00 00'),
        'horaires' => 'Du lundi au vendredi, de 8 h à 17 h',
        'adresse' => 'Yaoundé, Cameroun',
    ],

    'rubriques' => [

        [
            'cle' => 'creer-compte',
            'question' => 'Comment créer mon compte ?',
            'reponse' => "L'inscription se fait en une seule fois, depuis la page d'accueil du portail. Votre compte est ensuite validé par l'administration, qui vous attribue vos accès.",
            'etapes' => [
                [
                    'titre' => 'Ouvrir le formulaire',
                    'texte' => "Sur la page de connexion, cliquez sur « Vous faites partie du personnel ? Créez votre compte ».",
                    'capture' => 'images/support/01-connexion.jpg',
                ],
                [
                    'titre' => 'Ajouter votre photo',
                    'texte' => "La photo de profil est obligatoire : c'est elle qui vous identifie dans l'annuaire. Un portrait récent, visage dégagé, au format JPG, PNG ou WebP, de 4 Mo maximum.",
                    'capture' => 'images/support/02-photo.jpg',
                ],
                [
                    'titre' => 'Renseigner votre identité',
                    'texte' => "Prénom, nom, sexe, matricule et téléphone sont obligatoires. L'adresse e-mail est facultative, mais elle vous permettra de vous connecter avec elle plutôt qu'avec votre matricule.",
                    'capture' => 'images/support/03-identite.jpg',
                ],
                [
                    'titre' => 'Indiquer vos instituts',
                    'texte' => "Cochez les instituts où vous exercez et précisez votre poste dans chacun. Si vous ne savez pas, laissez vide : l'administration s'en chargera à la validation.",
                    'capture' => 'images/support/04-instituts.jpg',
                ],
                [
                    'titre' => 'Choisir un mot de passe et envoyer',
                    'texte' => "Huit caractères minimum, saisis deux fois à l'identique. Cliquez ensuite sur « Envoyer ma demande ».",
                    'capture' => 'images/support/05-mot-de-passe.jpg',
                ],
                [
                    'titre' => 'Attendre la validation',
                    'texte' => "Un message confirme l'enregistrement de votre demande. Vous pourrez vous connecter dès qu'un administrateur l'aura validée : n'ouvrez pas de seconde demande, elle serait refusée pour matricule déjà utilisé.",
                    'capture' => 'images/support/06-confirmation.jpg',
                ],
            ],
        ],

        [
            'cle' => 'se-connecter',
            'question' => 'Comment me connecter ?',
            'reponse' => "Une seule connexion donne accès à toutes vos applications : vous n'aurez plus à saisir de mot de passe dans IUM, IFPM ou GSBM.",
            'etapes' => [
                [
                    'titre' => 'Saisir votre identifiant',
                    'texte' => "Votre matricule ou votre adresse professionnelle, au choix : les deux fonctionnent.",
                    'capture' => 'images/support/01-connexion.jpg',
                ],
                [
                    'titre' => 'Ouvrir une application',
                    'texte' => "Après connexion, vos applications s'affichent sous forme de tuiles. Un clic suffit : le portail vous y connecte automatiquement.",
                    'capture' => 'images/support/07-applications.jpg',
                ],
            ],
        ],

        [
            'cle' => 'matricule-deja-utilise',
            'question' => 'Le portail refuse mon matricule : « déjà enregistré »',
            'reponse' => "Ce matricule correspond déjà à un compte. Deux cas possibles.",
            'points' => [
                "Vous avez déjà envoyé une demande : elle est en cours de validation, inutile d'en créer une seconde.",
                "Quelqu'un a saisi votre matricule par erreur, ou votre compte a été créé par l'administration : essayez de vous connecter avec ce matricule, ou contactez le support pour qu'il vérifie.",
            ],
        ],

        [
            'cle' => 'compte-en-attente',
            'question' => 'Mon compte est « en attente de validation »',
            'reponse' => "Toute inscription passe par l'administration : elle vérifie votre identité et vous attribue vos accès et vos rôles dans chaque institut.",
            'points' => [
                "Tant que la validation n'est pas faite, la connexion est refusée avec le message « Ce compte n'est pas encore activé ».",
                "Le délai habituel est d'un jour ouvré. Passé ce délai, contactez le support avec votre matricule.",
            ],
        ],

        [
            'cle' => 'mot-de-passe-oublie',
            'question' => "J'ai oublié mon mot de passe",
            'reponse' => "Le portail détient votre mot de passe unique : il ne se réinitialise pas depuis les applications.",
            'points' => [
                "Écrivez au support avec votre matricule et votre nom complet : un nouveau mot de passe provisoire vous sera transmis.",
                "Changez-le dès votre première connexion, depuis votre profil.",
            ],
        ],

        [
            'cle' => 'application-bientot',
            'question' => "Une application affiche « bientôt disponible »",
            'reponse' => "C'est normal : l'application est déclarée dans le portail mais n'est pas encore raccordée. Sa tuile reste visible pour que vous sachiez qu'elle arrive.",
            'points' => [
                "Vous n'avez rien à faire : dès sa mise en ligne, le clic ouvrira l'application.",
                "Aucune donnée n'est perdue : vos accès et vos rôles sont déjà enregistrés.",
            ],
        ],

        [
            'cle' => 'institut-manquant',
            'question' => "Mon institut n'apparaît pas dans la liste",
            'reponse' => "Seuls les instituts actifs du groupe sont proposés à l'inscription.",
            'points' => [
                "Laissez la case vide et terminez votre inscription : l'administration vous rattachera au bon institut.",
                "Précisez votre institut au support si vous voulez accélérer le rattachement.",
            ],
        ],

        [
            'cle' => 'photo-refusee',
            'question' => 'Ma photo est refusée',
            'reponse' => "Le portail n'accepte que les images, pour garder un annuaire lisible.",
            'points' => [
                'Formats acceptés : JPG, PNG ou WebP. Un PDF ou un document scanné sera refusé.',
                'Taille maximale : 4 Mo. Au-delà, réduisez la photo avant de l’envoyer.',
            ],
        ],

        [
            'cle' => 'langue-theme',
            'question' => 'Changer la langue ou passer en mode sombre',
            'reponse' => "Les réglages d'affichage sont accessibles depuis n'importe quelle page, en haut à droite.",
            'points' => [
                'FR / EN : bascule entre le français et l’anglais ; votre choix est mémorisé.',
                'Les trois icônes suivantes règlent le thème : automatique, clair ou sombre.',
            ],
        ],

    ],

];
