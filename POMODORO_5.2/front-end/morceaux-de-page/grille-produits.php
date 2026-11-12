<?php
/**
 * Grille de resultats + pagination. Attend en scope : $produits, $total,
 * $page, $parPage, $filtres, $imagesGaleries, $detailVariantes,
 * $notesParProduit. Utilisee par boutique.php (rendu initial) et
 * boutique-filtrer.php (fragment renvoye en AJAX) : c'est le meme HTML
 * dans les deux cas, jamais duplique.
 */
$totalPages = max(1, (int) ceil($total / $parPage));
$page = min($page, $totalPages);
?>
<div id="resultats-catalogue" data-total="<?= $total ?>" data-page="<?= $page ?>" data-pages="<?= $totalPages ?>">
    <p class="catalogue-compte"><?= $total ?> produit<?= $total > 1 ? 's' : '' ?></p>

    <?php if ($produits): ?>
        <div class="grille-produits">
            <?php foreach ($produits as $produit):
                $detail = $detailVariantes[$produit->id] ?? ['stockTotal' => 0, 'couleurs' => []];
                $note = $notesParProduit[$produit->id] ?? null;
                include __DIR__ . '/carte-produit.php';
            ?>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="pagination">
                <?php if ($page > 1): ?>
                    <a class="pagination-lien" href="<?= htmlspecialchars(url_catalogue($filtres, ['page' => $page - 1])) ?>">← Précédent</a>
                <?php endif; ?>

                <?php
                    $premiere = max(1, $page - 2);
                    $derniere = min($totalPages, $page + 2);
                ?>
                <?php if ($premiere > 1): ?>
                    <a class="pagination-numero" href="<?= htmlspecialchars(url_catalogue($filtres, ['page' => 1])) ?>">1</a>
                    <?php if ($premiere > 2): ?><span class="pagination-points">…</span><?php endif; ?>
                <?php endif; ?>

                <?php for ($n = $premiere; $n <= $derniere; $n++): ?>
                    <a class="pagination-numero <?= $n === $page ? 'pagination-numero-actif' : '' ?>" href="<?= htmlspecialchars(url_catalogue($filtres, ['page' => $n])) ?>"><?= $n ?></a>
                <?php endfor; ?>

                <?php if ($derniere < $totalPages): ?>
                    <?php if ($derniere < $totalPages - 1): ?><span class="pagination-points">…</span><?php endif; ?>
                    <a class="pagination-numero" href="<?= htmlspecialchars(url_catalogue($filtres, ['page' => $totalPages])) ?>"><?= $totalPages ?></a>
                <?php endif; ?>

                <?php if ($page < $totalPages): ?>
                    <a class="pagination-lien" href="<?= htmlspecialchars(url_catalogue($filtres, ['page' => $page + 1])) ?>">Suivant →</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php else: ?>
        <p class="catalogue-vide">Aucun produit ne correspond à ces critères.</p>
    <?php endif; ?>
</div>
