<?php
$versRacine = '../../'; // profondeur de ce fichier, pour la redirection du garde
require __DIR__ . '/../../../back-end/securite/garde-admin.php';
require __DIR__ . '/../../../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../../../back-end/classes/Produit.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id'])) {
    Produit::basculerActif($pdo, $_POST['id']);
}

header('Location: ../produits.php');
exit;
