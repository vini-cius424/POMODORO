<?php
// =====================================================================
// POMODORO STORE - Mot de passe oublie (1/2) : demande du lien
//
// Le client saisit son email. Si un compte actif existe, un lien pour
// choisir un nouveau mot de passe lui est envoye par Brevo (valable
// 1 heure, voir reinitialiser-mot-de-passe.php).
//
// Le message affiche est TOUJOURS le meme, que le compte existe ou non :
// sinon, n'importe qui pourrait tester des adresses pour savoir qui est
// client de la boutique.
// =====================================================================

session_start();

require __DIR__ . '/../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../back-end/classes/Client.php';
require __DIR__ . '/../back-end/fonctions/brevo.php';

// Deja connecte : rien a faire ici.
if (!empty($_SESSION['client_id'])) {
    header('Location: mon-compte.php');
    exit;
}

$email = '';
$erreur = '';
$demandeEnvoyee = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $erreur = "Cette adresse email n'est pas valide.";
    } else {
        // Compte inconnu ou demande trop rapprochee : nouveauJetonMotDePasse()
        // renvoie null et aucun email ne part, mais le visiteur voit le meme
        // message que si tout s'etait bien passe.
        $demande = Client::nouveauJetonMotDePasse($pdo, $email);
        if ($demande !== null) {
            brevo_envoyer_reinitialisation($demande['prenom'], $demande['email'], $demande['jeton']);
        }
        $demandeEnvoyee = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mot de passe oublié - Pomodoro Store</title>
    <link rel="stylesheet" href="styles/pomodoro.css?v=56">
</head>
<body>

<div class="split">

    <!-- ---------- Panneau sombre (gauche) ---------- -->
    <div class="panneau-sombre">
        <a class="logo" href="index.php">Pomodoro<span class="logo-point">.</span></a>
        <p class="accroche">Pas de panique.</p>
        <p class="accroche-texte">
            Indiquez l'adresse email de votre compte : nous vous envoyons un lien
            pour choisir un nouveau mot de passe.
        </p>
        <a class="retour" href="connexion.php" title="Retour à la connexion"
           aria-label="Retour à la connexion">&larr;</a>
    </div>

    <!-- ---------- Panneau clair (droite) ---------- -->
    <div class="panneau-clair">
        <h1 class="titre">Mot de passe oublié</h1>

        <?php if (!brevo_est_configure()): ?>
            <p class="encadre encadre-echec">
                L'envoi d'emails n'est pas configuré sur ce poste : la réinitialisation
                du mot de passe n'est pas disponible.
            </p>
        <?php elseif ($demandeEnvoyee): ?>
            <div class="encadre encadre-succes">
                <p class="encadre-titre">Vérifiez votre boîte mail</p>
                <p>Si un compte existe avec l'adresse <strong><?= htmlspecialchars($email) ?></strong>,
                   un email vient de lui être envoyé. Cliquez sur le lien qu'il contient
                   (valable 1 heure) pour choisir un nouveau mot de passe. Pensez à
                   regarder dans vos spams.</p>
            </div>
            <p class="note-bas"><a href="connexion.php">Retour à la connexion</a></p>
        <?php else: ?>
            <p class="sous-titre">Saisissez l'adresse email de votre compte.</p>

            <?php if ($erreur): ?>
                <p class="encadre encadre-echec"><?= htmlspecialchars($erreur) ?></p>
            <?php endif; ?>

            <form class="formulaire" method="post" action="mot-de-passe-oublie.php">
                <div class="champ">
                    <label for="email">Adresse email</label>
                    <input type="email" id="email" name="email" maxlength="191"
                           value="<?= htmlspecialchars($email) ?>" required autofocus>
                </div>
                <button type="submit" class="bouton">Recevoir le lien</button>
            </form>
            <p class="note-bas"><a href="connexion.php">Retour à la connexion</a></p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
