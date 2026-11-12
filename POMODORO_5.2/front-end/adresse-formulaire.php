<?php
// =====================================================================
// POMODORO STORE - Ajouter / modifier une adresse (tache 11, C2)
//
// Une seule page pour les deux cas, comme categorie-formulaire.php cote
// admin : sans ?id, c'est un ajout ; avec ?id=X, une modification de
// l'adresse X DU CLIENT CONNECTE (Adresse::trouverPourClient verifie
// l'appartenance).
// =====================================================================

require __DIR__ . '/../back-end/securite/garde-client.php';
require __DIR__ . '/../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../back-end/classes/Adresse.php';

$id = $_GET['id'] ?? null;
$adresse = $id ? Adresse::trouverPourClient($pdo, $id, $_SESSION['client_id']) : null;

// Un id invalide ou qui n'appartient pas au client -> retour a la liste.
if ($id && !$adresse) {
    header('Location: mon-compte.php?section=adresses');
    exit;
}

$rue     = $adresse['ADR_RUE'] ?? '';
$npa     = $adresse['ADR_NPA'] ?? '';
$ville   = $adresse['ADR_VILLE'] ?? '';
$pays    = $adresse['ADR_PAYS'] ?? 'Suisse';
$erreur  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rue     = trim($_POST['rue'] ?? '');
    $npa     = trim($_POST['npa'] ?? '');
    $ville   = trim($_POST['ville'] ?? '');
    $pays    = trim($_POST['pays'] ?? '');

    if ($rue === '' || mb_strlen($rue) > 200) {
        $erreur = "La rue est obligatoire (200 caractères maximum).";
    } elseif (!preg_match('/^[0-9]{4}$/', $npa)) {
        $erreur = "Le NPA doit contenir exactement 4 chiffres.";
    } elseif ($ville === '' || mb_strlen($ville) > 100) {
        $erreur = "La ville est obligatoire (100 caractères maximum).";
    } elseif ($pays === '' || mb_strlen($pays) > 100) {
        $erreur = "Le pays est obligatoire.";
    } else {
        if ($id) {
            Adresse::modifier($pdo, $id, $_SESSION['client_id'], $rue, $npa, $ville, $pays);
        } else {
            Adresse::ajouter($pdo, $_SESSION['client_id'], $rue, $npa, $ville, $pays);
        }
        header('Location: mon-compte.php?section=adresses');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $id ? 'Modifier' : 'Ajouter' ?> une adresse - Pomodoro Store</title>
    <link rel="stylesheet" href="styles/pomodoro.css?v=54">
</head>
<body>
<?php require __DIR__ . '/morceaux-de-page/entete.php'; ?>
<div class="page-client">
    <h1 class="titre"><?= $id ? 'Modifier' : 'Ajouter' ?> une adresse</h1>

    <?php if ($erreur): ?>
        <div class="encadre encadre-echec">
            <p class="encadre-titre">Le formulaire contient une erreur</p>
            <p><?= htmlspecialchars($erreur) ?></p>
        </div>
    <?php endif; ?>

    <form class="formulaire" method="post" action="adresse-formulaire.php<?= $id ? '?id=' . (int) $id : '' ?>">
        <div class="champ">
            <label for="rue">Rue et numéro</label>
            <input type="text" id="rue" name="rue" maxlength="200" value="<?= htmlspecialchars($rue) ?>" required autofocus>
        </div>
        <div class="champ champ-double">
            <div>
                <label for="npa">NPA</label>
                <input type="text" id="npa" name="npa" maxlength="4" value="<?= htmlspecialchars($npa) ?>"
                       pattern="[0-9]{4}" inputmode="numeric" required>
            </div>
            <div>
                <label for="ville">Ville</label>
                <input type="text" id="ville" name="ville" maxlength="100" value="<?= htmlspecialchars($ville) ?>" required>
            </div>
        </div>
        <div class="champ">
            <label for="pays">Pays</label>
            <input type="text" id="pays" name="pays" maxlength="100" value="<?= htmlspecialchars($pays) ?>" required>
        </div>

        <button type="submit" class="bouton">Enregistrer</button>
    </form>
    <p class="note-bas"><a href="mon-compte.php?section=adresses">Annuler</a></p>
</div>
<?php require __DIR__ . '/morceaux-de-page/pied.php'; ?>
</body>
</html>
