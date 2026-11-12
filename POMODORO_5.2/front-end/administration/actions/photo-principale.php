<?php
$versRacine = '../../'; // profondeur de ce fichier, pour la redirection du garde
require __DIR__ . '/../../../back-end/securite/garde-admin.php';
require __DIR__ . '/../../../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../../../back-end/classes/ImageProduit.php';

$imageId = $_POST['id'] ?? null;
$produitId = $_POST['produit_id'] ?? null;

if ($imageId && $produitId) {
    ImageProduit::definirPrincipale($pdo, $produitId, $imageId);
}

header('Location: ../produit-photos.php?id=' . (int) $produitId);
exit;
