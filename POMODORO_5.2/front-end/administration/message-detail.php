<?php
// =====================================================================
// POMODORO STORE - Detail d'un message de contact (administration)
//
// message-detail.php?id=3 : le message complet et son auteur.
// L'ouvrir le passe de NOUVEAU a LU. Le bouton "Repondre" mene a
// message-repondre.php, ou l'administrateur ecrit sa reponse : le site
// l'envoie par email au client et passe le message a "Traite". La
// derniere reponse envoyee s'affiche ici, sous le message.
// =====================================================================

require __DIR__ . '/../../back-end/securite/garde-admin.php';
require __DIR__ . '/../../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../../back-end/classes/MessageContact.php';

$id = (int) ($_GET['id'] ?? 0);
$message = MessageContact::trouver($pdo, $id);

if ($message === null) {
    header('Location: messages.php');
    exit;
}

// Premiere ouverture : NOUVEAU -> LU. On relit ensuite le message pour
// afficher le bon statut (et la pastille du menu a jour).
if ($message['MSG_STATUT'] === 'NOUVEAU') {
    MessageContact::marquerLu($pdo, $id);
    $message = MessageContact::trouver($pdo, $id);
}

$libelleSujet = MessageContact::SUJETS[$message['MSG_SUJET']] ?? $message['MSG_SUJET'];

$estTraite = $message['MSG_STATUT'] === 'TRAITE';
$ongletActif = 'messages';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Message de <?= htmlspecialchars($message['MSG_NOM']) ?> - Admin Pomodoro Store</title>
    <link rel="stylesheet" href="../styles/pomodoro.css?v=67">
</head>
<body>
<div class="admin-shell">
    <?php require __DIR__ . '/../morceaux-de-page/menu-admin.php'; ?>

    <main class="admin-main">
        <a class="admin-fil" href="messages.php">← Retour aux messages</a>

        <div class="admin-topbar">
            <div>
                <h1 class="titre"><?= htmlspecialchars($libelleSujet) ?></h1>
                <p class="sous-titre">
                    Reçu le <?= date('d.m.Y à H:i', strtotime($message['MSG_DATE'])) ?> ·
                    <span class="badge badge-message-<?= strtolower($message['MSG_STATUT']) ?>">
                        <?= MessageContact::STATUTS[$message['MSG_STATUT']] ?>
                    </span>
                </p>
            </div>
        </div>

        <?php if (($_GET['message'] ?? '') === 'statut'): ?>
            <p class="encadre encadre-succes">Le statut du message a été mis à jour.</p>
        <?php elseif (($_GET['message'] ?? '') === 'reponse'): ?>
            <p class="encadre encadre-succes">
                Votre réponse a été envoyée à <?= htmlspecialchars($message['MSG_EMAIL']) ?>. Le message est classé comme traité.
            </p>
        <?php endif; ?>

        <div class="message-grille">
            <div class="carte-admin">
                <h2 class="carte-admin-titre">Message</h2>
                <!-- nl2br : garde les retours a la ligne tapes par le client.
                     htmlspecialchars AVANT : le texte ne peut contenir aucun HTML. -->
                <p class="message-texte"><?= nl2br(htmlspecialchars($message['MSG_MESSAGE'])) ?></p>

                <?php if ($message['MSG_REPONSE']): ?>
                    <div class="message-reponse">
                        <p class="message-reponse-titre">
                            Votre réponse · envoyée le <?= date('d.m.Y à H:i', strtotime($message['MSG_DATE_REPONSE'])) ?>
                        </p>
                        <p class="message-texte"><?= nl2br(htmlspecialchars($message['MSG_REPONSE'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="carte-admin">
                <h2 class="carte-admin-titre">Expéditeur</h2>
                <dl class="message-auteur">
                    <dt>Nom</dt>
                    <dd><?= htmlspecialchars($message['MSG_NOM']) ?></dd>
                    <dt>Email</dt>
                    <dd><?= htmlspecialchars($message['MSG_EMAIL']) ?></dd>
                    <?php if ($message['MSG_NUMERO_COMMANDE']): ?>
                        <dt>N° de commande</dt>
                        <dd><?= htmlspecialchars($message['MSG_NUMERO_COMMANDE']) ?></dd>
                    <?php endif; ?>
                    <dt>Compte client</dt>
                    <dd>
                        <?php if ($message['MSG_CLI_ID']): ?>
                            Oui, connecté lors de l'envoi
                            <?php if ($message['CLI_TELEPHONE']): ?>
                                <br><span class="tableau-detail">Tél. <?= htmlspecialchars($message['CLI_TELEPHONE']) ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            Non, visiteur sans compte
                        <?php endif; ?>
                    </dd>
                </dl>

                <div class="message-actions">
                    <a class="bouton" href="message-repondre.php?id=<?= (int) $message['MSG_ID'] ?>">
                        <?= $message['MSG_REPONSE'] ? 'Répondre à nouveau' : 'Répondre' ?>
                    </a>
                    <form method="post" action="actions/message-statut.php">
                        <input type="hidden" name="id" value="<?= (int) $message['MSG_ID'] ?>">
                        <input type="hidden" name="statut" value="<?= $estTraite ? 'LU' : 'TRAITE' ?>">
                        <button type="submit" class="bouton-secondaire">
                            <?= $estTraite ? 'Remettre à traiter' : 'Marquer comme traité' ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>
