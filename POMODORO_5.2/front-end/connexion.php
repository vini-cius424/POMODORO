<?php
session_start();
require __DIR__ . '/../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../back-end/classes/Administrateur.php';
require __DIR__ . '/../back-end/classes/Client.php';
require __DIR__ . '/../back-end/fonctions/brevo.php';

// Deja connecte -> direction selon le role stocke en session :
// l'administrateur vers son tableau de bord, le client vers la boutique.
if (!empty($_SESSION['admin_id'])) {
    header('Location: administration/tableau-de-bord.php');
    exit;
}
if (!empty($_SESSION['client_id'])) {
    header('Location: boutique.php');
    exit;
}

$erreur = '';
$email = '';

// Lien de confirmation invalide ou expire : confirmer-email.php redirige
// ici avec ?confirmation=invalide (un lien valide connecte directement).
$confirmation = $_GET['confirmation'] ?? '';

// reinitialiser-mot-de-passe.php redirige ici avec ?mdp=change.
$messageMdp = $_GET['mdp'] ?? '';

// Rempli quand le mot de passe est bon mais l'email pas encore confirme.
$aConfirmer = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $motDePasse = $_POST['mot_de_passe'] ?? '';

    // Meme formulaire pour les deux, mais un email n'existe que dans une
    // seule des deux tables : on essaie d'abord l'administrateur, puis
    // le client. La session ne contient jamais qu'un seul des deux ids,
    // donc les droits ne se melangent jamais.
    $admin = Administrateur::seConnecter($pdo, $email, $motDePasse);

    if ($admin !== null) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin->id;
        $_SESSION['admin_prenom'] = $admin->prenom;
        header('Location: administration/tableau-de-bord.php');
        exit;
    }

    $client = Client::seConnecter($pdo, $email, $motDePasse);

    // Email pas encore confirme : pas de connexion, on renvoie un nouveau
    // lien. On ne le fait qu'apres un mot de passe correct : impossible de
    // bombarder d'emails l'adresse de quelqu'un d'autre depuis ce formulaire.
    // Sans Brevo configure, aucun email ne pourrait partir : on ne bloque pas.
    if ($client !== null && brevo_est_configure() && !$client->aConfirmeSonEmail()) {
        $envoye = brevo_envoyer_confirmation($pdo, $client->getId(), $client->getPrenom(), $client->getEmail());
        $aConfirmer = $envoye ? 'renvoye' : 'echec';
    } elseif ($client !== null) {
        session_regenerate_id(true);
        $_SESSION['client_id'] = $client->getId();
        $_SESSION['client_prenom'] = $client->getPrenom();
        header('Location: boutique.php');
        exit;
    } else {
        // Message volontairement vague, qu'il s'agisse d'un compte admin ou
        // client : on ne revele jamais lequel des deux champs est faux, ni
        // quel type de compte correspond a l'email saisi.
        $erreur = "Email ou mot de passe incorrect.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion - Pomodoro Store</title>
    <link rel="stylesheet" href="styles/pomodoro.css?v=56">
</head>
<body>

<div class="split">

    <!-- ---------- Panneau sombre (gauche) ---------- -->
    <div class="panneau-sombre">
        <a class="logo" href="index.php">Pomodoro<span class="logo-point">.</span></a>
        <p class="accroche">Content de vous revoir.</p>
        <p class="accroche-texte">
            Connectez-vous pour retrouver vos commandes et vos articles
            préférés.
        </p>

        <!-- Retour vers la boutique, colle en bas du panneau. title
             s'affiche au survol de la souris, aria-label est ce que lisent
             les lecteurs d'ecran : sans lui, une fleche seule ne veut rien
             dire pour une personne aveugle. -->
        <a class="retour" href="boutique.php" title="Retour à la boutique"
           aria-label="Retour à la boutique">&larr;</a>
    </div>

    <!-- ---------- Panneau clair (droite) ---------- -->
    <div class="panneau-clair">

        <div class="onglets">
            <a class="onglet" href="inscription.php">Créer un compte</a>
            <a class="onglet onglet-actif" href="connexion.php">Se connecter</a>
        </div>

        <h1 class="titre">Bienvenue</h1>
        <p class="sous-titre">Entrez vos identifiants pour continuer.</p>

        <?php if ($erreur): ?>
            <div class="encadre encadre-echec">
                <p class="encadre-titre">Connexion impossible</p>
                <p><?= htmlspecialchars($erreur) ?></p>
            </div>
        <?php elseif ($aConfirmer === 'renvoye'): ?>
            <div class="encadre encadre-echec">
                <p class="encadre-titre">Adresse email pas encore confirmée</p>
                <p>Nous venons de vous renvoyer un email de confirmation. Cliquez sur
                   le lien qu'il contient : cette page vous connectera automatiquement.
                   Pensez à regarder dans vos spams.</p>
            </div>
            <script src="javascript/attente-confirmation.js?v=2"></script>
        <?php elseif ($aConfirmer === 'echec'): ?>
            <div class="encadre encadre-echec">
                <p class="encadre-titre">Adresse email pas encore confirmée</p>
                <p>L'email de confirmation n'a pas pu être envoyé. Réessayez dans
                   quelques minutes.</p>
            </div>
        <?php elseif ($confirmation === 'invalide'): ?>
            <div class="encadre encadre-echec">
                <p class="encadre-titre">Lien invalide ou expiré</p>
                <p>Connectez-vous avec votre email et votre mot de passe : un nouveau
                   lien de confirmation vous sera envoyé.</p>
            </div>
        <?php elseif ($messageMdp === 'change'): ?>
            <div class="encadre encadre-succes">
                <p class="encadre-titre">Mot de passe modifié</p>
                <p>Vous pouvez maintenant vous connecter avec votre nouveau mot de passe.</p>
            </div>
        <?php endif; ?>

        <form class="formulaire" method="post">

            <div class="champ">
                <label for="email">Adresse email</label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($email) ?>" required autofocus>
            </div>

            <div class="champ">
                <label for="mot_de_passe">Mot de passe</label>
                <input type="password" id="mot_de_passe" name="mot_de_passe" required>
                <a class="lien-mot-de-passe-oublie" href="mot-de-passe-oublie.php">Mot de passe oublié ?</a>
            </div>

            <button type="submit" class="bouton">Se connecter</button>

        </form>

    </div>
</div>

</body>
</html>
