<?php
$versRacine = '../../'; // profondeur de ce fichier, pour la redirection du garde
require __DIR__ . '/../../../back-end/securite/garde-admin.php';
require __DIR__ . '/../../../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../../../back-end/classes/Avis.php';

// Un avis est publie des son depot : cet ecran sert a la moderation a
// posteriori. Un seul bouton pour les deux sens, retirer de la boutique
// ou remettre en ligne. "sens" est verifie contre une liste fermee,
// jamais utilise tel quel.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id'])) {
    $sens = $_POST['sens'] ?? 'valider';

    if ($sens === 'retirer') {
        Avis::invalider($pdo, $_POST['id']);
    } elseif ($sens === 'valider') {
        Avis::valider($pdo, $_POST['id']);
    }
}

header('Location: ../avis.php');
exit;
