<?php
require __DIR__ . '/../../back-end/securite/garde-admin.php';
require __DIR__ . '/../../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../../back-end/classes/Produit.php';
require __DIR__ . '/../../back-end/classes/Categorie.php';
require __DIR__ . '/../../back-end/fonctions/icones.php';

$id = $_GET['id'] ?? null;
$produit = $id ? Produit::trouver($pdo, $id) : null;

// Un id invalide dans l'URL -> retour a la liste plutot qu'une page cassee.
if ($id && !$produit) {
    header('Location: produits.php');
    exit;
}

$categories = Categorie::toutesPourAdmin($pdo);

$reference = $produit->reference ?? '';
$nom = $produit->nom ?? '';
$description = $produit->description ?? '';
$prix = $produit->prix ?? '';
$prixPromo = $produit->prixPromo ?? '';
$actif = $produit->actif ?? 0;
$type = $produit->type ?? '';
$categorieId = $produit->categorieId ?? '';
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reference = trim($_POST['reference'] ?? '');
    $nom = trim($_POST['nom'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $prix = $_POST['prix'] ?? '';
    $prixPromo = trim($_POST['prix_promo'] ?? '');
    $actif = isset($_POST['actif']) ? 1 : 0;
    $type = $_POST['type'] ?? '';
    $categorieId = $_POST['categorie_id'] ?? '';

    $categorieValide = false;
    foreach ($categories as $categorie) {
        if ((int) $categorie->id === (int) $categorieId) {
            $categorieValide = true;
            break;
        }
    }

    if ($reference === '' || $nom === '') {
        $erreur = "La référence et le nom sont obligatoires.";
    } elseif (!is_numeric($prix) || (float) $prix < 0) {
        $erreur = "Le prix doit être un nombre positif.";
    } elseif ($prixPromo !== '' && (!is_numeric($prixPromo) || (float) $prixPromo < 0)) {
        $erreur = "Le prix promotionnel doit être un nombre positif.";
    } elseif ($prixPromo !== '' && (float) $prixPromo >= (float) $prix) {
        $erreur = "Le prix promotionnel doit être inférieur au prix normal.";
    } elseif (!in_array($type, Produit::TYPES, true)) {
        $erreur = "Type de produit invalide.";
    } elseif (!$categorieValide) {
        $erreur = "Catégorie invalide.";
    } elseif (Produit::referenceDejaUtilisee($pdo, $reference, $id)) {
        $erreur = "Cette référence existe déjà.";
    } else {
        $prixPromoAEnregistrer = $prixPromo !== '' ? $prixPromo : null;
        if ($id) {
            Produit::modifier($pdo, $id, $reference, $nom, $description, $prix, $prixPromoAEnregistrer, $actif, $type, $categorieId);
            header('Location: produits.php');
        } else {
            $nouvelId = Produit::creer($pdo, $reference, $nom, $description, $prix, $prixPromoAEnregistrer, $actif, $type, $categorieId, $_SESSION['admin_id']);
            // On enchaine directement sur les variantes : c'est seulement
            // maintenant que le produit existe en base (une variante a
            // besoin d'un VAR_PRO_ID valide), et un produit doit avoir au
            // moins une variante pour etre complet (regle de gestion 5).
            header('Location: produit-variantes.php?id=' . $nouvelId);
        }
        exit;
    }
}

$ongletActif = 'produits';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $id ? 'Modifier' : 'Ajouter' ?> un produit - Admin Pomodoro Store</title>
    <link rel="stylesheet" href="../styles/pomodoro.css?v=62">
</head>
<body>
<div class="admin-shell">
    <?php require __DIR__ . '/../morceaux-de-page/menu-admin.php'; ?>

    <main class="admin-main">
        <a class="admin-fil" href="produits.php">← Retour aux produits</a>
        <div class="admin-topbar">
            <div>
                <h1 class="titre"><?= $id ? 'Modifier' : 'Ajouter' ?> un produit</h1>
                <p class="sous-titre"><?= $id ? htmlspecialchars($nom) : 'Nouvelle fiche produit' ?></p>
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
            <label for="reference">Référence</label>
            <input type="text" id="reference" name="reference" value="<?= htmlspecialchars($reference) ?>" required autofocus>
        </div>
        <div class="champ">
            <label for="nom">Nom</label>
            <input type="text" id="nom" name="nom" value="<?= htmlspecialchars($nom) ?>" required>
        </div>
        <div class="champ">
            <label for="description">Description</label>
            <input type="text" id="description" name="description" value="<?= htmlspecialchars($description) ?>">
        </div>
        <div class="champ">
            <label for="prix">Prix (CHF)</label>
            <input type="number" step="0.01" min="0" id="prix" name="prix" value="<?= htmlspecialchars((string) $prix) ?>" required>
        </div>
        <div class="champ">
            <label for="prix_promo">Prix promotionnel (CHF) <span class="facultatif">(facultatif)</span></label>
            <input type="number" step="0.01" min="0" id="prix_promo" name="prix_promo" value="<?= htmlspecialchars((string) $prixPromo) ?>">
            <span class="aide">Laisse vide si le produit n'est pas en promotion.</span>
        </div>
        <div class="champ">
            <label for="type">Type</label>
            <select id="type" name="type" required>
                <option value="">-- choisir --</option>
                <?php foreach (Produit::TYPES as $valeur): ?>
                    <option value="<?= $valeur ?>" <?= $type === $valeur ? 'selected' : '' ?>><?= Produit::libelleType($valeur) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="champ">
            <label for="categorie_id">Catégorie</label>
            <select id="categorie_id" name="categorie_id" required>
                <option value="">-- choisir --</option>
                <?php foreach ($categories as $categorie): ?>
                    <option value="<?= (int) $categorie->id ?>" <?= (int) $categorieId === (int) $categorie->id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($categorie->nom) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="champ champ-case">
            <label><input type="checkbox" name="actif" <?= $actif ? 'checked' : '' ?>> Visible sur le site</label>
        </div>
        <button type="submit" class="bouton">Enregistrer</button>
    </form>
    <?php if ($id): ?>
        <p class="note-bas"><a href="produit-variantes.php?id=<?= (int) $id ?>">Gérer les variantes</a></p>
        <p class="note-bas"><a href="produit-photos.php?id=<?= (int) $id ?>">Gérer les photos</a></p>
    <?php endif; ?>
    <p class="note-bas"><a href="produits.php">Annuler</a></p>
    </div>
    </main>
</div>
</body>
</html>
