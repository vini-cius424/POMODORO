<?php
// =====================================================================
// POMODORO STORE - Repondre a un message de contact (administration)
//
// message-repondre.php?id=3 : l'administrateur ecrit sa reponse ici et
// le site l'envoie lui-meme au client par email (Brevo), au nom de
// Pomodoro Store. La reponse est gardee dans le message et celui-ci passe
// a "Traite". Retour ensuite sur la page de detail.
// =====================================================================

require __DIR__ . '/../../back-end/securite/garde-admin.php';
require __DIR__ . '/../../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../../back-end/classes/MessageContact.php';
require __DIR__ . '/../../back-end/fonctions/brevo.php';

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$message = MessageContact::trouver($pdo, $id);

if ($message === null) {
    header('Location: messages.php');
    exit;
}

$libelleSujet = MessageContact::SUJETS[$message['MSG_SUJET']] ?? $message['MSG_SUJET'];

// Valeurs proposees : objet "Re: ..." et un debut de reponse a completer.
$objet = 'Re: ' . $libelleSujet . ' - Pomodoro Store';
$reponse = "Bonjour " . $message['MSG_NOM'] . ",\n\n\n\nMeilleures salutations,\nL'équipe Pomodoro Store";
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $objet = trim($_POST['objet'] ?? '');
    $reponse = trim($_POST['reponse'] ?? '');

    if (!brevo_est_configure()) {
        $erreur = "L'envoi d'emails n'est pas configuré sur ce poste (config-brevo.php).";
    } elseif ($objet === '' || mb_strlen($objet) > 150) {
        $erreur = "Indiquez un objet (150 caractères au maximum).";
    } elseif (mb_strlen($reponse) < 10) {
        $erreur = "Votre réponse est vide ou trop courte.";
    } elseif (!brevo_repondre_message_contact($message, $objet, $reponse)) {
        $erreur = "L'email n'a pas pu être envoyé. Réessayez dans un instant.";
    } else {
        // Envoye : on garde la reponse et le message passe a "Traite".
        MessageContact::enregistrerReponse($pdo, $id, $reponse);
        header('Location: message-detail.php?id=' . $id . '&message=reponse');
        exit;
    }
}

$ongletActif = 'messages';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Répondre à <?= htmlspecialchars($message['MSG_NOM']) ?> - Admin Pomodoro Store</title>
    <link rel="stylesheet" href="../styles/pomodoro.css?v=67">
</head>
<body>
<div class="admin-shell">
    <?php require __DIR__ . '/../morceaux-de-page/menu-admin.php'; ?>

    <main class="admin-main">
        <a class="admin-fil" href="message-detail.php?id=<?= (int) $message['MSG_ID'] ?>">← Retour au message</a>

        <div class="admin-topbar">
            <div>
                <h1 class="titre">Répondre à <?= htmlspecialchars($message['MSG_NOM']) ?></h1>
                <p class="sous-titre">
                    La réponse est envoyée par email à <strong><?= htmlspecialchars($message['MSG_EMAIL']) ?></strong>,
                    au nom de Pomodoro Store.
                </p>
            </div>
        </div>

        <?php if ($erreur): ?>
            <p class="encadre encadre-echec"><?= htmlspecialchars($erreur) ?></p>
        <?php endif; ?>

        <div class="message-grille">
            <div class="carte-admin">
                <form method="post" action="message-repondre.php">
                    <input type="hidden" name="id" value="<?= (int) $message['MSG_ID'] ?>">
                    <div class="champ">
                        <label for="objet">Objet</label>
                        <input type="text" id="objet" name="objet" maxlength="150" required
                               value="<?= htmlspecialchars($objet) ?>">
                    </div>
                    <div class="champ">
                        <label for="reponse">Votre réponse</label>
                        <textarea id="reponse" name="reponse" rows="12" required autofocus><?= htmlspecialchars($reponse) ?></textarea>
                    </div>
                    <p class="tableau-detail">Le message du client sera cité automatiquement sous votre réponse.</p>
                    <button type="submit" class="bouton">Envoyer la réponse</button>
                </form>
            </div>

            <div class="carte-admin">
                <h2 class="carte-admin-titre">Son message</h2>
                <p class="tableau-detail">
                    <?= htmlspecialchars($libelleSujet) ?> · reçu le <?= date('d.m.Y à H:i', strtotime($message['MSG_DATE'])) ?>
                    <?php if ($message['MSG_NUMERO_COMMANDE']): ?>
                        · commande <?= htmlspecialchars($message['MSG_NUMERO_COMMANDE']) ?>
                    <?php endif; ?>
                </p>
                <p class="message-texte"><?= nl2br(htmlspecialchars($message['MSG_MESSAGE'])) ?></p>
            </div>
        </div>
    </main>
</div>
</body>
</html>
