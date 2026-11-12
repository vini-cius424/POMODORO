<?php global $produit, $imagesGaleries;
/**
 * Une carte produit. Attend en scope (fournies par grille-produits.php) :
 * $produit (Produit), $detail (['stockTotal'=>int,'couleurs'=>[...]]),
 * $note (['moyenne'=>float,'nombre'=>int]|null), $imagesGaleries.
 * $imagesGaleries[produitId-COULEUR] = liste de ['chemin','ordre','principale'],
 * la principale en tete (voir ImageProduit::galerieParCouleur).
 */
$couleursProduit = array_keys($detail['couleurs']);
// Dans l'ordre ou les photos ont ete ajoutees (IMG_ORDRE) plutot que
// l'ordre fixe de la constante COULEURS : une couleur sans photo propre
// passe apres celles qui en ont une.
usort($couleursProduit, function ($a, $b) use ($produit, $imagesGaleries) {
    $ordreA = $imagesGaleries[$produit->id . '-' . $a][0]['ordre'] ?? PHP_INT_MAX;
    $ordreB = $imagesGaleries[$produit->id . '-' . $b][0]['ordre'] ?? PHP_INT_MAX;
    return $ordreA <=> $ordreB;
});

// La couleur choisie par defaut reste celle de la photo principale (donc
// la premiere photo ajoutee, sauf si l'admin en a designe une autre via
// "Definir comme principale") : l'ordre des autres pastilles ne bouge pas,
// seule celle-ci passe en tete pour piloter la photo affichee au premier
// coup d'oeil.
foreach ($couleursProduit as $c) {
    if ($imagesGaleries[$produit->id . '-' . $c][0]['principale'] ?? false) {
        $couleursProduit = array_merge([$c], array_diff($couleursProduit, [$c]));
        break;
    }
}

$couleurDefaut = $couleursProduit[0] ?? null;
$enRupture = !$couleursProduit;

// Filtre couleur actif dans la boutique : la carte s'ouvre directement sur
// cette couleur (photo, pastille, tailles), et son lien emmene la fiche
// produit sur la meme couleur. Le filtre ne garde que les produits qui
// l'ont en stock, elle fait donc partie de $couleursProduit.
$couleurFiltre = $filtres['couleur'] ?? '';
if ($couleurFiltre !== '' && in_array($couleurFiltre, $couleursProduit, true)) {
    $couleurDefaut = $couleurFiltre;
}
$lienFiche = 'fiche-produit.php?id=' . (int) $produit->id
    . ($couleurDefaut === $couleurFiltre && $couleurFiltre !== '' ? '&couleur=' . urlencode($couleurFiltre) : '');

// Pour chaque couleur : ses photos, et ses tailles en stock
// (taille => identifiant de la variante), pour l'ajout rapide au panier.
$donneesCarte = ['images' => [], 'tailles' => []];
foreach ($couleursProduit as $c) {
    $galerie = $imagesGaleries[$produit->id . '-' . $c] ?? null;
    $donneesCarte['images'][$c] = $galerie ? array_column($galerie, 'chemin') : [$produit->imagePrincipale];
    $donneesCarte['tailles'][$c] = $detail['couleurs'][$c];
}
$imagesDefaut = $donneesCarte['images'][$couleurDefaut] ?? [$produit->imagePrincipale];
$taillesDefaut = $donneesCarte['tailles'][$couleurDefaut] ?? [];
?>
<div class="carte-produit">
    <a class="carte-produit-lien-image" href="<?= htmlspecialchars($lienFiche) ?>">
        <div class="image">
            <?php if ($produit->imagePrincipale): ?>
                <img class="carte-produit-image" src="<?= htmlspecialchars($imagesDefaut[0]) ?>" alt="<?= htmlspecialchars($produit->nom) ?>">
            <?php endif; ?>
            <?php if ($enRupture): ?>
                <span class="badge-rupture">Rupture de stock</span>
            <?php endif; ?>
            <span class="carte-produit-badge-categorie"><?= htmlspecialchars($produit->categorieNom) ?></span>
            <?php if (count($imagesDefaut) > 1): ?>
                <button type="button" class="carte-produit-fleche carte-produit-fleche-gauche" data-sens="-1" aria-label="Photo précédente">‹</button>
                <button type="button" class="carte-produit-fleche carte-produit-fleche-droite" data-sens="1" aria-label="Photo suivante">›</button>
                <div class="carte-produit-puces">
                    <?php foreach ($imagesDefaut as $i => $img): ?>
                        <span class="carte-produit-puce <?= $i === 0 ? 'carte-produit-puce-actif' : '' ?>"></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </a>

    <?php if (count($couleursProduit) > 1): ?>
        <div class="carte-produit-couleurs">
            <?php foreach ($couleursProduit as $c): ?>
                <?php if (isset($imagesGaleries[$produit->id . '-' . $c])): ?>
                    <button type="button" class="couleur-swatch couleur-swatch-cliquable <?= $c === $couleurDefaut ? 'couleur-swatch-selectionne' : '' ?>"
                            data-couleur="<?= $c ?>" title="<?= htmlspecialchars(couleur_libelle($c)) ?>">
                        <img src="<?= htmlspecialchars($imagesGaleries[$produit->id . '-' . $c][0]['chemin']) ?>" alt="">
                    </button>
                <?php else: ?>
                    <button type="button" class="couleur-swatch couleur-swatch-uni couleur-swatch-cliquable <?= $c === $couleurDefaut ? 'couleur-swatch-selectionne' : '' ?> <?= $c === 'AUTRE' ? 'couleur-swatch-autre' : '' ?>"
                            style="background-color: <?= couleur_hex($c) ?>;" data-couleur="<?= $c ?>" title="<?= htmlspecialchars(couleur_libelle($c)) ?>"></button>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <a class="carte-produit-lien-titre" href="<?= htmlspecialchars($lienFiche) ?>">
        <p class="nom"><?= htmlspecialchars($produit->nom) ?></p>
    </a>

    <p class="carte-produit-notes">
        <?php if ($note): ?>
            <span class="fiche-produit-etoiles" aria-hidden="true"><?= str_repeat('★', (int) round($note['moyenne'])) . str_repeat('☆', 5 - (int) round($note['moyenne'])) ?></span>
            <?= number_format($note['moyenne'], 1) ?> (<?= $note['nombre'] ?>)
        <?php else: ?>
            Pas encore d'avis
        <?php endif; ?>
    </p>

    <p class="prix">
        <?php if ($produit->prixPromo !== null): ?>
            <span class="carte-produit-prix-promo"><?= number_format((float) $produit->prixPromo, 2) ?> CHF</span>
            <span class="carte-produit-prix-barre"><?= number_format((float) $produit->prix, 2) ?> CHF</span>
        <?php else: ?>
            <?= number_format((float) $produit->prix, 2) ?> CHF
        <?php endif; ?>
    </p>

    <?php // Le stock n'est pas affiche, sauf s'il en reste tres peu (marketing).
          // La rupture, elle, est deja signalee par le badge sur la photo. ?>
    <?php if (!$enRupture && $detail['stockTotal'] <= Variante::SEUIL_STOCK_FAIBLE): ?>
        <p class="alerte-stock">
            Plus que <?= (int) $detail['stockTotal'] ?> article<?= $detail['stockTotal'] > 1 ? 's' : '' ?> disponible<?= $detail['stockTotal'] > 1 ? 's' : '' ?> !
        </p>
    <?php endif; ?>

    <?php if (!$enRupture): ?>
        <!-- Ajout rapide au panier : meme action que la fiche produit.
             Les tailles sont une liste deroulante : la valeur de chaque
             option est l'identifiant de la variante (couleur + taille). Les
             tailles de la couleur par defaut sont ecrites ici ;
             javascript/catalogue.js les redessine quand on choisit une autre
             couleur, et envoie le formulaire en arriere-plan (le client reste
             sur la boutique).
             "required" + option vide : le navigateur refuse l'envoi tant
             qu'aucune taille n'est choisie. Une taille unique est
             preselectionnee : il n'y a rien a choisir. -->
        <form class="carte-produit-ajout" method="post" action="actions/panier-ajouter.php">
            <select class="carte-produit-select-taille" name="variante_id" required aria-label="Taille">
                <?php if (count($taillesDefaut) > 1): ?>
                    <option value="">Choisir une taille</option>
                <?php endif; ?>
                <?php foreach ($taillesDefaut as $taille => $varianteId): ?>
                    <option value="<?= (int) $varianteId ?>"><?= htmlspecialchars($taille === 'UNIQUE' ? 'Taille unique' : $taille) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="bouton carte-produit-ajouter">Ajouter au panier</button>
            <p class="carte-produit-message" hidden></p>
        </form>

        <script type="application/json" class="carte-produit-donnees" data-couleur-defaut="<?= htmlspecialchars($couleurDefaut ?? '') ?>"><?= json_encode($donneesCarte) ?></script>
    <?php endif; ?>
</div>
