<?php
$versRacine = '../../'; // profondeur de ce fichier, pour la redirection du garde
require __DIR__ . '/../../../back-end/securite/garde-admin.php';
require __DIR__ . '/../../../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../../../back-end/classes/Produit.php';
require __DIR__ . '/../../../back-end/classes/ImageProduit.php';
require __DIR__ . '/../../../back-end/classes/Variante.php';

$produitId = $_POST['produit_id'] ?? null;
$couleur = $_POST['couleur'] ?? '';
if (!in_array($couleur, Variante::COULEURS, true)) {
    $couleur = null;
}
$produit = $produitId ? Produit::trouver($pdo, $produitId) : null;

if (!$produit) {
    header('Location: ../produits.php');
    exit;
}

// Liste blanche : jamais de redirection vers une URL fournie telle quelle.
$pagesRetour = ['produit-photos.php', 'produit-variantes.php', 'produits.php'];
$pageRetour = in_array($_POST['retour'] ?? '', $pagesRetour, true) ? $_POST['retour'] : 'produit-photos.php';
$retour = '../' . $pageRetour . ($pageRetour === 'produits.php' ? '' : '?id=' . (int) $produitId);
$separateur = str_contains($retour, '?') ? '&' : '?';

if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    header('Location: ' . $retour . $separateur . 'erreur=upload');
    exit;
}

$fichier = $_FILES['photo'];

// 4 Mo maximum.
if ($fichier['size'] > 4 * 1024 * 1024) {
    header('Location: ' . $retour . $separateur . 'erreur=taille');
    exit;
}

// getimagesize() lit reellement le contenu du fichier au lieu de faire
// confiance au type envoye par le navigateur, facilement falsifiable.
$infos = getimagesize($fichier['tmp_name']);
$extensions = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
];

if (!$infos || !isset($extensions[$infos['mime']])) {
    header('Location: ' . $retour . $separateur . 'erreur=type');
    exit;
}

// Nom de fichier genere aleatoirement : jamais le nom fourni par
// l'utilisateur (risque d'ecrasement ou de chemin bricole).
$nomFichier = bin2hex(random_bytes(16)) . '.' . $extensions[$infos['mime']];
$dossier = __DIR__ . '/../../photos-produits/';

// Le dossier n'est pas versionne (.gitignore) : sur un serveur ou il n'a
// jamais ete envoye, on le cree plutot que de perdre la photo.
// Le @ masque l'avertissement PHP en cas d'echec : il serait affiche
// avant la redirection, et l'echec est traite juste en dessous.
if (!is_dir($dossier)) {
    @mkdir($dossier, 0755, true);
}

// Si le fichier n'a pas pu etre ecrit (dossier absent, droits
// insuffisants...), on n'enregistre rien en base : sinon la boutique
// afficherait une image cassee.
if (!@move_uploaded_file($fichier['tmp_name'], $dossier . $nomFichier)) {
    header('Location: ' . $retour . $separateur . 'erreur=dossier');
    exit;
}

// Chemin enregistre en base : relatif a front-end/, pour que les pages de
// la boutique (a la racine) l'utilisent tel quel.
$nouvelId = ImageProduit::ajouter($pdo, $produitId, 'photos-produits/' . $nomFichier, $couleur);

// Depuis la liste des produits, la vignette cliquee doit devenir la photo
// affichee : on force la principale plutot que de se fier au "premiere
// image = principale" par defaut, qui ne vaut que pour un produit sans
// aucune photo.
if (!empty($_POST['definir_principale'])) {
    ImageProduit::definirPrincipale($pdo, $produitId, $nouvelId);
}

header('Location: ' . $retour);
exit;
