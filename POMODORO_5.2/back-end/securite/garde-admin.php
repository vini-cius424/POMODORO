<?php
// A inclure en haut de chaque page reservee a l'admin connecte.
// Centralise la verification pour ne pas la recopier (et risquer de
// l'oublier) sur chaque nouvelle page admin.
// Un client connecte n'a pas d'admin_id en session : meme s'il est
// passe par la meme page de connexion, il est renvoye ici aussi.
//
// $versRacine = chemin vers la racine du site depuis la page qui inclut
// ce garde : '../' depuis front-end/administration/, '../../' depuis
// front-end/administration/actions/. Meme principe que $prefixe dans
// morceaux-de-page/entete.php.

session_start();

if (empty($_SESSION['admin_id'])) {
    header('Location: ' . ($versRacine ?? '../') . 'connexion.php');
    exit;
}
