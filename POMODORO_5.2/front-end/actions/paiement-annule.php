<?php
// =====================================================================
// POMODORO STORE - Action : le client a abandonne le paiement Stripe
//
// Stripe renvoie ici le client qui clique sur "Retour" sur la page de
// paiement :
//     paiement-annule.php?commande=12
// La commande (EN_ATTENTE) est annulee, son stock rendu, et ses
// articles remis dans le panier pour qu'il puisse reessayer.
// =====================================================================

require __DIR__ . '/../../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../../back-end/classes/Commande.php';
require __DIR__ . '/../../back-end/fonctions/stripe.php';

session_start();

// --- 1. Client connecte ---
if (empty($_SESSION['client_id'])) {
    header('Location: ../connexion.php');
    exit;
}

// --- 2. La commande doit etre a ce client, en attente, et passee par Stripe ---
$requete = $pdo->prepare(
    "SELECT COM_ID, COM_STATUT, COM_PAIEMENT_ID FROM POM_COMMANDE
     WHERE COM_ID = :id AND COM_CLI_ID = :client"
);
$requete->execute([":id" => (int) ($_GET['commande'] ?? 0), ":client" => $_SESSION['client_id']]);
$commande = $requete->fetch(PDO::FETCH_ASSOC);

if ($commande === false || $commande['COM_STATUT'] !== 'EN_ATTENTE' || $commande['COM_PAIEMENT_ID'] === null) {
    header('Location: ../panier.php');
    exit;
}

// --- 3. Securite : verifier aupres de Stripe qu'elle n'a PAS ete payee ---
// Sans ce controle, quelqu'un qui taperait cette adresse apres avoir
// paye ferait annuler une commande deja encaissee.
$session = stripe_lire_session($commande['COM_PAIEMENT_ID']);

// Stripe ne repond pas : impossible de savoir si c'est paye. Dans le
// doute, on n'annule rien (la commande reste EN_ATTENTE, l'admin
// pourra verifier dans le tableau de bord Stripe).
if ($session === null) {
    header('Location: ../panier.php?panier=paiement-echec');
    exit;
}

if (($session['payment_status'] ?? '') === 'paid') {
    // Elle est payee : on la traite comme un paiement reussi.
    header('Location: paiement-retour.php?session_id=' . urlencode($commande['COM_PAIEMENT_ID']));
    exit;
}

// --- 4. Annulation : statut ANNULEE + stock rendu, puis panier remis ---
if (Commande::annuler($pdo, $commande['COM_ID'])) {
    Commande::remettreAuPanier($pdo, $commande['COM_ID'], $_SESSION['client_id']);
}

header('Location: ../panier.php?panier=paiement-annule');
exit;
