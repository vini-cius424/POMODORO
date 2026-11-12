<?php
// =====================================================================
// POMODORO STORE - Action : annuler une commande (tache 23)
//
// Recoit le bouton "Annuler la commande" de commande-detail.php.
// Commande::annuler() passe le statut a ANNULEE et remet les articles
// en stock, uniquement si la commande n'est pas encore expediee.
//     ?message=annulee      commande annulee, stock rendu
//     ?message=non-annulable  deja expediee, livree ou annulee
// =====================================================================

$versRacine = '../../'; // profondeur de ce fichier, pour la redirection du garde
require __DIR__ . '/../../../back-end/securite/garde-admin.php';
require __DIR__ . '/../../../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../../../back-end/classes/Commande.php';

$id = (int) ($_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id === 0) {
    header('Location: ../commandes.php');
    exit;
}

if (Commande::annuler($pdo, $id)) {
    header('Location: ../commande-detail.php?id=' . $id . '&message=annulee');
} else {
    header('Location: ../commande-detail.php?id=' . $id . '&message=non-annulable');
}
exit;
