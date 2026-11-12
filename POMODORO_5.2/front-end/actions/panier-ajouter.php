<?php
// =====================================================================
// POMODORO STORE - Action : ajouter une variante au panier
//
// Recoit le formulaire "Ajouter au panier", depuis deux endroits :
//
//  - la fiche produit (envoi normal du formulaire) : la page verifie,
//    ajoute, puis renvoie sur la fiche avec un message dans l'URL :
//        ?panier=ajoute   l'article a ete ajoute
//        ?panier=stock    pas assez de stock
//
//  - une carte de la boutique (envoi en arriere-plan par
//    javascript/catalogue.js, avec l'en-tete "Accept: application/json") :
//    la page repond en JSON, le client reste sur la boutique et garde ses
//    filtres. {ok, message, articles} ou {ok: false, connexion: true}.
// =====================================================================

require __DIR__ . '/../../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../../back-end/classes/Produit.php';
require __DIR__ . '/../../back-end/classes/Variante.php';
require __DIR__ . '/../../back-end/classes/Panier.php';

session_start();

$enJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

// Reponse JSON (ajout depuis la boutique), puis fin du script.
function repondre_json($donnees)
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($donnees);
    exit;
}

// --- 1. Le client doit etre connecte (regle A) ---
// Un panier appartient toujours a un client : un visiteur est envoye
// vers la page de connexion.
if (empty($_SESSION['client_id'])) {
    if ($enJson) {
        repondre_json(['ok' => false, 'connexion' => true]);
    }
    header('Location: ../connexion.php');
    exit;
}

// On n'accepte que l'envoi du formulaire (POST), pas un simple lien.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../boutique.php');
    exit;
}

// --- 2. Lecture du formulaire ---
// (int) convertit en nombre entier : un texte bricole devient 0.
$varianteId = (int) ($_POST['variante_id'] ?? 0);
$quantite   = (int) ($_POST['quantite'] ?? 1);

// Une quantite de 0 ou negative n'a pas de sens.
if ($quantite < 1) {
    $quantite = 1;
}

// --- 3. La variante doit exister, et son produit etre en vente ---
$variante = Variante::trouver($pdo, $varianteId);
$produit  = $variante ? Produit::trouver($pdo, $variante->produitId) : null;

if (!$variante || !$produit || !$produit->estEnVente()) {
    if ($enJson) {
        repondre_json(['ok' => false, 'message' => "Choisissez une taille disponible."]);
    }
    header('Location: ../boutique.php');
    exit;
}

// Adresse de retour : la fiche du produit concerne.
$retour = '../fiche-produit.php?id=' . $produit->id;

// --- 4. Controle du stock ---
// On compte aussi ce qui est deja dans le panier : si le panier contient
// 2 pieces et qu'il en reste 3 en stock, on ne peut en ajouter qu'une.
$panierId       = Panier::idPourClient($pdo, $_SESSION['client_id']);
$dejaDansPanier = Panier::quantiteVariante($pdo, $panierId, $variante->id);

if ($dejaDansPanier + $quantite > (int) $variante->stock) {
    if ($enJson) {
        repondre_json(['ok' => false, 'message' => "Stock insuffisant pour cette taille."]);
    }
    header('Location: ' . $retour . '&panier=stock');
    exit;
}

// --- 5. Tout est bon : on ajoute ---
Panier::ajouterVariante($pdo, $panierId, $variante->id, $quantite);

if ($enJson) {
    // "articles" : nouveau total du panier, pour le compteur de l'en-tete.
    repondre_json([
        'ok'       => true,
        'message'  => "Ajouté au panier.",
        'articles' => Panier::nombreArticles($pdo, $_SESSION['client_id'])
    ]);
}

header('Location: ' . $retour . '&panier=ajoute');
exit;
