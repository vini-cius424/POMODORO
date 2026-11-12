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

2. **Configurer la connexion**

   ```bash
   cp back-end/connexion-base-de-donnees.exemple.php back-end/connexion-base-de-donnees.php
   ```

   Puis ajuster l'hôte, le port, l'utilisateur et le mot de passe dans le fichier
   copié. Ce fichier n'est pas versionné : il contient les identifiants de la
   machine sur laquelle le site tourne.

   Sous WAMP, attention au port : MariaDB écoute souvent sur **3307** tandis que
   MySQL occupe le 3306.

3. **Configurer Brevo (emails)** — facultatif

   ```bash
   cp back-end/config-brevo.exemple.php back-end/config-brevo.php
   ```

   Puis y mettre la clé API Brevo, l'adresse d'expéditeur validée dans
   Brevo, et pour la newsletter l'ID de la liste et du modèle « double
   opt-in ». Chaque réglage est expliqué dans le fichier. Comme la connexion
   à la base, ce fichier n'est pas versionné.

   Sans lui, le site fonctionne quand même : les comptes sont confirmés dès
   l'inscription et la newsletter est indiquée comme indisponible.

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
  connexion-base-de-donnees.php   connexion PDO (non versionnée)
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
