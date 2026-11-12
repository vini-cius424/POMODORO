# Pomodoro Store

Boutique de vêtements en ligne réalisée dans le cadre du projet ID04 (ESIG).
Site public (catalogue, fiche produit, compte client) et back-office
d'administration, en PHP et MariaDB, sans framework.

## Fonctionnalités

**Côté boutique**
- Page d'accueil avec sections qui se révèlent au défilement
- Catalogue filtrable sans rechargement de page : recherche texte, rayon, type,
  taille, couleur, plage de prix, promotions, nouveautés, tri et pagination
- Fiche produit avec galerie par couleur et tailles réellement disponibles
- Avis clients (note et commentaire), affichés une fois validés
- Création de compte et connexion

**Côté administration**
- Tableau de bord (produits, catégories, stock, ruptures)
- Gestion des produits : création, modification, publication, suppression
- Gestion des variantes taille/couleur et de leur stock
- Gestion des photos : ajout, réordonnancement, choix de la photo principale
- Gestion des catégories

## Prérequis

- **PHP 8.0+** (le projet est développé sous 8.1)
- **MariaDB 10.2.1+** ou **MySQL 8.0.16+**

La version du SGBD n'est pas un détail : en dessous de ces versions, les
contraintes `CHECK` du script de création sont acceptées puis **ignorées
silencieusement**. Vérifier avec `SELECT VERSION();`.

## Installation

1. **Créer la base**

   Dans phpMyAdmin, créer une base `pomodoro` puis exécuter
   `base-de-donnees/POMODORO - ID04 - CreationTables.sql`.
   Le script est rejouable : il supprime les tables existantes avant de les recréer.

2. **Créer le fichier `.env` (secrets)**

   ```bash
   cp .env.exemple .env
   ```

   Tous les secrets du site sont dans ce seul fichier, à la racine du projet :
   identifiants de la base, clé API Brevo, clé secrète Stripe. Chaque réglage est
   expliqué dans `.env.exemple`.

   **Le `.env` n'est jamais versionné** (il est dans le `.gitignore`) et se trouve
   hors de `front-end/`, donc aucune URL ne permet de le lire. Seul le modèle
   `.env.exemple`, sans aucune vraie valeur, est dans le dépôt.

   - **Base de données** : ajuster `DB_HOTE`, `DB_PORT`, `DB_UTILISATEUR`,
     `DB_MOT_DE_PASSE`. Sous WAMP, attention au port : MariaDB écoute souvent sur
     **3307** tandis que MySQL occupe le 3306.
   - **Brevo (emails)** — facultatif : `BREVO_CLE_API`, l'adresse d'expéditeur
     validée dans Brevo et, pour la newsletter, l'ID de la liste et du modèle
     « double opt-in ». Sans clé, le site fonctionne quand même : les comptes sont
     confirmés dès l'inscription et la newsletter est indiquée comme indisponible.
   - **Stripe (paiement)** — facultatif : `STRIPE_CLE_SECRETE`, en mode test
     uniquement (`sk_test_…`). Sans clé, la commande est enregistrée sans paiement.

   Si une clé a été commitée par erreur, la supprimer du dépôt ne suffit pas :
   elle reste dans l'historique git. Il faut la **révoquer** chez Brevo ou Stripe
   et en générer une nouvelle.

4. **Créer un compte administrateur**

   ```bash
   php back-end/creer-admin.php
   ```

   Le script demande nom, prénom, email et mot de passe (8 caractères minimum),
   et n'enregistre que le hash du mot de passe.

5. **Lancer le site**

   La racine web est `front-end/`. Sous WAMP, pointer le virtual host sur ce
   dossier. Pour un essai rapide :

   ```bash
   php -S localhost:8000 -t front-end
   ```

## Organisation des fichiers

Une seule question pour savoir où va un fichier : **est-ce que ça s'affiche
dans le navigateur ?** Oui → `front-end/`, non → `back-end/`.

```
back-end/                     le traitement : aucune ligne de HTML
  classes/                    classes métier, une par table (Active Record)
  fonctions/                  couleurs, icônes, filtres de la boutique
  securite/                   gardes d'accès admin et client
  connexion-base-de-donnees.php   connexion PDO (identifiants lus dans le .env)
  fonctions/env.php           lecture du fichier .env
  creer-admin.php             script en ligne de commande

front-end/                    l'affichage : c'est la racine web
  index.php, boutique.php, fiche-produit.php, connexion.php,
  inscription.php, deconnexion.php, mon-compte.php
  boutique-filtrer.php        fragment HTML renvoyé en AJAX
  pages-infos/                cgv, faq, livraison, retours, mentions légales...
  morceaux-de-page/           en-tête, pied, menu admin, carte et grille produits
  administration/             back-office (protégé par securite/garde-admin.php)
    actions/                  scripts qui enregistrent puis redirigent
  styles/  javascript/  images/  videos/
  photos-produits/            photos envoyées par les admins (non versionnées)

base-de-donnees/              script de création des tables
documents/                    documentation du projet
```

Le dossier `back-end/` est **hors de la racine web** : il n'est pas atteignable
par URL, seulement par `require`. Seule exception à la règle : les fichiers de
`administration/actions/` sont du traitement sans affichage, mais ils doivent
rester dans `front-end/` parce qu'un formulaire doit pouvoir les atteindre par
une URL.

## Choix techniques

- **Active Record** : chaque classe porte ses données et ses requêtes SQL.
- **Requêtes préparées PDO partout** : aucune valeur venue de l'utilisateur n'est
  concaténée dans le texte d'une requête.
- **Mots de passe hachés** avec `password_hash()`, jamais stockés en clair.
- **Filtres partagés** : `back-end/fonctions/filtres-boutique.php` est utilisé à la
  fois par `boutique.php` (premier chargement, fonctionne sans JavaScript) et par
  `boutique-filtrer.php` (mises à jour AJAX), pour que les deux interprètent la
  requête exactement de la même façon.
- **Huit règles de gestion** (RG1 à RG8) ne sont pas exprimables par une contrainte
  de table et relèvent de la couche PHP ou de triggers. Elles sont listées en tête
  du script SQL.
