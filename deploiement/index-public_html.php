<?php

/*
 * À utiliser UNIQUEMENT si la racine du domaine ne peut pas être déplacée
 * vers .../portail/public depuis le panneau PlanetHoster.
 *
 * Copier ce fichier dans public_html/index.php, puis copier également le
 * contenu de public/ (dossier build, favicon, images) dans public_html/.
 * Adapter le chemin ci-dessous au dossier réel de l'application.
 */

$application = __DIR__.'/../portail-lamajestueuse';

require $application.'/vendor/autoload.php';

$app = require_once $application.'/bootstrap/app.php';

$app->handleRequest(Illuminate\Http\Request::capture());
