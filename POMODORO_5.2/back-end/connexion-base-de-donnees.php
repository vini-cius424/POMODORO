<?php
// =====================================================================
// POMODORO STORE - Connexion a la base de donnees
//
// Les identifiants ne sont PAS ecrits ici : ils viennent du fichier
// .env a la racine du projet (modele : .env.exemple). Ce fichier-ci ne
// contient donc aucun secret et peut etre versionne.
// =====================================================================

require_once __DIR__ . '/fonctions/env.php';

$hote = env('DB_HOTE', '127.0.0.1');
$port = (int) env('DB_PORT', '3307');
$base = env('DB_BASE', 'pomodoro');
$utilisateur = env('DB_UTILISATEUR', 'root');
$motDePasse = env('DB_MOT_DE_PASSE');

// charset=utf8mb4 : sans lui, les accents arrivent deformes en base.
$pdo = new PDO(
    "mysql:host=$hote;port=$port;dbname=$base;charset=utf8mb4",
    $utilisateur,
    $motDePasse
);

// Sans cette ligne, une requete qui echoue ne dit rien et renvoie false.
// Avec elle, PHP affiche une erreur explicite.
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
