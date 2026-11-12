<?php
require __DIR__ . '/../../back-end/securite/garde-admin.php';
require __DIR__ . '/../../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../../back-end/classes/Produit.php';
require __DIR__ . '/../../back-end/classes/ImageProduit.php';
require __DIR__ . '/../../back-end/classes/Variante.php';
require __DIR__ . '/../../back-end/fonctions/icones.php';
require __DIR__ . '/../../back-end/fonctions/couleurs.php';

$produitId = $_GET['id'] ?? null;
$produit = $produitId ? Produit::trouver($pdo, $produitId) : null;

if (!$produit) {
    header('Location: produits.php');
    exit;
}

$images = ImageProduit::pourProduit($pdo, $produitId);
$erreur = $_GET['erreur'] ?? '';

$messages = [
    'upload' => "L'envoi a échoué, réessaie.",
    'taille' => "Image trop lourde (4 Mo maximum).",
    'type' => "Format non accepté (jpg, png, webp ou gif uniquement).",
    'dossier' => "La photo n'a pas pu être enregistrée sur le serveur : vérifie que le dossier photos-produits existe et qu'il est accessible en écriture.",
];

$ongletActif = 'produits';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Photos de <?= htmlspecialchars($produit->nom) ?> - Admin Pomodoro Store</title>
    <link rel="stylesheet" href="../styles/pomodoro.css?v=62">
</head>
<body>
<div class="admin-shell">
    <?php require __DIR__ . '/../morceaux-de-page/menu-admin.php'; ?>

    <main class="admin-main">
        <a class="admin-fil" href="produits.php">← Retour aux produits</a>
        <div class="admin-topbar">
            <div>
                <h1 class="titre">Photos de <?= htmlspecialchars($produit->nom) ?></h1>
            </div>
        </div>

    <?php if (isset($messages[$erreur])): ?>
        <div class="encadre encadre-echec">
            <p class="encadre-titre">Envoi impossible</p>
            <p><?= htmlspecialchars($messages[$erreur]) ?></p>
        </div>
    <?php endif; ?>

    <div class="carte-admin">
    <p class="carte-admin-soustitre">Les flèches changent l'ordre d'affichage (photo principale par défaut = la première).</p>
    <div class="galerie">
        <?php foreach ($images as $index => $image): ?>
            <div class="vignette">
                <img src="../<?= htmlspecialchars($image->chemin) ?>" alt="">
                <span class="badge-couleur-image">
                    <?= $image->couleur ? htmlspecialchars(couleur_libelle($image->couleur)) : 'Photo générale' ?>
                </span>

                <div class="vignette-ordre">
                    <form method="post" action="actions/photo-deplacer.php">
                        <input type="hidden" name="id" value="<?= (int) $image->id ?>">
                        <input type="hidden" name="produit_id" value="<?= (int) $produitId ?>">
                        <input type="hidden" name="sens" value="haut">
                        <button type="submit" class="lien-action" <?= $index === 0 ? 'disabled' : '' ?> title="Déplacer plus tôt">↑</button>
                    </form>
                    <form method="post" action="actions/photo-deplacer.php">
                        <input type="hidden" name="id" value="<?= (int) $image->id ?>">
                        <input type="hidden" name="produit_id" value="<?= (int) $produitId ?>">
                        <input type="hidden" name="sens" value="bas">
                        <button type="submit" class="lien-action" <?= $index === count($images) - 1 ? 'disabled' : '' ?> title="Déplacer plus tard">↓</button>
                    </form>
                </div>

                <?php if ($image->estPrincipale): ?>
                    <span class="badge-principale">Principale</span>
                <?php else: ?>
                    <form method="post" action="actions/photo-principale.php">
                        <input type="hidden" name="id" value="<?= (int) $image->id ?>">
                        <input type="hidden" name="produit_id" value="<?= (int) $produitId ?>">
                        <button type="submit" class="lien-action">Définir comme principale</button>
                    </form>
                <?php endif; ?>
                <form method="post" action="actions/photo-supprimer.php" onsubmit="return confirm('Supprimer cette photo ?');">
                    <input type="hidden" name="id" value="<?= (int) $image->id ?>">
                    <input type="hidden" name="produit_id" value="<?= (int) $produitId ?>">
                    <button type="submit" class="lien-action lien-action-danger">Supprimer</button>
                </form>
            </div>
        <?php endforeach; ?>

        <?php if (!$images): ?>
            <p class="sous-titre">Aucune photo pour ce produit pour l'instant.</p>
        <?php endif; ?>
    </div>
    </div>

    <div class="carte-admin carte-admin-formulaire">
    <h2 class="carte-admin-titre">Ajouter une photo</h2>
    <form class="formulaire formulaire-admin" method="post" action="actions/photo-ajouter.php" enctype="multipart/form-data">
        <input type="hidden" name="produit_id" value="<?= (int) $produitId ?>">
        <div class="champ">
            <label for="photo">Ajouter une photo</label>
            <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp,image/gif" required>
        </div>
        <div class="champ">
            <label for="couleur">Couleur <span class="facultatif">(facultatif)</span></label>
            <select id="couleur" name="couleur">
                <option value="">Aucune (photo générale)</option>
                <?php foreach (Variante::COULEURS as $valeur): ?>
                    <option value="<?= $valeur ?>"><?= htmlspecialchars(couleur_libelle($valeur)) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="aide">Associe la photo à une variante couleur pour l'afficher dans le catalogue.</span>
        </div>
        <button type="submit" class="bouton">Envoyer</button>
    </form>
    </div>

    <p class="note-bas"><a href="produit-formulaire.php?id=<?= (int) $produitId ?>">Retour au produit</a></p>
    </main>
</div>
</body>
</html>
