<?php
// =====================================================================
// POMODORO STORE - Confirmation de commande (tache 10, segment 4)
//
// Page affichee juste apres actions/commande-passer.php :
//     commande-confirmation.php?numero=POM-000012
// Elle relit la commande en base et en affiche le recapitulatif.
// =====================================================================

// Reserve au client connecte : sinon, retour a la page de connexion.
require __DIR__ . '/../back-end/securite/garde-client.php';
require __DIR__ . '/../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../back-end/classes/Commande.php';
require __DIR__ . '/../back-end/fonctions/couleurs.php';

$numero = $_GET['numero'] ?? '';

// trouverPourClient() ne renvoie que les commandes DE CE client :
// changer le numero dans l'adresse ne montre pas la commande d'un autre.
$commande = Commande::trouverPourClient($pdo, $numero, $_SESSION['client_id']);

if ($commande === null) {
    header('Location: index.php');
    exit;
}

// Vient-on de passer cette commande (redirection de commande-passer.php)
// ou la consulte-t-on depuis "Mes commandes" ? Change juste le titre et
// le bandeau affiches, pas les donnees.
$vientDetreCreee = ($_GET['nouvelle'] ?? '') === '1';

// Pose par commande-passer.php : l'email de confirmation est-il parti ?
// Lu une seule fois, puis efface.
$emailEnvoye = !empty($_SESSION['email_commande_envoye']);
unset($_SESSION['email_commande_envoye']);

$lignes = Commande::lignes($pdo, $commande['COM_ID']);

// Texte affiche pour chaque mode de livraison enregistre en base.
$libellesLivraison = [
    'STANDARD'        => 'Livraison standard (3 à 5 jours)',
    'EXPRESS'         => 'Livraison express (24 h)',
    'RETRAIT_MAGASIN' => 'Retrait en magasin'
];

// Total des articles = total de la commande - frais de port + remise
// (le total enregistre a deja la remise deduite, voir RG3).
$remise = (float) $commande['COM_REMISE'];
$totalArticles = $commande['COM_MONTANT_TOTAL'] - $commande['COM_FRAIS_PORT'] + $remise;

// strtotime : transforme la date de la base ("2026-09-21 14:05:00") en
// nombre de secondes ; date() la remet ensuite au format suisse.
$dateAffichee = date('d.m.Y à H:i', strtotime($commande['COM_DATE']));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $vientDetreCreee ? 'Commande confirmée' : htmlspecialchars($commande['COM_NUMERO']) ?> - Pomodoro Store</title>
    <link rel="stylesheet" href="styles/pomodoro.css?v=60">
</head>
<body>
<?php require __DIR__ . '/morceaux-de-page/entete.php'; ?>
<div class="page-panier">
    <?php if ($vientDetreCreee): ?>
        <h1 class="titre">Merci pour votre commande !</h1>
        <p class="encadre encadre-succes">
            Votre commande <strong><?= htmlspecialchars($commande['COM_NUMERO']) ?></strong>
            du <?= $dateAffichee ?> est enregistrée.
            <?php if ($emailEnvoye): ?>
                Un email de confirmation vient de vous être envoyé.
            <?php endif; ?>
        </p>
        <!-- &paiement=ok : renvoye par actions/paiement-retour.php une fois
             le paiement confirme par Stripe. -->
        <?php if (($_GET['paiement'] ?? '') === 'ok'): ?>
            <p class="encadre encadre-succes">Paiement reçu : merci, votre commande est validée.</p>
        <?php endif; ?>
    <?php else: ?>
        <a class="admin-fil" href="mon-compte.php?section=commandes">← Retour à mes commandes</a>
        <h1 class="titre">Commande <?= htmlspecialchars($commande['COM_NUMERO']) ?></h1>
        <p class="sous-titre">
            Passée le <?= $dateAffichee ?> ·
            <span class="badge badge-statut-<?= strtolower($commande['COM_STATUT']) ?>">
                <?= Commande::STATUTS[$commande['COM_STATUT']] ?>
            </span>
            <!-- Suivi : date du dernier changement de statut par la boutique.
                 COM_DATE_MAJ vaut NULL tant que le statut n'a pas bouge. -->
            <?php if ($commande['COM_DATE_MAJ']): ?>
                <br>Mis à jour le <?= date('d.m.Y à H:i', strtotime($commande['COM_DATE_MAJ'])) ?>
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <!-- Articles commandes : le prix est celui fige au moment de l'achat -->
    <section class="compte-section">
        <h2 class="compte-section-titre">Articles</h2>
        <div class="tableau-scroll">
            <table class="tableau-admin">
                <thead>
                    <tr>
                        <th>Article</th>
                        <th>Prix</th>
                        <th>Quantité</th>
                        <th>Sous-total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lignes as $ligne): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($ligne['PRO_NOM']) ?></strong>
                                <p class="panier-detail"><?= htmlspecialchars(couleur_libelle($ligne['VAR_COULEUR'])) ?> · Taille <?= htmlspecialchars($ligne['VAR_TAILLE']) ?></p>
                            </td>
                            <td><?= number_format((float) $ligne['LCO_PRIX_UNITAIRE'], 2) ?> CHF</td>
                            <td><?= (int) $ligne['LCO_QUANTITE'] ?></td>
                            <td><?= number_format((float) $ligne['LCO_SOUS_TOTAL'], 2) ?> CHF</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Livraison : mode choisi et adresse saisie -->
    <section class="compte-section">
        <h2 class="compte-section-titre">Livraison</h2>
        <p><?= $libellesLivraison[$commande['COM_MODE_LIVRAISON']] ?></p>
        <p class="panier-detail">
            <?= htmlspecialchars($commande['ADR_RUE']) ?><br>
            <?= htmlspecialchars($commande['ADR_NPA']) ?> <?= htmlspecialchars($commande['ADR_VILLE']) ?>
        </p>
    </section>

    <!-- Paiement : en ligne par carte (Stripe), ou pas encore regle -->
    <section class="compte-section">
        <h2 class="compte-section-titre">Paiement</h2>
        <?php if ($commande['COM_PAIEMENT_ID'] && $commande['COM_STATUT'] !== 'EN_ATTENTE' && $commande['COM_STATUT'] !== 'ANNULEE'): ?>
            <p>Payée en ligne par carte.</p>
        <?php elseif ($commande['COM_STATUT'] === 'ANNULEE'): ?>
            <p>Commande annulée : aucun montant n'est dû.</p>
        <?php else: ?>
            <p>En attente de paiement.</p>
        <?php endif; ?>
    </section>

    <div class="commande-total">
        <p>Articles : <?= number_format($totalArticles, 2) ?> CHF</p>
        <?php if ($remise > 0): ?>
            <p>Réduction <?= htmlspecialchars($commande['COM_CODE_PROMO']) ?> : -<?= number_format($remise, 2) ?> CHF</p>
        <?php endif; ?>
        <p>Livraison : <?= number_format((float) $commande['COM_FRAIS_PORT'], 2) ?> CHF</p>
        <p class="panier-total">Total : <strong><?= number_format((float) $commande['COM_MONTANT_TOTAL'], 2) ?> CHF</strong></p>
        <p class="ligne-tva">
            dont TVA <?= Commande::libelleTauxTva() ?> : <?= number_format(Commande::tvaComprise((float) $commande['COM_MONTANT_TOTAL']), 2) ?> CHF
        </p>
        <a class="bouton commande-confirmer" href="boutique.php">Continuer mes achats</a>
    </div>
</div>
<?php require __DIR__ . '/morceaux-de-page/pied.php'; ?>
</body>
</html>
