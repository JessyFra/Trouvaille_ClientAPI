# 📢 Trouvaille

> Site de petites annonces entre particuliers — projet scolaire réalisé en PHP / Symfony / Bootstrap.

---

## Sommaire

- [Présentation](#présentation)
- [Architecture](#architecture)
- [Fonctionnalités](#fonctionnalités)
- [Technologies utilisées](#technologies-utilisées)
- [Prérequis](#prérequis)
- [Installation](#installation)
  - [1. Cloner le dépôt](#1-cloner-le-dépôt)
  - [2. Base de données](#2-base-de-données)
  - [3. Configurer et lancer l'API](#3-configurer-et-lancer-lapi)
  - [4. Configurer et lancer le client](#4-configurer-et-lancer-le-client)
- [Comptes de test](#comptes-de-test)
- [Structure du projet](#structure-du-projet)
- [Documentation utilisateur](#documentation-utilisateur)

---

## Présentation

Trouvaille est une application web de petites annonces entre particuliers permettant de déposer, rechercher et gérer des offres et des demandes par catégorie et par ville.

Le projet suit une architecture **découplée** : un client PHP/HTML/CSS communique exclusivement avec une API REST Symfony via des requêtes HTTP.

---

## Architecture

```
Trouvaille/
├── trouvaille-api/       → API REST (Symfony 8, PHP 8.4, MySQL)
├── trouvaille-client/    → Client web (PHP, HTML, CSS, JavaScript)
├── trouvaille.sql        → Script SQL de création et seed de la base
└── documentation/
    └── documentation_utilisateur.pdf
```

Le client ne contient **aucune logique métier** : il se contente d'appeler l'API et d'afficher les réponses. Les deux sous-projets sont servis indépendamment.

---

## Fonctionnalités

| Fonctionnalité | Non connecté | Connecté | Admin |
|---|:---:|:---:|:---:|
| Rechercher des annonces (catégorie, ville, type, mot-clé) | ✔ | ✔ | ✔ |
| Consulter le détail d'une annonce | ✔ | ✔ | ✔ |
| Consulter le profil public d'un utilisateur | ✔ | ✔ | ✔ |
| Créer un compte | ✔ | — | — |
| Se connecter | ✔ | ✔ | ✔ |
| Déposer une annonce (offre ou demande, avec photos) | — | ✔ | ✔ |
| Modifier sa propre annonce | — | ✔ | ✔ |
| Clôturer / supprimer sa propre annonce | — | ✔ | ✔ |
| Envoyer un message à un autre utilisateur | — | ✔ | ✔ |
| Consulter sa messagerie | — | ✔ | ✔ |
| Modifier son profil (bio, nom affiché, mot de passe) | — | ✔ | ✔ |
| Modifier / clôturer / supprimer n'importe quelle annonce | — | — | ✔ |
| Bannir / débannir un compte utilisateur | — | — | ✔ |
| Accéder au panneau d'administration | — | — | ✔ |

---

## Technologies utilisées

**API (`trouvaille-api`)**
- PHP 8.4
- Symfony 8
- Doctrine ORM
- LexikJWT (authentification JWT RS256)
- NelmioApiDoc / Swagger (documentation interactive)
- MySQL 8
- Extension GD (génération de thumbnails)

**Client (`trouvaille-client`)**
- PHP 8 (rendu serveur)
- JavaScript vanilla (interactions dynamiques, polling messagerie)
- Bootstrap 5 (responsive)
- Font Awesome 6

---

## Prérequis

- **PHP** ≥ 8.2 (avec l'extension GD activée)
- **Composer**
- **MySQL** (via XAMPP ou autre)
- **OpenSSL** (pour la génération des clés JWT)

---

## Installation

### 1. Cloner le dépôt

```bash
git clone https://github.com/JessyFra/Trouvaille_ClientAPI.git
cd Trouvaille
```

---

### 2. Base de données

Ouvrez phpMyAdmin (ou un client MySQL) et importez le fichier SQL fourni : `trouvaille.sql`

Cela crée la base `trouvaille` et l'alimente avec des données de test (annonces, utilisateurs, catégories, villes).

---

### 3. Configurer et lancer l'API

#### a) Installer les dépendances

```bash
cd trouvaille-api
composer install
```

> **Note :** Ne prêtez pas attention aux avertissements éventuels.

#### b) Configurer les variables d'environnement

Copiez le fichier d'exemple et adaptez-le :

```bash
cp .env.example .env
```

Éditez `.env.local` et renseignez :

```dotenv
DATABASE_URL="mysql://root:@127.0.0.1:3306/civilipedia?serverVersion=8.0&charset=utf8mb4"
JWT_PASSPHRASE=votre_passphrase_ici
```

> **Note :** remplacez `root:` par `root:votre_mot_de_passe` si votre MySQL en a un.

#### c) Générer les clés RSA pour JWT

Créez le dossier de destination, puis générez la paire de clés :

**Sur Linux / macOS :**
```bash
mkdir -p config/jwt
openssl genpkey -algorithm RSA -out config/jwt/private.pem -aes256 -pass pass:trouvaille_jwt_passphrase
openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem -passin pass:trouvaille_jwt_passphrase
```

**Sur Windows (PowerShell) :**
```powershell
New-Item -ItemType Directory -Force config\jwt
openssl genpkey -algorithm RSA -out config/jwt/private.pem -aes256 -pass pass:your_jwt_passphrase_here
openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem -passin pass:your_jwt_passphrase_here
```

> La valeur de `your_jwt_passphrase_here` doit être identique à `JWT_PASSPHRASE` dans votre fichier d'environnement.

#### d) Générer les thumbnails manquants des images par défaut

Lancez la commande suivante pour les générer :

```bash
php bin/console app:generate-missing-thumbnails
```

> Vous pouvez d'abord faire un test à blanc avec l'option `--dry-run` pour voir ce qui sera généré sans rien modifier :
> ```bash
> php bin/console app:generate-missing-thumbnails --dry-run
> ```

#### e) Lancer le serveur de l'API

```bash
php -S localhost:3000 -t public
```

L'API est accessible sur `http://localhost:3000`.

> La documentation interactive Swagger est disponible sur `http://localhost:3000/api/doc`.

---

### 4. Configurer et lancer le client

#### a) Vérifier l'URL de l'API

Dans `trouvaille-client/.env`, vérifiez que l'URL pointe bien vers l'API :

```dotenv
API_URL=http://localhost:3000/api
```

#### b) Lancer le serveur client

```bash
cd trouvaille-client
php -S localhost:8000 -t public
```

Le client est accessible sur `http://localhost:8000`.

---

## Comptes de test

Les comptes suivants sont créés par le script SQL de seed :

| Rôle | Nom d'utilisateur | Mot de passe |
|---|---|---|
| Administrateur | `admin` | `password` |
| Utilisateur | `marie_dupont` | `password` |
| Utilisateur | `thomas_martin` | `password` |
| Utilisateur | `sophie_bd` | `password` |
| Utilisateur | `lucas_petit` | `password` |
| Utilisateur | `emma_rousseau` | `password` |
| Utilisateur | `julien_blanc` | `password` |

> L'utilisateur Julien Blanc est banni pour l'exemple.

---

## Structure du projet

```
trouvaille-api/
├── config/
│   └── jwt/                    → Clés RSA (non versionnées)
│
├── public/
│   └── uploads/
│       └── announces/          → Dossier de stockage des images des annonces
│
├── src/
│   ├── Command/                → GenerateMissingThumbnailsCommand
│   ├── Controller/             → AnnounceController, AnnounceImageController, AuthController, ...
│   ├── Entity/                 → Announce, AnnounceImage, User, ...
│   └── Repository/             → Requêtes Doctrine
│
├── .env.example                → Variables d'environnement (template)
└── .gitignore                  → Clés JWT et fichiers locaux exclus

trouvaille-client/
├── public/
│   ├── index.php               → Point d'entrée + routeur + proxies AJAX
│   └── assets/
│       ├── css/                → Styles personnalisés
│       └── img/
│
├── src/
│    ├── pages/                  → home, announce, announce_create, ...
│    ├── layout/                 → header.php, footer.php
│    ├── ApiClient.php           → Lien avec l'API
│    └── Auth.php
└── .env                         → URL de l'API

documentation/
└── documentation_utilisateur.pdf

trouvaille.sql                  → Création des tables + données de test
```

---

## Documentation utilisateur

La documentation utilisateur complète (sans extrait de code, avec captures d'écran) est disponible dans :

```
documentation/documentation_utilisateur.pdf
```

Elle décrit toutes les fonctionnalités de l'application à destination d'un utilisateur non informaticien.
