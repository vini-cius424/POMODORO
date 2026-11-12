<?php
// =====================================================================
// POMODORO STORE - Formulaire de contact
//
// Ouvert a tous, avec ou sans compte. Le message est enregistre dans
// POM_MESSAGE_CONTACT et l'administrateur le lit dans
// administration/messages.php. La boutique recoit aussi une notification
// par email (Brevo), a laquelle elle peut repondre directement au client.
//
// Client connecte : nom et email sont pre-remplis avec son compte, et
// le message est rattache a son compte (MSG_CLI_ID).
//
// Anti-spam, sans CAPTCHA :
//  - un champ piege "site_web", cache aux humains par le CSS : un robot
//    qui remplit tous les champs se trahit, son message est ignore ;
//  - un seul message par minute et par visiteur (session).
//
// Apres l'envoi, redirection vers contact.php?envoye=1 : actualiser la
// page ne renvoie pas le message une deuxieme fois.
// =====================================================================

session_start();

require __DIR__ . '/../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../back-end/classes/Client.php';
require __DIR__ . '/../back-end/classes/MessageContact.php';
require __DIR__ . '/../back-end/fonctions/brevo.php';

const DELAI_ENTRE_MESSAGES = 60; // secondes

$clientId = $_SESSION['client_id'] ?? null;
$client = $clientId ? Client::trouver($pdo, $clientId) : null;

// Valeurs du formulaire : celles du compte, ou vides pour un visiteur.
$nom = $client ? $client->getPrenom() . ' ' . $client->getNom() : '';
$email = $client ? $client->getEmail() : '';
$sujet = $_GET['sujet'] ?? '';
$numeroCommande = '';
$message = '';
$erreurs = [];
$envoye = ($_GET['envoye'] ?? '') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $sujet = $_POST['sujet'] ?? '';
    $numeroCommande = trim($_POST['numero_commande'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // Champ piege rempli : c'est un robot. On fait comme si tout allait
    // bien (il ne doit pas comprendre qu'il a ete repere) sans rien enregistrer.
    if (trim($_POST['site_web'] ?? '') !== '') {
        header('Location: contact.php?envoye=1');
        exit;
    }

    if (mb_strlen($nom) < 2 || mb_strlen($nom) > 150) {
        $erreurs['nom'] = "Indiquez votre nom (2 à 150 caractères).";
    }
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 191) {
        $erreurs['email'] = "Cette adresse email n'est pas valide.";
    }
    if (!array_key_exists($sujet, MessageContact::SUJETS)) {
        $erreurs['sujet'] = "Choisissez le sujet de votre message.";
    }
    if (mb_strlen($numeroCommande) > 50) {
        $erreurs['numero_commande'] = "Ce numéro de commande est trop long.";
    }
    if (mb_strlen($message) < MessageContact::LONGUEUR_MESSAGE_MIN || mb_strlen($message) > MessageContact::LONGUEUR_MESSAGE_MAX) {
        $erreurs['message'] = "Votre message doit faire entre " . MessageContact::LONGUEUR_MESSAGE_MIN
                            . " et " . MessageContact::LONGUEUR_MESSAGE_MAX . " caractères.";
    }

    $dernierEnvoi = $_SESSION['contact_dernier_envoi'] ?? 0;
    if (!$erreurs && time() - $dernierEnvoi < DELAI_ENTRE_MESSAGES) {
        $erreurs['general'] = "Vous venez déjà de nous écrire. Patientez une minute avant d'envoyer un autre message.";
    }

    if (!$erreurs) {
        $messageId = MessageContact::creer($pdo, $nom, $email, $sujet, $numeroCommande, $message, $clientId);
        $_SESSION['contact_dernier_envoi'] = time();

        // Notification par email a la boutique. Le message est deja en
        // base : si l'email ne part pas, il reste visible dans l'admin.
        if (brevo_est_configure()) {
            brevo_notifier_message_contact($messageId, $nom, $email, MessageContact::SUJETS[$sujet], $numeroCommande, $message);
        }

        header('Location: contact.php?envoye=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contact - Pomodoro Store</title>
    <link rel="stylesheet" href="styles/pomodoro.css?v=62">
</head>
<body>
<?php require __DIR__ . '/morceaux-de-page/entete.php'; ?>
<div class="page-contact">
    <div class="contact-intro">
        <h1 class="titre">Contactez-nous</h1>
        <p class="sous-titre">
            Une question sur une commande, un produit ou une taille ? Écrivez-nous :
            notre équipe vous répond par email dans les meilleurs délais.
        </p>
        <ul class="contact-infos">
            <li><strong>Une commande ?</strong> Indiquez son numéro (ex. POM-000012) : nous la retrouverons plus vite.</li>
            <li><strong>Un doute sur la taille ?</strong> Précisez le produit et votre taille habituelle.</li>
            <li><strong>Un retour ou un échange ?</strong> Indiquez le numéro de commande et l'article concerné.</li>
        </ul>
    </div>

    <div class="contact-formulaire">
        <?php if ($envoye): ?>
            <div class="encadre encadre-succes">
                <p class="encadre-titre">Message envoyé, merci !</p>
                <p>Nous avons bien reçu votre message et vous répondrons par email dès que possible.</p>
            </div>
            <p class="note-bas"><a href="boutique.php">Retour à la boutique</a></p>
        <?php else: ?>
            <?php if (isset($erreurs['general'])): ?>
                <p class="encadre encadre-echec"><?= htmlspecialchars($erreurs['general']) ?></p>
            <?php elseif ($erreurs): ?>
                <p class="encadre encadre-echec">Le message n'a pas été envoyé : corrigez les champs en rouge.</p>
            <?php endif; ?>

            <form class="formulaire" method="post" action="contact.php" novalidate>
                <div class="champ-double">
                    <div class="champ">
                        <label for="nom">Nom</label>
                        <input type="text" id="nom" name="nom" maxlength="150" autocomplete="name" required
                               class="<?= isset($erreurs['nom']) ? 'champ-erreur' : '' ?>"
                               value="<?= htmlspecialchars($nom) ?>">
                        <?php if (isset($erreurs['nom'])): ?>
                            <span class="message-erreur"><?= htmlspecialchars($erreurs['nom']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="champ">
                        <label for="email">Adresse email</label>
                        <input type="email" id="email" name="email" maxlength="191" autocomplete="email" required
                               class="<?= isset($erreurs['email']) ? 'champ-erreur' : '' ?>"
                               value="<?= htmlspecialchars($email) ?>">
                        <?php if (isset($erreurs['email'])): ?>
                            <span class="message-erreur"><?= htmlspecialchars($erreurs['email']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="champ-double">
                    <div class="champ">
                        <label for="sujet">Sujet</label>
                        <select id="sujet" name="sujet" required class="<?= isset($erreurs['sujet']) ? 'champ-erreur' : '' ?>">
                            <option value="">Choisir un sujet</option>
                            <?php foreach (MessageContact::SUJETS as $code => $libelle): ?>
                                <option value="<?= $code ?>" <?= $sujet === $code ? 'selected' : '' ?>><?= htmlspecialchars($libelle) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($erreurs['sujet'])): ?>
                            <span class="message-erreur"><?= htmlspecialchars($erreurs['sujet']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="champ">
                        <label for="numero_commande">N° de commande <span class="facultatif">(facultatif)</span></label>
                        <input type="text" id="numero_commande" name="numero_commande" maxlength="50" placeholder="POM-000012"
                               class="<?= isset($erreurs['numero_commande']) ? 'champ-erreur' : '' ?>"
                               value="<?= htmlspecialchars($numeroCommande) ?>">
                        <?php if (isset($erreurs['numero_commande'])): ?>
                            <span class="message-erreur"><?= htmlspecialchars($erreurs['numero_commande']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="champ">
                    <label for="message">Votre message</label>
                    <textarea id="message" name="message" rows="7" required
                              minlength="<?= MessageContact::LONGUEUR_MESSAGE_MIN ?>" maxlength="<?= MessageContact::LONGUEUR_MESSAGE_MAX ?>"
                              class="<?= isset($erreurs['message']) ? 'champ-erreur' : '' ?>"><?= htmlspecialchars($message) ?></textarea>
                    <?php if (isset($erreurs['message'])): ?>
                        <span class="message-erreur"><?= htmlspecialchars($erreurs['message']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Champ piege anti-robot : invisible pour un humain (voir le CSS),
                     hors du parcours au clavier et ignore par l'autocompletion. -->
                <div class="champ-piege" aria-hidden="true">
                    <label for="site_web">Ne pas remplir ce champ</label>
                    <input type="text" id="site_web" name="site_web" tabindex="-1" autocomplete="off">
                </div>

                <button type="submit" class="bouton">Envoyer le message</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/morceaux-de-page/pied.php'; ?>
</body>
</html>
