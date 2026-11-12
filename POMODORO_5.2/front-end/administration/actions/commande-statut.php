<?php
// =====================================================================
// POMODORO STORE - Action : changer le statut d'une commande (tache 23)
//
// Recoit le formulaire "Modifier le statut" de commande-detail.php,
// puis revient sur la page de detail avec un message dans l'URL :
//     ?message=statut   statut modifie
//     ?message=refuse   changement refuse (statut invalide, retour en arriere,
//                       commande annulee)
// =====================================================================

$versRacine = '../../'; // profondeur de ce fichier, pour la redirection du garde
require __DIR__ . '/../../../back-end/securite/garde-admin.php';
require __DIR__ . '/../../../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../../../back-end/classes/Commande.php';

$id = (int) ($_POST['id'] ?? 0);

// Formulaire uniquement (POST) et identifiant present.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id === 0) {
    header('Location: ../commandes.php');
    exit;
}

// changerStatut() fait lui-meme tous les controles (liste blanche des
// statuts, refus de ANNULEE, pas de retour en arriere, commande deja annulee) : on n'a qu'a lire
// sa reponse, true ou false.
if (Commande::changerStatut($pdo, $id, $_POST['statut'] ?? '')) {
    header('Location: ../commande-detail.php?id=' . $id . '&message=statut');
} else {
    header('Location: ../commande-detail.php?id=' . $id . '&message=refuse');
}
exit;
