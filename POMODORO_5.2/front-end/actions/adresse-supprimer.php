<?php
// Recoit l'id d'une adresse depuis mon-compte.php et la supprime
// (suppression logique : les commandes passees gardent leur adresse).
//     -> ../mon-compte.php?section=adresses

session_start();

if (empty($_SESSION['client_id'])) {
    header('Location: ../connexion.php');
    exit;
}

require __DIR__ . '/../../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../../back-end/classes/Adresse.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id'])) {
    Adresse::supprimer($pdo, $_POST['id'], $_SESSION['client_id']);
}

header('Location: ../mon-compte.php?section=adresses');
exit;
