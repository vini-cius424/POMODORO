<?php
// Recoit le bouton "Marquer comme traite" / "Remettre a traiter" de
// message-detail.php, change le statut, puis revient sur le message.
$versRacine = '../../'; // profondeur de ce fichier, pour la redirection du garde
require __DIR__ . '/../../../back-end/securite/garde-admin.php';
require __DIR__ . '/../../../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../../../back-end/classes/MessageContact.php';

$id = (int) ($_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0
    && MessageContact::changerStatut($pdo, $id, $_POST['statut'] ?? '')) {
    header('Location: ../message-detail.php?id=' . $id . '&message=statut');
    exit;
}

header('Location: ../messages.php');
exit;
