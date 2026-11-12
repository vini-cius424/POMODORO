<?php
// =====================================================================
// POMODORO STORE - Messages de contact (administration)
//
// Les messages envoyes par le formulaire front-end/contact.php, du plus
// recent au plus ancien. Des onglets permettent de ne garder qu'un
// statut : messages.php?statut=NOUVEAU
// Un message nouveau est en gras : il n'a pas encore ete ouvert.
// =====================================================================

require __DIR__ . '/../../back-end/securite/garde-admin.php';
require __DIR__ . '/../../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../../back-end/classes/MessageContact.php';

$statutChoisi = $_GET['statut'] ?? null;
if (!array_key_exists((string) $statutChoisi, MessageContact::STATUTS)) {
    $statutChoisi = null;
}

$messages = MessageContact::listePourAdmin($pdo, $statutChoisi);
$nombres = MessageContact::nombreParStatut($pdo);
$nombreTotal = array_sum($nombres);

// Apercu du message dans la liste : les 90 premiers caracteres.
function apercu($texte)
{
    $texte = preg_replace('/\s+/', ' ', $texte);
    return mb_strlen($texte) > 90 ? mb_substr($texte, 0, 90) . '…' : $texte;
}

$ongletActif = 'messages';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Messages - Admin Pomodoro Store</title>
    <link rel="stylesheet" href="../styles/pomodoro.css?v=62">
</head>
<body>
<div class="admin-shell">
    <?php require __DIR__ . '/../morceaux-de-page/menu-admin.php'; ?>

    <main class="admin-main">
        <div class="admin-topbar">
            <div>
                <h1 class="titre">Messages</h1>
                <p class="sous-titre">Les messages envoyés par le formulaire de contact du site.</p>
            </div>
        </div>

        <nav class="filtres-commandes">
            <a class="filtre-categorie <?= $statutChoisi === null ? 'filtre-categorie-actif' : '' ?>"
               href="messages.php">Tous (<?= $nombreTotal ?>)</a>
            <?php foreach (MessageContact::STATUTS as $code => $libelle): ?>
                <a class="filtre-categorie <?= $statutChoisi === $code ? 'filtre-categorie-actif' : '' ?>"
                   href="messages.php?statut=<?= $code ?>"><?= $libelle ?> (<?= $nombres[$code] ?>)</a>
            <?php endforeach; ?>
        </nav>

        <div class="carte-admin">
            <div class="tableau-scroll">
                <table class="tableau-admin tableau-messages">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>De</th>
                            <th>Sujet</th>
                            <th>Message</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($messages as $msg): ?>
                            <tr class="<?= $msg['MSG_STATUT'] === 'NOUVEAU' ? 'message-non-lu' : '' ?>">
                                <td><?= date('d.m.Y H:i', strtotime($msg['MSG_DATE'])) ?></td>
                                <td>
                                    <?= htmlspecialchars($msg['MSG_NOM']) ?><br>
                                    <span class="tableau-detail"><?= htmlspecialchars($msg['MSG_EMAIL']) ?></span>
                                </td>
                                <td><?= htmlspecialchars(MessageContact::SUJETS[$msg['MSG_SUJET']] ?? $msg['MSG_SUJET']) ?></td>
                                <td class="message-apercu"><?= htmlspecialchars(apercu($msg['MSG_MESSAGE'])) ?></td>
                                <td>
                                    <span class="badge badge-message-<?= strtolower($msg['MSG_STATUT']) ?>">
                                        <?= MessageContact::STATUTS[$msg['MSG_STATUT']] ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <a class="lien-action" href="message-detail.php?id=<?= (int) $msg['MSG_ID'] ?>">Lire</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (count($messages) === 0): ?>
                            <tr><td colspan="6" class="tableau-vide">Aucun message pour l'instant.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
</body>
</html>
