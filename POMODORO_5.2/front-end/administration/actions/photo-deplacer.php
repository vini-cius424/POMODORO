<?php
$versRacine = '../../'; // profondeur de ce fichier, pour la redirection du garde
require __DIR__ . '/../../../back-end/securite/garde-admin.php';
require __DIR__ . '/../../../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../../../back-end/classes/ImageProduit.php';

$produitId = $_POST['produit_id'] ?? null;
$imageId = $_POST['id'] ?? null;
$sens = $_POST['sens'] ?? '';

if ($produitId && $imageId && in_array($sens, ['haut', 'bas'], true)) {
    ImageProduit::deplacer($pdo, $produitId, $imageId, $sens);
}

header('Location: ../produit-photos.php?id=' . (int) $produitId);
exit;
