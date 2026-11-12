<?php
// =====================================================================
// POMODORO STORE - Mot de passe oublie (2/2) : nouveau mot de passe
//
// Page ouverte par le lien de l'email :
//     reinitialiser-mot-de-passe.php?jeton=4f3a...
// Le jeton est verifie a l'affichage ET a l'enregistrement (il a pu
// expirer entre les deux). Il repart dans un champ cache du formulaire.
// Une fois le mot de passe change, le lien ne sert plus.
// =====================================================================

require __DIR__ . '/../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../back-end/classes/Client.php';

// Le jeton arrive dans l'URL (premier affichage) ou dans le formulaire.
// is_string : ?jeton[]=... donnerait un tableau, que hash() refuserait.
$jeton = $_POST['jeton'] ?? $_GET['jeton'] ?? '';
if (!is_string($jeton)) {
    $jeton = '';
}

$lienValide = $jeton !== '' && Client::jetonMotDePasseValide($pdo, $jeton);
$erreurs = [];

if ($lienValide && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pas de trim() : un espace peut faire partie du mot de passe choisi.
    $nouveau      = $_POST['nouveau'] ?? '';
    $confirmation = $_POST['confirmation'] ?? '';

    // Memes regles qu'a l'inscription.
    if (strlen($nouveau) < 8) {
        $erreurs['nouveau'] = "Le mot de passe doit contenir au moins 8 caractères.";
    } elseif (!preg_match("/[a-zA-Z]/", $nouveau) || !preg_match("/[0-9]/", $nouveau)) {
        $erreurs['nouveau'] = "Le mot de passe doit contenir au moins une lettre et un chiffre.";
    }

    if ($confirmation !== $nouveau) {
        $erreurs['confirmation'] = "Les deux mots de passe ne correspondent pas.";
    }

    if (count($erreurs) === 0) {
        if (Client::reinitialiserMotDePasse($pdo, $jeton, $nouveau)) {
            header('Location: connexion.php?mdp=change');
            exit;
        }
        // Le lien a expire pendant que le client remplissait le formulaire.
        $lienValide = false;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nouveau mot de passe - Pomodoro Store</title>
    <link rel="stylesheet" href="styles/pomodoro.css?v=56">
</head>
<body>

<div class="split">

    <!-- ---------- Panneau sombre (gauche) ---------- -->
    <div class="panneau-sombre">
        <a class="logo" href="index.php">Pomodoro<span class="logo-point">.</span></a>
        <p class="accroche">Un nouveau départ.</p>
        <p class="accroche-texte">
            Choisissez un nouveau mot de passe pour votre compte Pomodoro.
        </p>
        <a class="retour" href="connexion.php" title="Retour à la connexion"
           aria-label="Retour à la connexion">&larr;</a>
    </div>

    <!-- ---------- Panneau clair (droite) ---------- -->
    <div class="panneau-clair">
        <h1 class="titre">Nouveau mot de passe</h1>

        <?php if (!$lienValide): ?>
            <div class="encadre encadre-echec">
                <p class="encadre-titre">Lien invalide ou expiré</p>
                <p>Ce lien a déjà servi ou n'est plus valable (il expire après 1 heure).
                   Faites une nouvelle demande.</p>
            </div>
            <p class="note-bas"><a href="mot-de-passe-oublie.php">Recevoir un nouveau lien</a></p>
        <?php else: ?>
            <form class="formulaire" method="post" action="reinitialiser-mot-de-passe.php">
                <input type="hidden" name="jeton" value="<?= htmlspecialchars($jeton) ?>">

                <!-- Les mots de passe ne sont jamais reaffiches : pas de value. -->
                <div class="champ">
                    <label for="nouveau">Nouveau mot de passe</label>
                    <input type="password" id="nouveau" name="nouveau" required autofocus
                           class="<?= isset($erreurs['nouveau']) ? 'champ-erreur' : '' ?>">
                    <?php if (isset($erreurs['nouveau'])): ?>
                        <span class="message-erreur"><?= $erreurs['nouveau'] ?></span>
                    <?php else: ?>
                        <span class="aide">Au moins 8 caractères, dont une lettre et un chiffre.</span>
                    <?php endif; ?>
                </div>

                <div class="champ">
                    <label for="confirmation">Confirmer le nouveau mot de passe</label>
                    <input type="password" id="confirmation" name="confirmation" required
                           class="<?= isset($erreurs['confirmation']) ? 'champ-erreur' : '' ?>">
                    <?php if (isset($erreurs['confirmation'])): ?>
                        <span class="message-erreur"><?= $erreurs['confirmation'] ?></span>
                    <?php endif; ?>
                </div>

                <button type="submit" class="bouton">Enregistrer le mot de passe</button>
            </form>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
