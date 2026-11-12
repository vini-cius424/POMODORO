<?php
// =====================================================================
// POMODORO STORE - Newsletter confirmee
//
// Page d'arrivee apres le clic sur le lien de l'email "confirmez votre
// inscription" envoye par Brevo (redirectionUrl dans brevo.php). C'est
// Brevo qui a deja enregistre l'abonne : ici, on remercie seulement.
// =====================================================================
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inscription confirmée - Pomodoro Store</title>
    <link rel="stylesheet" href="styles/pomodoro.css?v=54">
</head>
<body>
<?php require __DIR__ . '/morceaux-de-page/entete.php'; ?>
<div class="page-client">
    <h1 class="titre">Merci, c'est confirmé !</h1>
    <p class="encadre encadre-succes">
        Votre inscription à la newsletter Pomodoro est validée. Vous recevrez
        nos nouveautés, collections et offres exclusives.
    </p>
    <a class="bouton" href="boutique.php">Découvrir la boutique</a>
</div>
<?php require __DIR__ . '/morceaux-de-page/pied.php'; ?>
</body>
</html>
