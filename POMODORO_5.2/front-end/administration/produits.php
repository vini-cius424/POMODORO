<?php
require __DIR__ . '/../../back-end/securite/garde-admin.php';
require __DIR__ . '/../../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../../back-end/classes/Produit.php';
require __DIR__ . '/../../back-end/fonctions/icones.php';

// Tache 18 : texte tape dans le champ de recherche (produits.php?recherche=pull).
// Vide ou absent = tous les produits.
$recherche = trim($_GET['recherche'] ?? '');
$produits = Produit::listePourAdmin($pdo, $recherche);
$ongletActif = 'produits';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Produits - Admin Pomodoro Store</title>
    <link rel="stylesheet" href="../styles/pomodoro.css?v=68">
</head>
<body>
<div class="admin-shell">
    <?php require __DIR__ . '/../morceaux-de-page/menu-admin.php'; ?>

    <main class="admin-main">
        <div class="admin-topbar">
            <div>
                <h1 class="titre">Produits</h1>
                <p class="sous-titre">Le catalogue vendu en boutique.</p>
            </div>
            <a class="bouton-secondaire bouton-avec-icone" href="produit-formulaire.php"><?= icone_admin('plus') ?> Ajouter un produit</a>
        </div>

        <!-- Recherche (tache 18) : method="get" met le texte dans l'URL,
             comme les filtres de la boutique. La page se recharge avec
             ?recherche=... et le champ garde ce qui a ete tape. -->
        <form class="recherche-produits" method="get" action="produits.php">
            <label for="recherche" class="visuellement-cache">Rechercher un produit</label>
            <input type="search" id="recherche" name="recherche"
                   value="<?= htmlspecialchars($recherche) ?>"
                   placeholder="Rechercher par nom ou référence">
            <button type="submit" class="bouton-secondaire">Rechercher</button>
            <?php if ($recherche !== ''): ?>
                <a class="lien-action" href="produits.php">Effacer</a>
            <?php endif; ?>
        </form>
        <?php if ($recherche !== ''): ?>
            <p class="recherche-resultat"><?= count($produits) ?> produit(s) pour « <?= htmlspecialchars($recherche) ?> »</p>
        <?php endif; ?>

        <div class="carte-admin">
            <div class="tableau-scroll">
                <table class="tableau-admin">
                    <tr>
                        <th>Produit</th>
                        <th>Catégorie</th>
                        <th>Prix</th>
                        <th>Actif</th>
                        <th></th>
                        <th></th>
                    </tr>
                    <?php foreach ($produits as $produit): ?>
                        <tr>
                            <td>
                                <div class="vignette-produit">
                                    <form method="post" action="actions/photo-ajouter.php" enctype="multipart/form-data" class="vignette-produit-photo">
                                        <input type="hidden" name="produit_id" value="<?= (int) $produit->id ?>">
                                        <input type="hidden" name="retour" value="produits.php">
                                        <input type="hidden" name="definir_principale" value="1">
                                        <input type="file" id="photo-produit-<?= (int) $produit->id ?>" name="photo"
                                               accept="image/jpeg,image/png,image/webp,image/gif"
                                               onchange="this.form.submit()" class="visuellement-cache">
                                        <label class="miniature" for="photo-produit-<?= (int) $produit->id ?>" title="Changer la photo principale">
                                            <?php if ($produit->imagePrincipale): ?>
                                                <img src="../<?= htmlspecialchars($produit->imagePrincipale) ?>" alt="">
                                            <?php else: ?>
                                                <?= icone_admin('photo') ?>
                                            <?php endif; ?>
                                        </label>
                                    </form>
                                    <span>
                                        <span class="nom-produit"><?= htmlspecialchars($produit->nom) ?></span>
                                        <span class="ref-produit"><?= htmlspecialchars($produit->reference) ?></span>
                                    </span>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($produit->categorieNom) ?></td>
                            <td><?= number_format((float) $produit->prix, 2) ?> CHF</td>
                            <td>
                                <span class="badge <?= $produit->actif ? 'badge-oui' : 'badge-non' ?>">
                                    <?= $produit->actif ? 'Oui' : 'Non' ?>
                                </span>
                            </td>
                            <td class="actions">
                                <a class="lien-action" href="produit-variantes.php?id=<?= (int) $produit->id ?>">Variantes</a>
                                <a class="lien-action" href="produit-photos.php?id=<?= (int) $produit->id ?>">Photos</a>
                                <a class="lien-action" href="produit-formulaire.php?id=<?= (int) $produit->id ?>">Modifier</a>
                                <form method="post" action="actions/produit-activer-desactiver.php">
                                    <input type="hidden" name="id" value="<?= (int) $produit->id ?>">
                                    <button type="submit" class="lien-action"><?= $produit->actif ? 'Désactiver' : 'Activer' ?></button>
                                </form>
                            </td>
                            <!-- Derniere colonne : alerte si au moins une variante est a 0
                                 en stock. Icone seule, meme style que les actions ; mene
                                 aux variantes pour remettre du stock. title = texte au survol. -->
                            <td class="colonne-rupture">
                                <?php if ($produit->variantesEnRupture > 0): ?>
                                    <a class="lien-action lien-action-danger lien-action-icone" href="produit-variantes.php?id=<?= (int) $produit->id ?>"
                                       title="<?= $produit->variantesEnRupture ?> variante(s) en rupture de stock"
                                       aria-label="<?= $produit->variantesEnRupture ?> variante(s) en rupture de stock"><?= icone_admin('alerte') ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$produits && $recherche !== ''): ?>
                        <tr><td colspan="6" class="tableau-vide">Aucun produit ne correspond à « <?= htmlspecialchars($recherche) ?> ».</td></tr>
                    <?php elseif (!$produits): ?>
                        <tr><td colspan="6" class="tableau-vide">Aucun produit pour l'instant.</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </main>
</div>
</body>
</html>
