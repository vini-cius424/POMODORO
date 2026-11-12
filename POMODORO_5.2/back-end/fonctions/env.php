<?php
// =====================================================================
// POMODORO STORE - Lecture du fichier .env
//
// Toutes les valeurs secretes (cle Brevo, cle Stripe, mot de passe de
// la base) sont rangees dans UN seul fichier : ".env", a la racine du
// projet. Il n'est jamais versionne (voir .gitignore) ; le modele a
// copier est ".env.exemple", lui versionne et sans aucune vraie valeur.
//
// Le .env est hors de front-end/ : aucune URL ne permet de le lire.
//
// Format, une valeur par ligne :
//     NOM=valeur
//     NOM="valeur avec espaces"
//     # une ligne qui commence par # est un commentaire
//
// Une vraie variable d'environnement (definie par le serveur) l'emporte
// sur le .env : pratique sur un hebergement qui fournit ses propres
// variables.
//
// Pas de Composer ni de librairie : quelques lignes suffisent.
// =====================================================================

// Lit le .env une seule fois (static) et renvoie un tableau NOM => valeur.
// Tableau vide si le fichier n'existe pas : le site tourne alors avec
// les valeurs par defaut (Brevo et Stripe desactives).
function env_charger()
{
    static $valeurs = null;

    if ($valeurs !== null) {
        return $valeurs;
    }

    $valeurs = [];
    $fichier = __DIR__ . '/../../.env';

    if (!is_readable($fichier)) {
        return $valeurs;
    }

    foreach (file($fichier, FILE_IGNORE_NEW_LINES) as $ligne) {
        $ligne = trim($ligne);

        // Ligne vide, commentaire, ou ligne sans "=" : on l'ignore.
        if ($ligne === '' || $ligne[0] === '#' || strpos($ligne, '=') === false) {
            continue;
        }

        [$nom, $valeur] = explode('=', $ligne, 2);
        $nom = trim($nom);
        $valeur = trim($valeur);

        // Guillemets autour de la valeur : on les retire.
        $longueur = strlen($valeur);
        if ($longueur >= 2
            && ($valeur[0] === '"' || $valeur[0] === "'")
            && $valeur[$longueur - 1] === $valeur[0]) {
            $valeur = substr($valeur, 1, -1);
        }

        $valeurs[$nom] = $valeur;
    }

    return $valeurs;
}

// Renvoie la valeur de $nom : variable d'environnement du serveur
// d'abord, puis le .env, sinon $defaut.
function env($nom, $defaut = '')
{
    $systeme = getenv($nom);
    if ($systeme !== false) {
        return $systeme;
    }

    $valeurs = env_charger();

    return array_key_exists($nom, $valeurs) ? $valeurs[$nom] : $defaut;
}
