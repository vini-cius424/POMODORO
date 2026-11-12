<?php
require __DIR__ . '/../../back-end/securite/garde-admin.php';
require __DIR__ . '/../../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../../back-end/classes/Produit.php';
require __DIR__ . '/../../back-end/classes/Variante.php';
require __DIR__ . '/../../back-end/classes/ImageProduit.php';
require __DIR__ . '/../../back-end/fonctions/couleurs.php';
require __DIR__ . '/../../back-end/fonctions/icones.php';

$produitId = $_GET['id'] ?? null;
$produit = $produitId ? Produit::trouver($pdo, $produitId) : null;

if (!$produit) {
    header('Location: produits.php');
    exit;
}

$variantes = Variante::pourProduit($pdo, $produitId);
$photosParCouleur = ImageProduit::vignetteParCouleur($pdo, [$produitId]);

$variantesIndex = [];
$valeursGrille = [];
foreach (Variante::TAILLES as $taille) {
    foreach (Variante::COULEURS as $couleur) {
        $valeursGrille[$taille][$couleur] = '';
    }
}
foreach ($variantes as $variante) {
    $variantesIndex[$variante->taille][$variante->couleur] = $variante;
    $valeursGrille[$variante->taille][$variante->couleur] = (string) $variante->stock;
}

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $soumis = $_POST['stock'] ?? [];

    // On relit d'abord tout ce qui a ete saisi, pour pouvoir reafficher la
    // grille telle quelle si une case contient une valeur invalide. Une
    // case laissee vide garde sa valeur par defaut (stock existant, ou
    // rien) : elle est ignoree, jamais traitee comme une suppression.
    foreach (Variante::TAILLES as $taille) {
        foreach (Variante::COULEURS as $couleur) {
            if (isset($soumis[$taille][$couleur]) && trim($soumis[$taille][$couleur]) !== '') {
                $valeursGrille[$taille][$couleur] = trim($soumis[$taille][$couleur]);
            }
        }
    }

    foreach (Variante::TAILLES as $taille) {
        foreach (Variante::COULEURS as $couleur) {
            $valeur = $valeursGrille[$taille][$couleur];
            if ($valeur === '' || (is_numeric($valeur) && (int) $valeur >= 0)) {
                continue;
            }
            $erreur = "Le stock de $taille / " . couleur_libelle($couleur) . " doit être un nombre positif ou nul.";
            break 2;
        }
    }

    // Taille unique OU tailles classiques, jamais les deux : un produit
    // est soit un accessoire a taille unique (casquette, sac...), soit un
    // vetement decline en tailles (XS a XXL).
    // Le JavaScript de la page grise deja les cases concernees, mais le
    // serveur refait le controle : le navigateur peut etre contourne.
    if (!$erreur) {
        $tailleUniqueRemplie = false;
        $autresTaillesRemplies = false;

        foreach (Variante::TAILLES as $taille) {
            foreach (Variante::COULEURS as $couleur) {
                if ($valeursGrille[$taille][$couleur] === '') {
                    continue;
                }
                if ($taille === 'UNIQUE') {
                    $tailleUniqueRemplie = true;
                } else {
                    $autresTaillesRemplies = true;
                }
            }
        }

        if ($tailleUniqueRemplie && $autresTaillesRemplies) {
            $erreur = "Un produit ne peut pas avoir à la fois la taille unique et d'autres tailles. "
                    . "Supprimez (×) les variantes de l'une des deux colonnes.";
        }
    }

    if (!$erreur) {
        foreach (Variante::TAILLES as $taille) {
            foreach (Variante::COULEURS as $couleur) {
                $valeur = $valeursGrille[$taille][$couleur];
                if ($valeur === '') {
                    continue;
                }

                $stock = (int) $valeur;
                $existante = $variantesIndex[$taille][$couleur] ?? null;

                if ($existante) {
                    if ($stock !== (int) $existante->stock) {
                        Variante::modifierStock($pdo, $existante->id, $stock);
                    }
                } else {
                    Variante::creer($pdo, $produitId, $produit->reference, $taille, $couleur, $stock);
                }
            }
        }
        header('Location: produit-variantes.php?id=' . (int) $produitId);
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
    <title>Variantes de <?= htmlspecialchars($produit->nom) ?> - Admin Pomodoro Store</title>
    <link rel="stylesheet" href="../styles/pomodoro.css?v=62">
</head>
<body>
<div class="admin-shell">
    <?php require __DIR__ . '/../morceaux-de-page/menu-admin.php'; ?>

    <main class="admin-main">
        <a class="admin-fil" href="produits.php">← Retour aux produits</a>
        <div class="admin-topbar">
            <div>
                <h1 class="titre">Variantes de <?= htmlspecialchars($produit->nom) ?></h1>
                <p class="sous-titre">Une case = le stock pour cette taille et cette couleur.</p>
            </div>
        </div>

    <?php if ($erreur): ?>
        <div class="encadre encadre-echec">
            <p class="encadre-titre">Enregistrement impossible</p>
            <p><?= htmlspecialchars($erreur) ?></p>
        </div>
    <?php endif; ?>

    <div class="carte-admin carte-admin-ajustee">
    <h2 class="carte-admin-titre">Grille des variantes</h2>
    <p class="carte-admin-soustitre">
        Remplis le stock de chaque combinaison utile, laisse les autres cases vides, puis enregistre.
        Une case vide n'est jamais supprimée automatiquement : utilise le × pour retirer une variante existante.
        La taille unique (casquette, sac…) et les autres tailles s'excluent : remplir l'une grise l'autre.
    </p>

    <form method="post">
    <div class="tableau-scroll">
    <table class="tableau-admin grille-variantes">
        <tr>
            <th class="grille-variantes-entete-couleur">Couleur</th>
            <?php foreach (Variante::TAILLES as $taille): ?>
                <th><?= $taille ?></th>
            <?php endforeach; ?>
        </tr>
        <?php foreach (Variante::COULEURS as $couleur):
            $photo = $photosParCouleur[$produitId . '-' . $couleur] ?? null;
        ?>
            <tr>
                <th class="grille-variantes-entete-couleur">
                    <span class="grille-variantes-couleur">
                        <?php if ($photo): ?>
                            <span class="couleur-swatch">
                                <img src="../<?= htmlspecialchars($photo['chemin']) ?>" alt="">
                            </span>
                        <?php else: ?>
                            <span class="couleur-swatch couleur-swatch-uni <?= $couleur === 'AUTRE' ? 'couleur-swatch-autre' : '' ?>" style="background-color: <?= couleur_hex($couleur) ?>;"></span>
                        <?php endif; ?>
                        <?= htmlspecialchars(couleur_libelle($couleur)) ?>
                        <input type="file" id="photo-input-<?= $couleur ?>" form="photo-ajouter-<?= $couleur ?>"
                               name="photo" accept="image/jpeg,image/png,image/webp,image/gif"
                               onchange="this.form.submit()" class="visuellement-cache">
                        <label class="grille-variantes-photo-label" for="photo-input-<?= $couleur ?>"
                               title="<?= $photo ? 'Changer la photo' : 'Ajouter une photo' ?>">
                            <?= icone_admin('photo') ?>
                        </label>
                        <?php if ($photo): ?>
                            <button type="submit" form="photo-supprimer-<?= (int) $photo['id'] ?>"
                                    class="cellule-stock-supprimer" title="Supprimer la photo">×</button>
                        <?php endif; ?>
                    </span>
                </th>
                <?php foreach (Variante::TAILLES as $taille):
                    $existante = $variantesIndex[$taille][$couleur] ?? null;
                    $rupture = $existante && (int) $existante->stock === 0;
                ?>
                    <td class="cellule-stock <?= $rupture ? 'cellule-stock-rupture' : '' ?>">
                        <span class="cellule-stock-inner">
                            <!-- La classe indique au script plus bas si la case est
                                 dans la colonne "UNIQUE" ou dans une taille classique. -->
                            <input type="number" min="0" name="stock[<?= $taille ?>][<?= $couleur ?>]"
                                   class="<?= $taille === 'UNIQUE' ? 'stock-taille-unique' : 'stock-taille-classique' ?>"
                                   value="<?= htmlspecialchars($valeursGrille[$taille][$couleur]) ?>">
                            <?php if ($existante): ?>
                                <button type="submit" form="supprimer-<?= (int) $existante->id ?>"
                                        class="cellule-stock-supprimer" title="Supprimer cette variante">×</button>
                            <?php endif; ?>
                        </span>
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    </table>
    </div>
    <div class="grille-variantes-actions">
        <button type="submit" class="bouton bouton-grille">Enregistrer la grille</button>
        <div class="grille-variantes-actions-secondaires">
            <a class="bouton-secondaire" href="produit-photos.php?id=<?= (int) $produitId ?>">Gérer les photos</a>
            <a class="bouton-secondaire" href="produit-formulaire.php?id=<?= (int) $produitId ?>">Retour au produit</a>
        </div>
    </div>
    </form>
    </div>

    <?php foreach ($variantes as $variante): ?>
        <form id="supprimer-<?= (int) $variante->id ?>" method="post" action="actions/variante-supprimer.php"
              onsubmit="return confirm('Supprimer la variante <?= htmlspecialchars($variante->taille) ?> / <?= htmlspecialchars(couleur_libelle($variante->couleur)) ?> ?');"
              hidden>
            <input type="hidden" name="id" value="<?= (int) $variante->id ?>">
            <input type="hidden" name="produit_id" value="<?= (int) $produitId ?>">
        </form>
    <?php endforeach; ?>

    <?php foreach (Variante::COULEURS as $couleur): ?>
        <form id="photo-ajouter-<?= $couleur ?>" method="post" action="actions/photo-ajouter.php" enctype="multipart/form-data" hidden>
            <input type="hidden" name="produit_id" value="<?= (int) $produitId ?>">
            <input type="hidden" name="couleur" value="<?= $couleur ?>">
            <input type="hidden" name="retour" value="produit-variantes.php">
        </form>
    <?php endforeach; ?>

    <?php foreach ($photosParCouleur as $photo): ?>
        <form id="photo-supprimer-<?= (int) $photo['id'] ?>" method="post" action="actions/photo-supprimer.php"
              onsubmit="return confirm('Supprimer cette photo ?');" hidden>
            <input type="hidden" name="id" value="<?= (int) $photo['id'] ?>">
            <input type="hidden" name="produit_id" value="<?= (int) $produitId ?>">
            <input type="hidden" name="retour" value="produit-variantes.php">
        </form>
    <?php endforeach; ?>

    </main>
</div>

<script>
// Taille unique et tailles classiques s'excluent.
//  - une case "UNIQUE" est remplie  -> les cases vides des autres tailles sont grisees ;
//  - une autre taille est remplie   -> les cases vides de "UNIQUE" sont grisees.
// Une case deja remplie n'est jamais grisee : sa valeur resterait sinon
// invisible pour l'administrateur (et un champ grise n'est pas envoye).
var casesUniques = document.querySelectorAll('.stock-taille-unique');
var casesClassiques = document.querySelectorAll('.stock-taille-classique');

// Renvoie true si au moins une case de la liste contient une valeur.
function auMoinsUneRemplie(cases) {
    for (var i = 0; i < cases.length; i++) {
        if (cases[i].value !== '') {
            return true;
        }
    }
    return false;
}

// Grise (disabled) les cases vides de la liste si "griser" vaut true.
function griserCasesVides(cases, griser) {
    for (var i = 0; i < cases.length; i++) {
        cases[i].disabled = griser && cases[i].value === '';
    }
}

function mettreAJourTailles() {
    var uniqueRemplie = auMoinsUneRemplie(casesUniques);
    var classiqueRemplie = auMoinsUneRemplie(casesClassiques);

    griserCasesVides(casesClassiques, uniqueRemplie);
    griserCasesVides(casesUniques, classiqueRemplie);
}

// 'input' : a chaque touche tapee dans n'importe quelle case de stock.
var toutesLesCases = document.querySelectorAll('.stock-taille-unique, .stock-taille-classique');
for (var i = 0; i < toutesLesCases.length; i++) {
    toutesLesCases[i].addEventListener('input', mettreAJourTailles);
}

// Au chargement de la page : applique la regle aux variantes existantes.
mettreAJourTailles();
</script>
</body>
</html>
