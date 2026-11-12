<?php
$versRacine = '../../'; // profondeur de ce fichier, pour la redirection du garde
require __DIR__ . '/../../../back-end/securite/garde-admin.php';
require __DIR__ . '/../../../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../../../back-end/classes/Variante.php';

$produitId = $_POST['produit_id'] ?? null;
$id = $_POST['id'] ?? null;

// Suppression logique : une variante deja commandee peut maintenant etre
// retiree, ses lignes de commande gardent leur reference.
if ($id) {
    Variante::supprimer($pdo, $id);
}

header('Location: ../produit-variantes.php?id=' . (int) $produitId);
exit;
