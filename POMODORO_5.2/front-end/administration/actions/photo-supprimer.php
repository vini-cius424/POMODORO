<?php
$versRacine = '../../'; // profondeur de ce fichier, pour la redirection du garde
require __DIR__ . '/../../../back-end/securite/garde-admin.php';
require __DIR__ . '/../../../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../../../back-end/classes/ImageProduit.php';

$imageId = $_POST['id'] ?? null;
$produitId = $_POST['produit_id'] ?? null;

// Liste blanche : jamais de redirection vers une URL fournie telle quelle.
$pagesRetour = ['produit-photos.php', 'produit-variantes.php'];
$pageRetour = in_array($_POST['retour'] ?? '', $pagesRetour, true) ? $_POST['retour'] : 'produit-photos.php';

if ($imageId) {
    $image = ImageProduit::trouver($pdo, $imageId);

    if ($image) {
        // Suppression logique : la ligne et le fichier sur le disque sont
        // conserves, la photo n'est simplement plus affichee.
        ImageProduit::supprimer($pdo, $imageId);

        // Si l'image supprimee etait la principale, on en repromeut une
        // autre : un produit qui a encore des photos doit toujours en
        // avoir une principale (RG4).
        if ($image->estPrincipale) {
            $restantes = ImageProduit::pourProduit($pdo, $image->produitId);
            if ($restantes) {
                ImageProduit::definirPrincipale($pdo, $image->produitId, $restantes[0]->id);
            }
        }
    }
}

header('Location: ../' . $pageRetour . '?id=' . (int) $produitId);
exit;
