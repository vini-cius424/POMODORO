<?php
// =====================================================================
// POMODORO STORE - Action : modifier la quantite d'une ligne du panier
//
// Recoit le formulaire "Modifier" de panier.php, verifie la nouvelle
// quantite, puis revient sur le panier avec un message dans l'URL :
//     ?panier=modifie   quantite mise a jour
//     ?panier=stock     pas assez de stock
// =====================================================================

require __DIR__ . '/../../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../../back-end/classes/Variante.php';
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

// --- 2. Lecture du formulaire ---
$varianteId = (int) ($_POST['variante_id'] ?? 0);
$quantite   = (int) ($_POST['quantite'] ?? 0);

// --- 3. La ligne doit exister dans le panier DE CE client ---
// quantiteVariante() renvoie 0 si la variante n'est pas dans son panier :
// impossible de modifier le panier de quelqu'un d'autre.
$panierId = Panier::idPourClient($pdo, $_SESSION['client_id']);
$variante = Variante::trouver($pdo, $varianteId);

if (!$variante || Panier::quantiteVariante($pdo, $panierId, $varianteId) === 0 || $quantite < 1) {
    header('Location: ../panier.php');
    exit;
}

// --- 4. Controle du stock ---
// Ici on REMPLACE la quantite (on n'ajoute pas) : il suffit donc de
// comparer la nouvelle quantite au stock.
if ($quantite > (int) $variante->stock) {
    header('Location: ../panier.php?panier=stock');
    exit;
}

// --- 5. Tout est bon : on enregistre ---
Panier::modifierQuantite($pdo, $panierId, $varianteId, $quantite);

header('Location: ../panier.php?panier=modifie');
exit;
