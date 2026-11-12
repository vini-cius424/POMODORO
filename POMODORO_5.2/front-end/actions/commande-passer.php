<?php
// =====================================================================
// POMODORO STORE - Action : passer la commande (tache 10, segment 3)
//
// Recoit le formulaire de commander.php, verifie les champs, puis
// enregistre la commande avec Commande::passer().
//     champs invalides  -> ../commander.php?commande=invalide
//     echec (stock...)  -> ../panier.php?panier=commande
//     reussite          -> ../commande-confirmation.php?numero=POM-000012
// =====================================================================

require __DIR__ . '/../../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../../back-end/classes/Commande.php';
require __DIR__ . '/../../back-end/fonctions/brevo.php';
require __DIR__ . '/../../back-end/fonctions/stripe.php';
require_once __DIR__ . '/../../back-end/classes/Client.php';

session_start();

// --- 1. Client connecte, et envoi du formulaire uniquement (POST) ---
if (empty($_SESSION['client_id'])) {
    header('Location: ../connexion.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../commander.php');
    exit;
}

// --- 2. Lecture du formulaire ---
// adresse_id : le numero d'une adresse enregistree, ou "nouvelle" si le
// client a choisi de saisir une autre adresse.
$choixAdresse = $_POST['adresse_id'] ?? 'nouvelle';
$rue   = trim($_POST['rue'] ?? '');
$npa   = trim($_POST['npa'] ?? '');
$ville = trim($_POST['ville'] ?? '');
$mode  = $_POST['mode_livraison'] ?? '';
$codePromo = strtoupper(trim($_POST['code_promo'] ?? ''));

// --- 3. Verifications ---
// Le HTML (required, pattern) verifie deja, mais il peut etre contourne :
// le serveur refait toujours les controles.
$valide = true;

if ($choixAdresse === 'nouvelle') {
    // Nouvelle adresse : on verifie les champs saisis.
    $adresseId = null;

    // mb_strlen : longueur du texte en caracteres (compte "e" et "é" pareil).
    // Les limites sont celles des colonnes ADR_RUE (200) et ADR_VILLE (100).
    if ($rue === '' || mb_strlen($rue) > 200) {
        $valide = false;
    }
    if ($ville === '' || mb_strlen($ville) > 100) {
        $valide = false;
    }

    // preg_match : verifie que le texte correspond au modele.
    // /^[0-9]{4}$/ = du debut (^) a la fin ($), exactement 4 chiffres.
    if (!preg_match('/^[0-9]{4}$/', $npa)) {
        $valide = false;
    }
} else {
    // Adresse enregistree : les champs rue / NPA / ville sont ignores.
    // passer() verifiera que cette adresse appartient bien au client.
    $adresseId = (int) $choixAdresse;
}

// Liste blanche : seuls ces 3 codes sont acceptes.
// in_array : le mode recu est-il dans la liste ?
if (!in_array($mode, ['STANDARD', 'EXPRESS', 'RETRAIT_MAGASIN'], true)) {
    $valide = false;
}

if (!$valide) {
    header('Location: ../commander.php?commande=invalide');
    exit;
}

// Code promo (facultatif) : on previent le client plutot que de passer
// sa commande au plein tarif sans qu'il s'en rende compte.
if ($codePromo !== '' && Commande::erreurCodePromo($pdo, $_SESSION['client_id'], $codePromo) !== '') {
    header('Location: ../commander.php?commande=code');
    exit;
}

// --- 4. Enregistrement ---
// passer() recalcule lui-meme les prix, les frais de port et la remise
// a partir de la base : aucun montant ne vient du navigateur.
$numero = Commande::passer($pdo, $_SESSION['client_id'], $adresseId, $rue, $npa, $ville, $mode, $codePromo);

if ($numero === null) {
    // Panier vide, stock insuffisant ou adresse d'un autre client :
    // rien n'a ete enregistre.
    header('Location: ../panier.php?panier=commande');
    exit;
}

// --- 5. Paiement Stripe (si configure sur ce poste) ---
// La commande est enregistree en EN_ATTENTE, son stock est reserve.
// On envoie maintenant le client payer sur la page de Stripe. L'email
// de confirmation partira seulement apres le paiement
// (actions/paiement-retour.php).
if (stripe_est_configure()) {
    $commande = Commande::trouverPourClient($pdo, $numero, $_SESSION['client_id']);
    $client = Client::trouver($pdo, $_SESSION['client_id']);

    $session = stripe_creer_session($commande, $client->getEmail());

    if ($session === null) {
        // Stripe n'a pas repondu (cle invalide, pas d'Internet...) :
        // on ne garde pas une commande impossible a payer. Elle est
        // annulee, le stock rendu et le panier remis comme avant.
        Commande::annuler($pdo, $commande['COM_ID']);
        Commande::remettreAuPanier($pdo, $commande['COM_ID'], $_SESSION['client_id']);
        header('Location: ../commander.php?commande=paiement');
        exit;
    }

    // On garde le numero de session pour retrouver la commande au retour.
    Commande::enregistrerSessionPaiement($pdo, $commande['COM_ID'], $session['id']);

    // Redirection vers la page de paiement, hebergee chez Stripe.
    header('Location: ' . $session['url']);
    exit;
}

// --- 6. Sans Stripe : email de confirmation tout de suite ---
// La commande est deja enregistree : si l'email ne part pas, elle reste
// valable. Le resultat est garde en session pour que la page de
// confirmation dise au client si un email lui a ete envoye.
if (brevo_est_configure()) {
    $_SESSION['email_commande_envoye'] = brevo_envoyer_confirmation_commande($pdo, $_SESSION['client_id'], $numero);
}

// urlencode : prepare le numero pour etre place dans une adresse web.
// &nouvelle=1 : dit a la page de confirmation qu'on vient de passer
// cette commande, pour afficher "Merci" plutot qu'un simple recapitulatif.
header('Location: ../commande-confirmation.php?numero=' . urlencode($numero) . '&nouvelle=1');
exit;
