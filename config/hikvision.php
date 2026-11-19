<?php

/*
 * Le lecteur d'empreintes Hikvision de l'etablissement.
 *
 * Il parle ISAPI : du HTTP sur son adresse locale, authentifie en Digest,
 * derriere un certificat qu'il a signe lui-meme. Son adresse est privee —
 * le portail en ligne ne l'atteint pas, la synchronisation se lance depuis
 * un poste du site.
 *
 * Rien n'est ecrit ici : l'adresse et le mot de passe vivent dans le .env,
 * qui ne part pas sur GitHub.
 */
return [
    'url' => env('HIKVISION_URL'),

    'utilisateur' => env('HIKVISION_UTILISATEUR', 'admin'),

    'mot_de_passe' => env('HIKVISION_MOT_DE_PASSE'),

    // Le certificat du lecteur n'est signe par personne : on ne le verifie pas.
    'verifier_certificat' => (bool) env('HIKVISION_VERIFIER_CERTIFICAT', false),

    // La porte que ce lecteur commande, et le plan horaire qui s'y applique.
    'porte' => (int) env('HIKVISION_PORTE', 1),
    'plan_horaire' => env('HIKVISION_PLAN_HORAIRE', '1'),

    // Duree d'acces ouverte a chaque personne, en annees.
    'validite_annees' => (int) env('HIKVISION_VALIDITE_ANNEES', 10),

    // Secondes avant d'abandonner un appel : le lecteur est lent sous charge.
    'delai' => (int) env('HIKVISION_DELAI', 20),
    /*
     * Le portail, vu depuis le relais.
     *
     * Le relais tourne sur un poste du site : il sort vers le portail pour
     * y prendre les ordres, puis parle au lecteur sur le reseau local. Rien
     * n'entre : aucun port n'est ouvert sur le reseau de l'etablissement.
     */
    'portail' => [
        'url' => env('HIKVISION_PORTAIL_URL', 'https://lamajestueuse.com'),
        'client_id' => env('HIKVISION_RELAIS_ID', 'relais-lecteur'),
        'secret' => env('HIKVISION_RELAIS_SECRET'),
        'intervalle' => (int) env('HIKVISION_RELAIS_INTERVALLE', 15),
    ],
];
