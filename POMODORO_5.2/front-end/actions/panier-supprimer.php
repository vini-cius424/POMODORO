<?php
// =====================================================================
// POMODORO STORE - Action : retirer une variante du panier
//
// Recoit le formulaire "Retirer" de panier.php, supprime la ligne,
// puis revient sur le panier avec ?panier=supprime.
// =====================================================================

require __DIR__ . '/../../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../../back-end/classes/Panier.php';

session_start();

// --- 1. Client connecte, et envoi du formulaire uniquement (POST) ---
if (empty($_SESSION['client_id'])) {
    header('Location: ../connexion.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../panier.php');
    exit;
}

$varianteId = (int) ($_POST['variante_id'] ?? 0);

// --- 2. Suppression ---
// La requete ne vise que le panier de CE client (son $panierId) :
// meme avec un identifiant bricole, il ne peut pas toucher au panier
// d'un autre client.
$panierId = Panier::idPourClient($pdo, $_SESSION['client_id']);

// Si la variante n'est pas dans son panier, il n'y a rien a retirer :
// on revient sans message plutot que d'afficher "Article retire".
if (Panier::quantiteVariante($pdo, $panierId, $varianteId) === 0) {
    header('Location: ../panier.php');
    exit;
}

Panier::supprimerLigne($pdo, $panierId, $varianteId);

header('Location: ../panier.php?panier=supprime');
exit;
