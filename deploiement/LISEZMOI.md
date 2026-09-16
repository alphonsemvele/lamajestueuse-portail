# Mise en ligne du portail sur lamajestueuse.com

Hébergement : **PlanetHoster** (serveur LiteSpeed, panneau N0C).
Dépôt : `alphonsemvele/lamajestueuse-portail`, branche `main`.

## Ce que fait le pipeline

À chaque poussée sur `main` (ou depuis l'onglet **Actions → Déploiement → Run workflow**) :

1. **Tests** — dépendances, vérification des types TypeScript, compilation des assets, 125 tests.
2. **Compilation** — dépendances PHP de production (sans les outils de développement) et assets Vite.
3. **Envoi** — copie des fichiers en SSH (`rsync`). Ne sont jamais touchés sur le serveur :
   `.env`, le dossier `storage/` (fichiers déposés, journaux, sessions) et `public/storage`.
4. **Sur le serveur** — sauvegarde de la base, mise en maintenance, migrations, mise en cache
   (configuration, routes, vues), remise en service.
5. **Vérification** — le pipeline échoue si `https://lamajestueuse.com/up` ne répond pas.

Si une étape échoue, le déploiement s'arrête et le site reste dans son état précédent.

## 1. À préparer une seule fois sur PlanetHoster

1. **Activer l'accès SSH** dans le panneau N0C et noter l'hôte, l'utilisateur et le port.
2. **Autoriser la clé de déploiement** : coller le contenu de `~/.ssh/lamajestueuse-deploiement.pub`
   (clé publique, générée sur ton Mac) dans les clés SSH autorisées du compte.
3. **Choisir PHP 8.2 ou plus** (8.3 ou 8.4 de préférence) avec les extensions
   `pdo_mysql`, `mbstring`, `intl`, `zip`, `bcmath`, `gd`.
4. **Créer la base de données** MySQL et son utilisateur.
5. **Créer le dossier de l'application**, hors du dossier public — par exemple :
   `/home/<utilisateur>/lamajestueuse-portail`
6. **Racine du domaine** : rien à faire. Le panneau N0C ne permet pas de la changer
   pour le domaine principal (elle est figée sur `/public_html`), donc le déploiement
   relie lui-même `~/public_html` au dossier `public/` de l'application, en conservant
   l'ancien dossier sous `public_html.origine-<date>`.
   Pour désactiver ce comportement : `LIER_RACINE_WEB=0`. Solution de repli si les liens
   symboliques sont refusés : `index-public_html.php` (voir son en-tête).
7. **Déposer le fichier `.env`** dans le dossier de l'application, à partir de
   `env-production.exemple`, puis :
   ```
   cd ~/lamajestueuse-portail
   php artisan key:generate      # renseigne APP_KEY
   chmod -R 775 storage bootstrap/cache
   ```
8. **Importer les données** depuis ton poste (applications, comptes, accès) :
   ```
   mysqldump -u root lamajestueuse_portail --skip-routines --skip-triggers > portail.sql
   scp portail.sql <utilisateur>@<hôte>:~/
   ssh <utilisateur>@<hôte> "mysql -u <bd_user> -p <bd_nom> < ~/portail.sql"
   ```

## 2. Secrets à créer sur GitHub

**Settings → Secrets and variables → Actions → New repository secret**

| Secret | Obligatoire | Exemple | Rôle |
|---|---|---|---|
| `SSH_HOST` | oui | `node42.n0c.com` | serveur SSH de PlanetHoster |
| `SSH_USER` | oui | `lamajes` | utilisateur SSH du compte |
| `SSH_KEY` | oui | *(clé privée complète)* | contenu de `~/.ssh/lamajestueuse-deploiement` |
| `APP_PATH` | oui | `/home/lamajes/lamajestueuse-portail` | dossier de l'application sur le serveur |
| `SSH_PORT` | non | `5022` | seulement si le port n'est pas 22 |
| `PHP_BIN` | non | `/opt/alt/php83/usr/bin/php` | seulement si `php` en SSH n'est pas la bonne version |

La clé privée se colle en entier, de `-----BEGIN…` à `-----END…`, retour à la ligne final compris.

## 3. Premier déploiement

Pousser sur `main`, ou lancer **Actions → Déploiement → Run workflow**.
Suivre le déroulé dans l'onglet Actions : chaque étape est nommée en français.

## 4. Après la mise en ligne

Les adresses locales doivent devenir les adresses publiques :

- **Dans le portail** (Administration → Applications) : remplacer `http://127.0.0.1:8002`, `:8003`, `:8004`
  par `https://ium-ndazoa.com`, `https://ifpm-ndazoa.com`, `https://gsbm-ndazoa.com`.
- **Dans chaque application** (`.env`) : `PORTAIL_URL=https://lamajestueuse.com`.
  `PORTAIL_CLIENT_ID` et `PORTAIL_CLIENT_SECRET` restent ceux enregistrés dans le portail.

Sans cela, la connexion unique et la synchronisation des rôles continuent de viser ton poste.

## 5. Revenir en arrière

- **Le code** : `git revert <commit>` puis pousser — le pipeline redéploie la version précédente.
- **La base** : les sauvegardes sont sur le serveur dans `~/sauvegardes-portail/`
  (les 10 dernières, compressées).
  ```
  gunzip -c ~/sauvegardes-portail/<fichier>.sql.gz | mysql -u <bd_user> -p <bd_nom>
  ```

## 6. Si quelque chose ne va pas

| Symptôme | Cause la plus fréquente |
|---|---|
| Page PlanetHoster ou 404 partout | la racine du domaine ne pointe pas sur `.../public` |
| Erreur 500 au premier chargement | `.env` absent, `APP_KEY` vide, ou `storage/` non inscriptible |
| `mysqldump introuvable` | lancer le script avec `SANS_SAUVEGARDE=1`, ou demander l'outil au support |
| `PHP introuvable` | renseigner le secret `PHP_BIN` avec le chemin complet du binaire |
| Le site reste en maintenance | `php artisan up` dans le dossier de l'application |

Essai à blanc sur le serveur (vérifie les prérequis et sauvegarde, sans rien modifier) :

```
bash ~/lamajestueuse-portail/deploiement/apres-deploiement.sh --essai
```
