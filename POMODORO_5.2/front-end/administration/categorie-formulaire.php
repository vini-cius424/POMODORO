<?php
require __DIR__ . '/../../back-end/securite/garde-admin.php';
require __DIR__ . '/../../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../../back-end/classes/Categorie.php';

$id = $_GET['id'] ?? null;
$categorie = $id ? Categorie::trouver($pdo, $id) : null;

// Un id invalide dans l'URL -> retour a la liste plutot qu'une page cassee.
if ($id && !$categorie) {
    header('Location: categories.php');
    exit;
}

$nom = $categorie->nom ?? '';
$description = $categorie->description ?? '';
$ordre = $categorie->ordre ?? 0;
$erreur = '';
$ongletActif = 'categories';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $ordre = (int) ($_POST['ordre'] ?? 0);

    if ($nom === '') {
        $erreur = "Le nom est obligatoire.";
    } elseif ($ordre < 0) {
        $erreur = "L'ordre doit être positif ou nul.";
    } elseif (Categorie::nomDejaUtilise($pdo, $nom, $id)) {
        $erreur = "Une catégorie avec ce nom existe déjà.";
    } else {
        if ($id) {
            Categorie::modifier($pdo, $id, $nom, $description, $ordre);
        } else {
            Categorie::creer($pdo, $nom, $description, $ordre);
        }
        header('Location: categories.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $id ? 'Modifier' : 'Ajouter' ?> une catégorie - Admin Pomodoro Store</title>
    <link rel="stylesheet" href="../styles/pomodoro.css?v=62">
</head>
<body>
<div class="admin-shell">
    <?php require __DIR__ . '/../morceaux-de-page/menu-admin.php'; ?>

    <main class="admin-main">
        <a class="admin-fil" href="categories.php">← Retour aux catégories</a>
        <div class="admin-topbar">
            <div>
                <h1 class="titre"><?= $id ? 'Modifier' : 'Ajouter' ?> une catégorie</h1>
            </div>
        </div>

    <?php if ($erreur): ?>
        <div class="encadre encadre-echec">
            <p class="encadre-titre">Le formulaire contient une erreur</p>
            <p><?= htmlspecialchars($erreur) ?></p>
        </div>
    <?php endif; ?>

    <div class="carte-admin carte-admin-formulaire">
    <form class="formulaire formulaire-admin" method="post">
        <div class="champ">
            <label for="nom">Nom</label>
            <input type="text" id="nom" name="nom" value="<?= htmlspecialchars($nom) ?>" required autofocus>
        </div>
        <div class="champ">
            <label for="description">Description</label>
            <input type="text" id="description" name="description" value="<?= htmlspecialchars($description) ?>">
        </div>
        <div class="champ">
            <label for="ordre">Ordre d'affichage</label>
            <input type="number" id="ordre" name="ordre" value="<?= (int) $ordre ?>" min="0">
        </div>
        <button type="submit" class="bouton">Enregistrer</button>
    </form>
    <p class="note-bas"><a href="categories.php">Annuler</a></p>
    </div>
    </main>
</div>
</body>
</html>
