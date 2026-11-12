<?php
global $pdo;
require __DIR__ . '/../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../back-end/classes/Produit.php';
require __DIR__ . '/../back-end/classes/Categorie.php';
require __DIR__ . '/../back-end/classes/Variante.php';
require __DIR__ . '/../back-end/classes/ImageProduit.php';
require __DIR__ . '/../back-end/classes/Avis.php';
require __DIR__ . '/../back-end/fonctions/couleurs.php';
require __DIR__ . '/../back-end/fonctions/filtres-boutique.php';

session_start();

$categories = Categorie::toutes($pdo);
$filtres = lire_filtres_catalogue($categories);
$page = page_catalogue_courante();
$parPage = 24;

$total = Produit::compterActifs($pdo, $filtres);
$produits = Produit::rechercherActifs($pdo, $filtres, $page, $parPage);

$produitIds = array_map(fn ($produit) => $produit->id, $produits);
$imagesGaleries = ImageProduit::galerieParCouleur($pdo, $produitIds);
$detailVariantes = Variante::disponibilitesParProduit($pdo, $produitIds);
$notesParProduit = Avis::resumePourProduits($pdo, $produitIds);

$sousCategories = Produit::sousCategoriesUtilisees($pdo);
$bornes = Produit::bornesPrix($pdo);
$prixPlancher = floor($bornes['min']);
$prixPlafond = max($prixPlancher + 10, ceil($bornes['max']));

$filtresActifs = array_filter($filtres, fn ($valeur) => $valeur !== null && $valeur !== '' && $valeur !== 0);

// Le client arrive de confirmer-email.php : il vient d'activer son compte.
// Message de bienvenue affiche une seule fois (la cle est effacee).
$bienvenue = isset($_SESSION['code_bienvenue_envoye']);
$codeBienvenueEnvoye = !empty($_SESSION['code_bienvenue_envoye']);
unset($_SESSION['code_bienvenue_envoye']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pomodoro Store</title>
    <link rel="stylesheet" href="styles/pomodoro.css?v=61">
</head>
<body class="corps-boutique">

<?php require __DIR__ . '/morceaux-de-page/entete.php'; ?>

<div class="page-catalogue">
    <div class="voile-filtres" id="voile-filtres" hidden></div>

    <aside class="sidebar-filtres sidebar-filtres-cachee" id="sidebar-filtres">
        <div class="sidebar-filtres-entete">
            <h2 class="sidebar-filtres-titre">Filtres</h2>
            <button type="button" class="sidebar-filtres-fermer" id="fermer-filtres" aria-label="Fermer">✕</button>
        </div>

        <form id="formulaire-filtres" method="get" action="boutique.php">
            <input type="hidden" name="categorie" value="<?= $filtres['categorie'] ? (int) $filtres['categorie'] : '' ?>">

            <div class="filtre-groupe">
                <h3 class="filtre-groupe-titre">Type</h3>
                <select name="type" class="filtre-select">
                    <option value="">Tous les types</option>
                    <?php foreach (Produit::TYPES as $valeur): ?>
                        <option value="<?= $valeur ?>" <?= $filtres['type'] === $valeur ? 'selected' : '' ?>><?= Produit::libelleType($valeur) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($sousCategories): ?>
                <div class="filtre-groupe">
                    <h3 class="filtre-groupe-titre">Sous-catégorie</h3>
                    <select name="sous_categorie" class="filtre-select">
                        <option value="">Toutes</option>
                        <?php foreach ($sousCategories as $valeur): ?>
                            <option value="<?= htmlspecialchars($valeur) ?>" <?= $filtres['sous_categorie'] === $valeur ? 'selected' : '' ?>><?= htmlspecialchars($valeur) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="filtre-groupe">
                <h3 class="filtre-groupe-titre">Taille</h3>
                <div class="filtre-boutons-taille" data-champ="taille">
                    <?php foreach (Variante::TAILLES as $valeur): ?>
                        <button type="button" class="filtre-bouton-taille <?= in_array($valeur, $filtres['taille'], true) ? 'filtre-bouton-taille-actif' : '' ?>" data-valeur="<?= $valeur ?>"><?= $valeur ?></button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="taille" value="<?= htmlspecialchars(implode(',', $filtres['taille'])) ?>">
            </div>

            <div class="filtre-groupe">
                <h3 class="filtre-groupe-titre">Couleur</h3>
                <div class="filtre-couleurs" data-champ="couleur">
                    <?php foreach (Variante::COULEURS as $valeur): ?>
                        <button type="button" class="couleur-swatch couleur-swatch-uni couleur-swatch-cliquable <?= $filtres['couleur'] === $valeur ? 'couleur-swatch-selectionne' : '' ?> <?= $valeur === 'AUTRE' ? 'couleur-swatch-autre' : '' ?>"
                                style="background-color: <?= couleur_hex($valeur) ?>;" data-valeur="<?= $valeur ?>" title="<?= htmlspecialchars(couleur_libelle($valeur)) ?>"></button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="couleur" value="<?= htmlspecialchars($filtres['couleur']) ?>">
            </div>

            <div class="filtre-groupe">
                <h3 class="filtre-groupe-titre">Prix</h3>
                <div class="prix-slider" data-min="<?= $prixPlancher ?>" data-max="<?= $prixPlafond ?>">
                    <div class="prix-slider-piste"></div>
                    <div class="prix-slider-plage" id="prix-slider-plage"></div>
                    <input type="range" class="prix-range prix-range-min" id="prix-range-min" min="<?= $prixPlancher ?>" max="<?= $prixPlafond ?>" value="<?= $filtres['prix_min'] !== '' ? (float) $filtres['prix_min'] : $prixPlancher ?>">
                    <input type="range" class="prix-range prix-range-max" id="prix-range-max" min="<?= $prixPlancher ?>" max="<?= $prixPlafond ?>" value="<?= $filtres['prix_max'] !== '' ? (float) $filtres['prix_max'] : $prixPlafond ?>">
                </div>
                <div class="prix-champs">
                    <input type="number" name="prix_min" id="prix-champ-min" min="<?= $prixPlancher ?>" max="<?= $prixPlafond ?>" value="<?= $filtres['prix_min'] !== '' ? (float) $filtres['prix_min'] : $prixPlancher ?>"> —
                    <input type="number" name="prix_max" id="prix-champ-max" min="<?= $prixPlancher ?>" max="<?= $prixPlafond ?>" value="<?= $filtres['prix_max'] !== '' ? (float) $filtres['prix_max'] : $prixPlafond ?>"> CHF
                </div>
            </div>

            <div class="filtre-groupe">
                <label class="filtre-case"><input type="checkbox" name="promo" value="1" <?= $filtres['promo'] ? 'checked' : '' ?>> En promotion</label>
                <label class="filtre-case"><input type="checkbox" name="nouveautes" value="1" <?= $filtres['nouveautes'] ? 'checked' : '' ?>> Nouveautés</label>
            </div>

            <button type="button" class="bouton-secondaire filtre-reinitialiser" id="reinitialiser-filtres">Réinitialiser les filtres</button>
            <button type="button" class="bouton filtre-afficher-mobile" id="afficher-resultats-mobile">Afficher <span id="compte-mobile"><?= $total ?></span> produits</button>
        </form>
    </aside>

    <div class="catalogue-contenu">
        <?php if ($bienvenue): ?>
            <div class="encadre encadre-succes">
                <p class="encadre-titre">Bienvenue chez Pomodoro, <?= htmlspecialchars($_SESSION['client_prenom'] ?? '') ?> !</p>
                <p>
                    Votre adresse email est confirmée et vous êtes connecté.
                    <?php if ($codeBienvenueEnvoye): ?>
                        Votre code de -20 % pour votre première commande vient de vous
                        être envoyé par email.
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>

        <div class="catalogue-barre">
            <button type="button" class="bouton-filtrer-mobile" id="ouvrir-filtres">☰ Filtrer</button>
            <div class="catalogue-champs">
                <input type="search" name="q" id="champ-recherche" form="formulaire-filtres" placeholder="Rechercher un produit..." value="<?= htmlspecialchars($filtres['q']) ?>" class="catalogue-recherche">
                <select name="tri" id="champ-tri" form="formulaire-filtres" class="filtre-select catalogue-tri">
                    <option value="">Trier par : Pertinence</option>
                    <option value="nouveautes" <?= $filtres['tri'] === 'nouveautes' ? 'selected' : '' ?>>Nouveautés</option>
                    <option value="prix_asc" <?= $filtres['tri'] === 'prix_asc' ? 'selected' : '' ?>>Prix croissant</option>
                    <option value="prix_desc" <?= $filtres['tri'] === 'prix_desc' ? 'selected' : '' ?>>Prix décroissant</option>
                </select>
            </div>
        </div>

        <div class="chips-filtres-actifs" id="chips-filtres-actifs"></div>

        <?php include __DIR__ . '/morceaux-de-page/grille-produits.php'; ?>
    </div>
</div>

<script src="javascript/catalogue.js?v=5"></script>
<?php require __DIR__ . '/morceaux-de-page/pied.php'; ?>
</body>
</html>
