<?php
// =====================================================================
// POMODORO STORE - Page du panier
//
// Affiche le panier du client connecte : une ligne par variante, avec
// la quantite modifiable, un bouton pour la retirer, et le total.
// Les modifications sont traitees par actions/panier-modifier.php et
// actions/panier-supprimer.php (segment 3).
// =====================================================================

// Reserve au client connecte : sinon, retour a la page de connexion.
require __DIR__ . '/../back-end/securite/garde-client.php';
require __DIR__ . '/../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../back-end/classes/Commande.php'; // charge aussi Panier.php (TVA)
require __DIR__ . '/../back-end/fonctions/couleurs.php';

$lignes = Panier::lignesPourClient($pdo, $_SESSION['client_id']);

// Calcul du total : pour chaque ligne, prix unitaire x quantite.
$total = 0;
foreach ($lignes as $ligne) {
    $total += $ligne['PRIX_UNITAIRE'] * $ligne['LPA_QUANTITE'];
}

// Message renvoye par les actions du segment 3 dans l'URL.
$message = $_GET['panier'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mon panier - Pomodoro Store</title>
    <link rel="stylesheet" href="styles/pomodoro.css?v=59">
</head>
<body>
<?php require __DIR__ . '/morceaux-de-page/entete.php'; ?>
<div class="page-panier">
    <h1 class="titre">Mon panier</h1>

    <?php if ($message === 'modifie'): ?>
        <p class="encadre encadre-succes">Quantité mise à jour.</p>
    <?php elseif ($message === 'supprime'): ?>
        <p class="encadre encadre-succes">Article retiré du panier.</p>
    <?php elseif ($message === 'stock'): ?>
        <p class="encadre encadre-echec">Stock insuffisant pour cette quantité.</p>
    <?php elseif ($message === 'commande'): ?>
        <!-- Renvoye par actions/commande-passer.php si la commande a echoue -->
        <p class="encadre encadre-echec">La commande n'a pas pu être enregistrée : un article n'est plus disponible en quantité suffisante. Ajustez votre panier puis réessayez.</p>
    <?php elseif ($message === 'paiement-annule'): ?>
        <!-- Renvoye par actions/paiement-annule.php (retour depuis Stripe) -->
        <p class="encadre encadre-echec">Paiement annulé : votre commande n'a pas été passée. Vos articles sont toujours dans votre panier.</p>
    <?php elseif ($message === 'paiement-echec'): ?>
        <!-- Renvoye par actions/paiement-retour.php si Stripe ne confirme pas le paiement -->
        <p class="encadre encadre-echec">Le paiement n'a pas pu être confirmé. Si votre carte a été débitée, contactez-nous avec votre numéro de commande.</p>
    <?php endif; ?>

    <?php if (count($lignes) === 0): ?>
        <!-- Panier vide : on propose simplement de retourner a la boutique. -->
        <p class="sous-titre">Votre panier est vide.</p>
        <a class="bouton" href="boutique.php">Découvrir la boutique</a>
    <?php else: ?>
        <div class="tableau-scroll">
            <table class="tableau-admin tableau-panier">
                <thead>
                    <tr>
                        <th>Article</th>
                        <th>Prix</th>
                        <th>Quantité</th>
                        <th>Sous-total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lignes as $ligne): ?>
                        <tr>
                            <!-- Photo, nom (lien vers la fiche), couleur et taille -->
                            <td>
                                <div class="panier-article">
                                    <?php if ($ligne['IMG_CHEMIN']): ?>
                                        <img class="panier-photo" src="<?= htmlspecialchars($ligne['IMG_CHEMIN']) ?>" alt="">
                                    <?php else: ?>
                                        <span class="panier-photo"></span>
                                    <?php endif; ?>
                                    <div>
                                        <a href="fiche-produit.php?id=<?= (int) $ligne['PRO_ID'] ?>"><?= htmlspecialchars($ligne['PRO_NOM']) ?></a>
                                        <p class="panier-detail"><?= htmlspecialchars(couleur_libelle($ligne['VAR_COULEUR'])) ?> · Taille <?= htmlspecialchars($ligne['VAR_TAILLE']) ?></p>
                                    </div>
                                </div>
                            </td>

                            <!-- number_format(x, 2) : affiche toujours 2 decimales (40 -> 40.00) -->
                            <td><?= number_format((float) $ligne['PRIX_UNITAIRE'], 2) ?> CHF</td>

                            <!-- Quantite modifiable : max = stock disponible -->
                            <td>
                                <form class="panier-quantite" method="post" action="actions/panier-modifier.php">
                                    <input type="hidden" name="variante_id" value="<?= (int) $ligne['LPA_VAR_ID'] ?>">
                                    <input type="number" name="quantite" value="<?= (int) $ligne['LPA_QUANTITE'] ?>"
                                           min="1" max="<?= (int) $ligne['VAR_STOCK'] ?>" required aria-label="Quantité">
                                    <button type="submit" class="bouton-secondaire">Modifier</button>
                                </form>
                            </td>

                            <td><?= number_format($ligne['PRIX_UNITAIRE'] * $ligne['LPA_QUANTITE'], 2) ?> CHF</td>

                            <td class="actions">
                                <form method="post" action="actions/panier-supprimer.php">
                                    <input type="hidden" name="variante_id" value="<?= (int) $ligne['LPA_VAR_ID'] ?>">
                                    <button type="submit" class="bouton-secondaire">Retirer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="panier-total">Total : <strong><?= number_format($total, 2) ?> CHF</strong></p>
        <p class="ligne-tva">
            dont TVA <?= Commande::libelleTauxTva() ?> : <?= number_format(Commande::tvaComprise($total), 2) ?> CHF
            · hors frais de livraison
        </p>

        <!-- Etape suivante : la page de commande (adresse + livraison) -->
        <div class="panier-commander">
            <a class="bouton commande-confirmer" href="commander.php">Commander</a>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/morceaux-de-page/pied.php'; ?>
</body>
</html>
