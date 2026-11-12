<?php
require __DIR__ . '/../../back-end/securite/garde-admin.php';
require __DIR__ . '/../../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../../back-end/classes/Produit.php';
require __DIR__ . '/../../back-end/classes/Categorie.php';
require __DIR__ . '/../../back-end/classes/Variante.php';
require __DIR__ . '/../../back-end/fonctions/icones.php';

$statsProduits = Produit::statistiques($pdo);
$nombreCategories = count(Categorie::toutesPourAdmin($pdo));
$statsVariantes = Variante::statistiquesStock($pdo);
require_once __DIR__ . '/../../back-end/classes/MessageContact.php';
$messagesNouveaux = MessageContact::nombreNouveaux($pdo);

$ongletActif = 'tableau-de-bord';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Espace admin - Pomodoro Store</title>
    <link rel="stylesheet" href="../styles/pomodoro.css?v=62">
</head>
<body>
<div class="admin-shell">
    <?php require __DIR__ . '/../morceaux-de-page/menu-admin.php'; ?>

    <main class="admin-main">
        <div class="admin-topbar">
            <div>
                <h1 class="titre">Bonjour <?= htmlspecialchars($_SESSION['admin_prenom']) ?></h1>
                <p class="sous-titre">Vue d'ensemble de la boutique.</p>
            </div>
        </div>

        <!-- Chaque carte est un lien vers la page qui la concerne. -->
        <div class="stats-grille">
            <a class="stat-carte" href="produits.php">
                <div>
                    <span class="stat-valeur"><?= $statsProduits['total'] ?></span>
                    <span class="stat-label">Produits</span>
                </div>
                <span class="stat-icone"><?= icone_admin('produit') ?></span>
            </a>
            <a class="stat-carte" href="produits.php">
                <div>
                    <span class="stat-valeur"><?= $statsProduits['actifs'] ?></span>
                    <span class="stat-label">Produits visibles</span>
                </div>
                <span class="stat-icone"><?= icone_admin('ok') ?></span>
            </a>
            <a class="stat-carte" href="categories.php">
                <div>
                    <span class="stat-valeur"><?= $nombreCategories ?></span>
                    <span class="stat-label">Catégories</span>
                </div>
                <span class="stat-icone"><?= icone_admin('categorie') ?></span>
            </a>
            <a class="stat-carte" href="produits.php">
                <div>
                    <span class="stat-valeur"><?= $statsVariantes['stockTotal'] ?></span>
                    <span class="stat-label">Articles en stock</span>
                </div>
                <span class="stat-icone"><?= icone_admin('pile') ?></span>
            </a>
            <a class="stat-carte <?= $messagesNouveaux > 0 ? 'stat-carte-alerte' : '' ?>" href="messages.php?statut=NOUVEAU">
                <div>
                    <span class="stat-valeur"><?= $messagesNouveaux ?></span>
                    <span class="stat-label">Messages non lus</span>
                </div>
                <span class="stat-icone"><?= icone_admin('message') ?></span>
            </a>
            <a class="stat-carte <?= $statsVariantes['ruptures'] > 0 ? 'stat-carte-alerte' : '' ?>" href="produits.php">
                <div>
                    <span class="stat-valeur"><?= $statsVariantes['ruptures'] ?></span>
                    <span class="stat-label">Variantes en rupture</span>
                </div>
                <span class="stat-icone"><?= icone_admin('alerte') ?></span>
            </a>
        </div>

        <div class="carte-admin">
            <h2 class="carte-admin-titre">Actions rapides</h2>
            <p class="carte-admin-soustitre">Les tâches les plus courantes, à un clic.</p>
            <div class="raccourcis-admin">
                <a class="raccourci-admin" href="produit-formulaire.php">
                    <?= icone_admin('plus') ?>
                    <span>
                        <span class="raccourci-admin-titre">Ajouter un produit</span>
                        <span class="raccourci-admin-texte">Créer une nouvelle fiche</span>
                    </span>
                </a>
                <a class="raccourci-admin" href="categorie-formulaire.php">
                    <?= icone_admin('categorie') ?>
                    <span>
                        <span class="raccourci-admin-titre">Ajouter une catégorie</span>
                        <span class="raccourci-admin-texte">Créer un nouveau rayon</span>
                    </span>
                </a>
                <a class="raccourci-admin" href="produits.php">
                    <?= icone_admin('produit') ?>
                    <span>
                        <span class="raccourci-admin-titre">Gérer le catalogue</span>
                        <span class="raccourci-admin-texte">Voir tous les produits</span>
                    </span>
                </a>
                <a class="raccourci-admin" href="../boutique.php" target="_blank">
                    <?= icone_admin('boutique') ?>
                    <span>
                        <span class="raccourci-admin-titre">Voir la boutique</span>
                        <span class="raccourci-admin-texte">Ouvrir le site public</span>
                    </span>
                </a>
            </div>
        </div>
    </main>
</div>
</body>
</html>
