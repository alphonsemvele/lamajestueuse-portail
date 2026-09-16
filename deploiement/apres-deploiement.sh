#!/usr/bin/env bash
#
# Exécuté SUR LE SERVEUR après l'envoi des fichiers par GitHub Actions.
#
#   1. sauvegarde de la base (conservées : les 10 dernières) ;
#   1 bis. racine du domaine (~/public_html) reliée à public/ ;
#   2. mise en maintenance ;
#   3. migrations ;
#   4. mise en cache de la configuration, des routes et des vues ;
#   5. remise en service.
#
# Réglages possibles (variables d'environnement) :
#   PHP_BIN=/opt/alt/php83/usr/bin/php   binaire PHP à utiliser (défaut : php)
#   DOSSIER_SAUVEGARDES=~/sauvegardes    où déposer les dumps
#   SANS_SAUVEGARDE=1                    passer la sauvegarde (déconseillé)
#   LIER_RACINE_WEB=0                    ne pas toucher à ~/public_html
#
# Essai à blanc, sans migration ni coupure du site :
#   bash deploiement/apres-deploiement.sh --essai

set -euo pipefail

PHP="${PHP_BIN:-php}"
RACINE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ESSAI=0
[ "${1:-}" = "--essai" ] && ESSAI=1

cd "$RACINE"
echo "▸ Application : $RACINE"

if [ ! -f .env ]; then
    echo "✗ Aucun fichier .env sur le serveur. Créez-le à partir de deploiement/env-production.exemple," >&2
    echo "  puis lancez « $PHP artisan key:generate »." >&2
    exit 1
fi

if ! command -v "$PHP" >/dev/null 2>&1; then
    echo "✗ PHP introuvable ($PHP). Renseignez le secret PHP_BIN avec le chemin complet du binaire." >&2
    exit 1
fi
echo "▸ PHP : $("$PHP" -r 'echo PHP_VERSION;')"

# Dossiers de travail de Laravel : ils ne viennent jamais du dépôt (storage/
# est exclu de l'envoi pour préserver les fichiers déposés et les journaux),
# il faut donc s'assurer qu'ils existent sur un serveur neuf.
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions \
         storage/framework/views storage/logs bootstrap/cache
chmod -R u+rwX storage bootstrap/cache
echo "▸ Dossiers storage/ et bootstrap/cache vérifiés"

# Valeur d'une variable du .env, guillemets retirés.
valeur_env() {
    sed -nE "s/^$1=[[:space:]]*//p" .env | tail -n 1 | sed -E 's/^"(.*)"$/\1/; s/^'\''(.*)'\''$/\1/'
}

# ---------------------------------------------------------------- sauvegarde
if [ "${SANS_SAUVEGARDE:-0}" = "1" ]; then
    echo "▸ Sauvegarde : ignorée (SANS_SAUVEGARDE=1)"
else
    DOSSIER_SAUVEGARDES="${DOSSIER_SAUVEGARDES:-$RACINE/../sauvegardes-portail}"
    mkdir -p "$DOSSIER_SAUVEGARDES"

    if ! command -v mysqldump >/dev/null 2>&1; then
        echo "✗ mysqldump introuvable : impossible de sauvegarder la base avant les migrations." >&2
        echo "  Relancez avec SANS_SAUVEGARDE=1 si vous acceptez ce risque." >&2
        exit 1
    fi

    base="$(valeur_env DB_DATABASE)"
    fichier="$DOSSIER_SAUVEGARDES/${base}-$(date +%Y%m%d-%H%M%S).sql.gz"

    MYSQL_PWD="$(valeur_env DB_PASSWORD)" mysqldump \
        --no-tablespaces --skip-lock-tables --single-transaction --quick \
        -h "$(valeur_env DB_HOST)" -P "$(valeur_env DB_PORT)" -u "$(valeur_env DB_USERNAME)" \
        "$base" | gzip > "$fichier"

    echo "▸ Sauvegarde : $fichier ($(du -h "$fichier" | cut -f1))"

    # On ne garde que les 10 plus récentes.
    ls -1t "$DOSSIER_SAUVEGARDES"/*.sql.gz 2>/dev/null | tail -n +11 | xargs -r rm -f
fi

# ------------------------------------------------- racine du domaine
# N0C sert le domaine depuis ~/public_html, sans possibilité de le changer
# dans le panneau : on le fait pointer sur le dossier public/ de Laravel.
# Idempotent, et l'ancien dossier est conservé (suffixe .origine-<date>).
if [ "${LIER_RACINE_WEB:-1}" = "1" ]; then
    RACINE_WEB="${RACINE_WEB:-$HOME/public_html}"
    CIBLE="$RACINE/public"

    if [ -L "$RACINE_WEB" ] && [ "$(readlink "$RACINE_WEB")" = "$CIBLE" ]; then
        echo "▸ Racine du domaine : déjà reliée à $CIBLE"
    else
        if [ -L "$RACINE_WEB" ]; then
            rm -f "$RACINE_WEB"
        elif [ -e "$RACINE_WEB" ]; then
            mv "$RACINE_WEB" "$RACINE_WEB.origine-$(date +%Y%m%d-%H%M%S)"
        fi
        ln -s "$CIBLE" "$RACINE_WEB"
        echo "▸ Racine du domaine : $RACINE_WEB → $CIBLE"
    fi
fi

if [ "$ESSAI" = "1" ]; then
    echo "▸ Essai à blanc : ni maintenance, ni migration, ni cache."
    "$PHP" artisan migrate:status | tail -n 5 || true
    echo "✓ Prérequis vérifiés."
    exit 0
fi

# ------------------------------------------------- maintenance et migrations
# Quoi qu'il arrive ensuite, le site est remis en service.
trap '"$PHP" artisan up >/dev/null 2>&1 || true' EXIT

"$PHP" artisan down --retry=15 >/dev/null 2>&1 || true
echo "▸ Site en maintenance"

"$PHP" artisan migrate --force

[ -e public/storage ] || "$PHP" artisan storage:link || true

# Un cache de configuration périmé peut contenir des chemins absents (le
# dossier des vues compilées, par exemple, résolu à vide sur une installation
# neuve) : on le supprime avant de le reconstruire.
rm -f bootstrap/cache/config.php bootstrap/cache/events.php bootstrap/cache/routes-*.php

# optimize met en cache configuration, routes, vues et événements.
"$PHP" artisan optimize
"$PHP" artisan queue:restart >/dev/null 2>&1 || true

"$PHP" artisan up
trap - EXIT
echo "✓ Déploiement terminé, site en service."
