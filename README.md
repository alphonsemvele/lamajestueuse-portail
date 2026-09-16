# La Majestueuse — Portail entreprise

Point d'entrée unique des employés du groupe La Majestueuse. Le portail est le
**seul endroit de l'écosystème où un mot de passe est saisi et vérifié** : les
applications métier (IFPM, GSBM, ERP NDAZOA, Fondation Médicale) n'exposent plus
de page de connexion, elles sont ouvertes depuis le tableau de bord.

## Pile technique

Laravel 12 · PHP 8.2+ · **Inertia 3 + React 19 + TypeScript** · Tailwind CSS 4
(Vite) · MySQL.

Même pile front que la Fondation Médicale (SIH-HAD), pour que les deux
applications partagent leurs conventions. Laravel 12 / PHP ^8.2 comme le reste
de la flotte, afin de se déployer sur les mêmes serveurs.

### Organisation du front

```
resources/js/
  app.tsx            point d'entrée Inertia
  pages/             une page = une route (auth/, admin/, dashboard, post)
  layouts/           portal-layout, admin-layout
  components/        icon, logo, app-card, ui, pagination, bascules thème/langue
  lib/utils.ts       cn(), traduction, table des routes
  types/             types partagés
```

Les contrôleurs renvoient `Inertia::render('chemin/page', [...])`. Les modèles
exposent une méthode `toUiArray()` : on ne transmet à React que ce qui est
affiché, jamais l'entité Eloquent complète.

## Installation

```bash
composer install
npm install && npm run build
cp .env.example .env && php artisan key:generate
# renseigner DB_DATABASE / DB_USERNAME / DB_PASSWORD
php artisan migrate --seed
php artisan storage:link   # requis pour afficher les visuels televerses
php artisan serve
```

## Comptes de démonstration

Créés par le seeder. **À supprimer avant toute mise en production.**

| Compte | Identifiant | Mot de passe | Rôle |
|---|---|---|---|
| Alphonse MVELE | `alphonsemvele95@gmail.com` ou `LM-0001` | `Majestueuse@2026` | Administrateur |
| Elvin YONDOUA | `elvin.yondoua@lamajestueuse.cm` | `Majestueuse@2026` | Employé (IFPM) |
| Sandrine ABENA | `sandrine.abena@lamajestueuse.cm` | `Majestueuse@2026` | Employé (ERP NDAZOA) |
| Joseph NKOLO | `joseph.nkolo@lamajestueuse.cm` | `Majestueuse@2026` | Employé (Fondation) |

La connexion accepte l'adresse professionnelle **ou** le matricule.

## Structure fonctionnelle

### Côté employé

- `/connexion` — l'unique page de connexion.
- `/` — tableau de bord : applications autorisées, liens rapides, pointage,
  centre d'information (actualités / annonces / affichage).
- `/applications/{slug}/ouvrir` — sortie vers l'application métier. Vérifie les
  droits, journalise l'accès, puis redirige.

### Côté administration (`/admin`, réservé au rôle `admin`)

- **Applications** — déclaration d'un projet et de son **lien de redirection**,
  catégorie, apparence de la tuile, activation, identifiant client SSO.
  L'**image de couverture** et le **logo** se téléversent depuis le formulaire
  (voir « Images » plus bas).
- **Accès** — qui peut ouvrir quelle application, et avec quel rôle.
- **Utilisateurs** — création des comptes. C'est le seul endroit où un compte
  employé est créé ; les applications métier n'en créent plus.
- **Catégories**, **Publications**, **Journal d'accès**.

## Images : une seule mécanique partout

Tout ce qui porte une image se téléverse depuis le formulaire, avec aperçu
immédiat et retrait :

| Élément | Dossier de stockage |
|---|---|
| Application — image de couverture | `applications/couvertures/` |
| Application — logo | `applications/logos/` |
| Employé — photo de profil | `utilisateurs/photos/` |
| Publication — image | `publications/` |

Chaque champ accepte aussi une **URL externe** en repli, saisie sous la zone de
téléversement. Les visuels du bandeau de connexion restent des fichiers déposés
dans `public/images/hero/` (voir plus bas).

Le comportement est identique partout : remplacer une image efface l'ancien
fichier, supprimer l'élément efface la sienne, et les visuels livrés avec le
dépôt (`public/images/...`) ne sont jamais supprimés. Le SVG est refusé — servi
depuis notre propre domaine, il peut embarquer du script.

Côté code, un trait `Http\Controllers\Concerns\HandlesMediaUploads` et un
composant `components/media-field` portent cette logique une seule fois.

## Photos de profil

La **photo est obligatoire à l'inscription** (JPG, PNG ou WebP, 4 Mo maximum).
Elle est stockée sur le disque `public` sous `utilisateurs/photos/` et remplace
les initiales partout où l'employé apparaît : en-têtes du portail et de
l'administration, liste des employés, écran d'affectation des accès.

Un administrateur peut la remplacer ou la retirer depuis la fiche employé, où
elle reste facultative — un compte créé à la main peut n'en avoir aucune, les
initiales prennent alors le relais.

## Affichage d'une publication

Deux notions séparées :

| Champ | Rôle |
|---|---|
| **Affichage sur le portail** (`is_visible`) | L'interrupteur Actif / Inactif. Inactif, la publication disparaît partout et son lien direct renvoie 404. |
| **Date de publication** (`published_at`) | Depuis quand elle est parue. Vide = brouillon, date future = programmée. |

Quatre états en découlent, distingués dans les listes : **Actif**, **Inactif**,
**Brouillon** (aucune date) et **Programmée** (date future).

Le bouton Actif / Inactif est cliquable directement dans la liste
d'administration et sur les cartes du module, sans ouvrir la fiche. Basculer sur
Inactif **conserve la date de publication** — c'est tout l'intérêt par rapport à
l'ancien mécanisme, où masquer obligeait à effacer la date.

Une publication n'est visible d'un employé que si les trois conditions sont
réunies : interrupteur actif, date posée, date passée.

## Modules internes

Un **module** est une application rendue par le portail lui-même : il reçoit une
tuile comme n'importe quelle autre application, mais l'ouvrir mène à une route
interne au lieu d'un site extérieur.

Les modules disponibles se déclarent dans `config/modules.php`, puis s'ajoutent
depuis `/admin/applications` en choisissant le type « Module du portail ». Le
champ URL est alors remplacé par un sélecteur de module.

Le seul module livré est le **Centre d'information** (`/informations`) :
consultation des actualités, annonces et affichage, avec filtres par rubrique et
recherche. La publication est réservée aux employés dont le `role_in_app` sur ce
module figure dans `manage_roles` (`admin`, `editeur`, `redacteur`) — plus les
administrateurs du portail, toujours. Les brouillons ne sont visibles que d'eux.

Le second module est l'**Annuaire** (`/annuaire`) : recherche du personnel par
nom, matricule, e-mail ou téléphone, affinable par institut et par poste, triable
par nom, matricule, entité ou poste. Il n'expose que des informations de contact
professionnelles — `User::toDirectoryArray()` est la seule porte de sortie, et un
test vérifie que rien d'autre ne transite. Seuls les comptes **actifs**
apparaissent, et la liste ne s'affiche qu'après une recherche ou un filtre.

Le champ de tri vient de l'URL : il est restreint à une liste blanche, jamais
utilisé comme nom de colonne libre.

Pour ajouter un module : une entrée dans `config/modules.php`, un contrôleur
sous `app/Http/Controllers/Modules/`, ses routes, et ses pages sous
`resources/js/pages/modules/`.

## Modèle de données

| Table | Rôle |
|---|---|
| `users` | Identité seule : nom, matricule, poste, entité, rôle portail, statut. |
| `applications` | Nom, description, **`url`** (lien de redirection), catégorie, type (`application` / `quick_link` / `module`), `module_key` pour les modules, `cover`, `logo`, apparence, `client_id` réservé au SSO. |
| `application_user` | Qui accède à quoi, avec quel `role_in_app`, compteur d'ouvertures. |
| `categories` | Regroupement des applications sur le tableau de bord. |
| `posts` | Centre d'information : actualités, annonces, affichage. |
| `check_ins` | Pointage quotidien. |
| `access_logs` | Connexions, déconnexions, ouvertures d'application. |

## Connexion directe aux applications

Une application dotée d'un **identifiant client** et d'un **secret partagé** ne
s'ouvre plus sur sa page d'accueil : le portail lui remet une identité signée, et
l'employé arrive directement dans son espace, sans mot de passe.

**Le jeton** est un JWT `HS256` signé avec le secret de l'application, valable
**60 secondes**, utilisable **une seule fois** (un `jti` que l'application
retient). Il porte l'identité professionnelle — matricule, nom, adresse,
téléphone, poste, entité, photo — plus deux informations décisives :

| Champ | Rôle |
|---|---|
| `role` | Le rôle de l'employé **dans cette application** (`application_user.role_in_app`). C'est lui qui détermine où il atterrit. |
| `reference` | Son identifiant **local** (`application_user.reference_locale`), pour retrouver son compte existant au lieu d'en créer un autre. |

Jamais de mot de passe. Le secret est chiffré en base et n'est jamais renvoyé à
l'interface — seul un booléen `hasClientSecret` indique s'il existe.

Chaque application déclare les rôles qu'elle accepte (`applications.roles`) ;
l'écran « Gérer les accès » propose alors une liste au lieu d'une saisie libre,
et un rôle inconnu est refusé.

### Raccorder une application

1. `/admin/applications` → l'application → renseigner l'**identifiant client**,
   **générer un secret**, et lister les **rôles acceptés**.
2. Dans le `.env` de l'application : `PORTAIL_URL`, `PORTAIL_CLIENT_ID`,
   `PORTAIL_CLIENT_SECRET`, `PORTAIL_FALLBACK_LOGIN=false`.
3. Y ajouter `config/portail.php`, le contrôleur `PortalSignOnController`, la
   route `GET /sso/portail`, et renvoyer `/login` et `/` vers le portail.
4. Dans « Gérer les accès », donner à chaque employé son **rôle** et son
   **matricule local**.

**IUM est raccordée** et sert de modèle (`erpndazoa/`). IFPM et GSBM restent à
faire ; l'accès de secours `PORTAIL_FALLBACK_LOGIN=true` rouvre le formulaire
local si le portail est indisponible.

## Bascule SSO complète (étape suivante)

Le portail est aujourd'hui un **annuaire d'applications** : il vérifie les droits
puis redirige. Le champ `applications.client_id` et la colonne
`application_user.role_in_app` sont déjà en place pour le branchement OIDC.

Ce qu'il restera à faire, sans changer ce modèle de données :

1. Installer Laravel Passport ici et déclarer un client OAuth par application.
2. Dans `ApplicationLaunchController`, construire l'URL `/oauth/authorize`
   au lieu de rediriger directement vers `applications.url`.
3. Publier le paquet `obm/portal-client` que chaque application métier
   installera (driver Socialite, callback, provisioning local, déconnexion).

Prérequis inchangé : rétro-générer les migrations manquantes des trois bases
instituts avant de toucher à leur table `users`.

## Bandeau de connexion

Les quatre photos du groupe se trouvent dans `public/images/hero/`
(`ecole-cour`, `ecole-aire-de-jeux`, `fondation-facade`, `fondation-accueil`).
Elles défilent automatiquement toutes les 6,5 s dans `components/hero-carousel`.

Pour en ajouter ou en retirer une : déposer le fichier dans ce dossier et
modifier le tableau `slides` en tête de `pages/auth/login.tsx`. Le composant
s'adapte au nombre de vues, et masque les puces s'il n'y en a qu'une.

Le défilement se met en pause au survol et quand l'onglet passe en
arrière-plan ; il est entièrement désactivé si le système demande à réduire les
animations (`prefers-reduced-motion`).

## Langues et thème

Interface disponible en français (défaut) et en anglais — `lang/en.json`. Le
dictionnaire est transmis à React par le middleware Inertia ; côté composant on
appelle `useT()`, dont la clé est la chaîne française. Le
choix effectué sur la page de connexion l'emporte sur la préférence du compte et
la met à jour. Thème clair / sombre / système, mémorisé par navigateur.

Le **contenu** (noms d'applications, descriptions, actualités) n'est pas traduit :
ce sont des données saisies par l'administration.

## Tests

```bash
php artisan test      # 113 tests
npx tsc --noEmit      # vérification des types
npm run build         # compilation du front
```

113 tests couvrent l'authentification, l'inscription du personnel (photo
obligatoire comprise) et sa validation, l'isolation des accès, la redirection vers les applications, les
modules internes et leurs droits de publication, l'administration des projets et
des comptes, et le téléversement des visuels. Les pages sont vérifiées par
`assertInertia` (composant rendu et props transmises).
