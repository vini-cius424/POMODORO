<?php
// A inclure en haut de chaque page reservee au client connecte.
// Un admin connecte n'a pas de client_id en session : il est lui aussi
// renvoye vers la connexion s'il essaie d'ouvrir une page client.

session_start();

if (empty($_SESSION['client_id'])) {
    header('Location: connexion.php');
    exit;
}
