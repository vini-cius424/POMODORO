<?php
// =====================================================================
// POMODORO STORE - Action : retour apres un paiement Stripe reussi
//
// Stripe renvoie ici le client apres le paiement :
//     paiement-retour.php?session_id=cs_test_...
// On ne croit PAS cette adresse sur parole (n'importe qui peut la
// taper) : on redemande a Stripe si la session est vraiment payee.
//     paye           -> commande VALIDEE, email, page de confirmation
//     pas paye       -> ../panier.php?panier=paiement-echec
// =====================================================================

require __DIR__ . '/../../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../../back-end/classes/Commande.php';
require __DIR__ . '/../../back-end/fonctions/brevo.php';
require __DIR__ . '/../../back-end/fonctions/stripe.php';

session_start();

// --- 1. Client connecte ---
if (empty($_SESSION['client_id'])) {
    header('Location: ../connexion.php');
    exit;
}

// --- 2. La commande liee a cette session de paiement ---
$sessionId = $_GET['session_id'] ?? '';
$commande = $sessionId !== '' ? Commande::trouverParSessionPaiement($pdo, $sessionId) : null;

// Session inconnue, ou commande d'un autre client : on s'arrete.
if ($commande === null || (int) $commande['COM_CLI_ID'] !== (int) $_SESSION['client_id']) {
    header('Location: ../index.php');
    exit;
}

$adresseConfirmation = '../commande-confirmation.php?numero=' . urlencode($commande['COM_NUMERO']) . '&nouvelle=1';

// Deja validee (le client recharge la page) : rien a refaire, et
// surtout pas un deuxieme email.
if ($commande['COM_STATUT'] !== 'EN_ATTENTE') {
    header('Location: ' . $adresseConfirmation);
    exit;
}

// --- 3. On demande a Stripe si c'est vraiment paye ---
$session = stripe_lire_session($sessionId);

// Le montant encaisse doit etre exactement celui de la commande
// (en centimes) : on verifie, au cas ou.
$montantAttendu = (int) round($commande['COM_MONTANT_TOTAL'] * 100);

if ($session === null
    || ($session['payment_status'] ?? '') !== 'paid'
    || (int) ($session['amount_total'] ?? 0) !== $montantAttendu) {
    header('Location: ../panier.php?panier=paiement-echec');
    exit;
}

// --- 4. Paiement confirme : la commande est validee ---
Commande::changerStatut($pdo, $commande['COM_ID'], 'VALIDEE');

// Email de confirmation (si Brevo est configure). Le resultat est garde
// en session pour que la page de confirmation le dise au client.
if (brevo_est_configure()) {
    $_SESSION['email_commande_envoye'] = brevo_envoyer_confirmation_commande($pdo, $_SESSION['client_id'], $commande['COM_NUMERO']);
}

header('Location: ' . $adresseConfirmation . '&paiement=ok');
exit;
