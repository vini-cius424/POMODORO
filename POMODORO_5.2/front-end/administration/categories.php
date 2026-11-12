<?php
require __DIR__ . '/../../back-end/securite/garde-admin.php';
require __DIR__ . '/../../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../../back-end/classes/Categorie.php';
require __DIR__ . '/../../back-end/fonctions/icones.php';

$categories = Categorie::toutesPourAdmin($pdo);
$ongletActif = 'categories';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Catégories - Admin Pomodoro Store</title>
    <link rel="stylesheet" href="../styles/pomodoro.css?v=62">
</head>
<body>
<div class="admin-shell">
    <?php require __DIR__ . '/../morceaux-de-page/menu-admin.php'; ?>

    <main class="admin-main">
        <div class="admin-topbar">
            <div>
                <h1 class="titre">Catégories</h1>
                <p class="sous-titre">Les rayons du magasin (Homme, Femme, Enfant...).</p>
            </div>
            <a class="bouton-secondaire bouton-avec-icone" href="categorie-formulaire.php"><?= icone_admin('plus') ?> Ajouter une catégorie</a>
        </div>

        <div class="carte-admin">
            <div class="tableau-scroll">
                <table class="tableau-admin">
                    <tr>
                        <th>Nom</th>
                        <th>Description</th>
                        <th>Ordre</th>
                        <th>Active</th>
                        <th></th>
                    </tr>
                    <?php foreach ($categories as $categorie): ?>
                        <tr>
                            <td><?= htmlspecialchars($categorie->nom) ?></td>
                            <td><?= htmlspecialchars($categorie->description ?? '') ?></td>
                            <td><?= (int) $categorie->ordre ?></td>
                            <td>
                                <span class="badge <?= $categorie->actif ? 'badge-oui' : 'badge-non' ?>">
                                    <?= $categorie->actif ? 'Oui' : 'Non' ?>
                                </span>
                            </td>
                            <td class="actions">
                                <a class="lien-action" href="categorie-formulaire.php?id=<?= (int) $categorie->id ?>">Modifier</a>
                                <form method="post" action="actions/categorie-activer-desactiver.php">
                                    <input type="hidden" name="id" value="<?= (int) $categorie->id ?>">
                                    <button type="submit" class="lien-action"><?= $categorie->actif ? 'Désactiver' : 'Activer' ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$categories): ?>
                        <tr><td colspan="5" class="tableau-vide">Aucune catégorie pour l'instant.</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </main>
</div>
</body>
</html>
