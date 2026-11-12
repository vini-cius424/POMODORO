<?php
// =====================================================================
// POMODORO STORE - Action : inscription a la newsletter
//
// Appelee en arriere-plan (fetch) par javascript/pied.js quand on envoie
// le formulaire du pied de page. Repond en JSON :
//     {"ok": true,  "message": "..."}
// Le message est affiche tel quel sous le formulaire.
// =====================================================================

require __DIR__ . '/../../back-end/fonctions/brevo.php';

header('Content-Type: application/json; charset=utf-8');

function repondre($ok, $message)
{
    echo json_encode(['ok' => $ok, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    repondre(false, "Méthode non autorisée.");
}

if (!brevo_newsletter_configuree()) {
    repondre(false, "L'inscription à la newsletter n'est pas encore disponible sur le site.");
}

$email = trim($_POST['email'] ?? '');

if ($email === '' || strlen($email) > 191 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    repondre(false, "Cette adresse email n'est pas valide.");
}

if (brevo_deja_abonne($email)) {
    repondre(false, "Cette adresse est déjà inscrite à notre newsletter.");
}

if (!brevo_inscrire_newsletter($email)) {
    repondre(false, "L'inscription n'a pas pu être enregistrée. Réessayez dans quelques minutes.");
}

repondre(true, "Merci ! Un email de confirmation vient de vous être envoyé : cliquez sur le lien qu'il contient pour valider votre inscription.");
