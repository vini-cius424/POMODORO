<?php
// =====================================================================
// POMODORO STORE - Modele de connexion a la base de donnees
//
// Copier ce fichier en "connexion-base-de-donnees.php" dans le meme dossier, puis y
// mettre les identifiants de la machine :
//     cp back-end/connexion-base-de-donnees.exemple.php back-end/connexion-base-de-donnees.php
//
// Ce fichier n'est pas versionne : chaque poste a ses propres
// identifiants, et ils n'ont rien a faire dans le depot.
//
// PORT : sous WAMP, MariaDB ecoute souvent sur 3307 tandis que MySQL
// occupe le 3306. MariaDB est le bon choix ici : contrairement a
// MySQL 5.7, il applique reellement les contraintes CHECK du script de
// creation.
// =====================================================================

$hote = "127.0.0.1";
$port = 3307;
$base = "pomodoro";
$utilisateur = "root";
$motDePasse = "";

// charset=utf8mb4 : sans lui, les accents arrivent deformes en base.
$pdo = new PDO(
    "mysql:host=$hote;port=$port;dbname=$base;charset=utf8mb4",
    $utilisateur,
    $motDePasse
);

// Sans cette ligne, une requete qui echoue ne dit rien et renvoie false.
// Avec elle, PHP affiche une erreur explicite.
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
