<?php
$versRacine = '../../'; // profondeur de ce fichier, pour la redirection du garde
require __DIR__ . '/../../../back-end/securite/garde-admin.php';
require __DIR__ . '/../../../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../../../back-end/classes/Avis.php';

// Suppression logique : l'avis reste en base, il disparait du site.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id'])) {
    Avis::supprimer($pdo, $_POST['id']);
}

header('Location: ../avis.php');
exit;
