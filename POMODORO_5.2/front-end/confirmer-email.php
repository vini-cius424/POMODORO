<?php
// =====================================================================
// POMODORO STORE - Confirmation de l'adresse email
//
// Page ouverte par le lien de l'email envoye a l'inscription :
//     confirmer-email.php?jeton=4f3a...
// Lien valide : l'email est confirme et le client est connecte
// directement, sans retaper son mot de passe. Le lien ne sert qu'une
// fois et expire apres 24 heures : seul le proprietaire de la boite
// mail peut s'en servir.
// Lien invalide ou expire : retour a la connexion avec un message.
//
// La messagerie ouvre le lien dans un NOUVEL onglet. Si l'onglet
// d'inscription est encore ouvert, on le previent (BroadcastChannel,
// voir javascript/attente-confirmation.js) : c'est lui qui passe sur la
// boutique, et celui-ci se referme. Sinon, celui-ci y va lui-meme.
// =====================================================================

session_start();

require __DIR__ . '/../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../back-end/classes/Client.php';
require __DIR__ . '/../back-end/fonctions/brevo.php';

$jeton = $_GET['jeton'] ?? '';

// is_string : ?jeton[]=... donnerait un tableau, que hash() refuserait.
$client = is_string($jeton) && $jeton !== '' ? Client::confirmerEmail($pdo, $jeton) : null;

if ($client === null) {
    header('Location: connexion.php?confirmation=invalide');
    exit;
}

// Meme connexion que dans connexion.php. session_unset() d'abord :
// si quelqu'un d'autre (admin ou client) etait connecte dans ce
// navigateur, il est deconnecte, pour ne jamais avoir deux ids en session.
session_unset();
session_regenerate_id(true);
$_SESSION['client_id'] = $client->getId();
$_SESSION['client_prenom'] = $client->getPrenom();

// Adresse confirmee = nouveau client : on lui envoie son code de bienvenue
// (-20 % sur la premiere commande). Le lien de confirmation ne sert
// qu'une fois : le code n'est donc envoye qu'une fois par compte.
// Le resultat est garde en session : la boutique affiche le message de
// bienvenue (une seule fois) tant que cette cle existe.
$_SESSION['code_bienvenue_envoye'] = brevo_est_configure()
    && brevo_envoyer_code_bienvenue($client->getPrenom(), $client->getEmail());
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Adresse confirmée - Pomodoro Store</title>
    <link rel="stylesheet" href="styles/pomodoro.css?v=49">
</head>
<body>
<div class="page-client">
    <h1 class="titre">Adresse email confirmée</h1>
    <p class="sous-titre" id="confirmation-texte">Connexion en cours...</p>

    <!-- Affiche seulement si le navigateur refuse de fermer l'onglet. -->
    <div id="confirmation-fermer" hidden>
        <p class="encadre encadre-succes">
            Vous êtes connecté dans votre autre onglet Pomodoro : vous pouvez fermer celui-ci.
        </p>
        <a class="bouton-secondaire" href="boutique.php">Continuer ici</a>
    </div>
</div>

<script>
(function () {
    'use strict';

    var boutique = 'boutique.php';

    // Navigateur trop ancien : pas de communication entre onglets.
    if (!('BroadcastChannel' in window)) {
        window.location.replace(boutique);
        return;
    }

    var canal = new BroadcastChannel('pomodoro-confirmation');
    var ongletOrigineTrouve = false;

    canal.addEventListener('message', function (evenement) {
        if (evenement.data !== 'recu') return;
        ongletOrigineTrouve = true;

        // L'autre onglet a pris le relais : on essaie de fermer celui-ci.
        // Certains navigateurs refusent (onglet ouvert autrement que par
        // un lien) : on affiche alors un message a la place.
        window.close();
        window.setTimeout(function () {
            document.getElementById('confirmation-texte').style.display = 'none';
            document.getElementById('confirmation-fermer').hidden = false;
        }, 300);
    });

    canal.postMessage('confirme');

    // Pas de reponse : l'onglet d'inscription n'est plus ouvert (lien
    // clique plus tard, ou sur un autre appareil). On continue ici.
    window.setTimeout(function () {
        if (!ongletOrigineTrouve) window.location.replace(boutique);
    }, 800);
})();
</script>
</body>
</html>
