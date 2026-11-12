<?php
// =====================================================================
// POMODORO STORE - Detail d'une commande (tache 22, segment 3)
//
// commande-detail.php?id=12 : articles commandes, client, adresse de
// livraison et totaux. Page en lecture seule : le changement de statut
// sera ajoute avec la tache 23.
// =====================================================================

require __DIR__ . '/../../back-end/securite/garde-admin.php';
require __DIR__ . '/../../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../../back-end/classes/Commande.php';
require __DIR__ . '/../../back-end/fonctions/couleurs.php';

// (int) : "12" devient 12, et un texte comme "abc" devient 0.
$commande = Commande::trouverPourAdmin($pdo, (int) ($_GET['id'] ?? 0));

// Identifiant absent ou inconnu : retour a la liste.
if ($commande === null) {
    header('Location: commandes.php');
    exit;
}

$lignes = Commande::lignes($pdo, $commande['COM_ID']);

$libellesLivraison = [
    'STANDARD'        => 'Livraison standard (3 à 5 jours)',
    'EXPRESS'         => 'Livraison express (24 h)',
    'RETRAIT_MAGASIN' => 'Retrait en magasin'
];

// Le total enregistre a deja la remise deduite (RG3) : on la rajoute
// pour retrouver le prix des articles.
$remise = (float) $commande['COM_REMISE'];
$totalArticles = $commande['COM_MONTANT_TOTAL'] - $commande['COM_FRAIS_PORT'] + $remise;

// Tache 23 : que peut-on encore faire sur cette commande ?
//  - une commande annulee ne change plus ;
//  - l'annulation n'est possible qu'avant l'expedition.
$estAnnulee = $commande['COM_STATUT'] === 'ANNULEE';
$estAnnulable = in_array($commande['COM_STATUT'], Commande::STATUTS_ANNULABLES, true);
$statutsSuivants = Commande::statutsSuivants($commande['COM_STATUT']);

// Message renvoye par actions/commande-statut.php ou commande-annuler.php.
$message = $_GET['message'] ?? '';

$ongletActif = 'commandes';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Commande <?= htmlspecialchars($commande['COM_NUMERO']) ?> - Admin Pomodoro Store</title>
    <link rel="stylesheet" href="../styles/pomodoro.css?v=62">
</head>
<body>
<div class="admin-shell">
    <?php require __DIR__ . '/../morceaux-de-page/menu-admin.php'; ?>

    <main class="admin-main">
        <a class="admin-fil" href="commandes.php">← Retour aux commandes</a>
        <div class="admin-topbar">
            <div>
                <h1 class="titre">Commande <?= htmlspecialchars($commande['COM_NUMERO']) ?></h1>
                <p class="sous-titre">
                    Passée le <?= date('d.m.Y à H:i', strtotime($commande['COM_DATE'])) ?>
                    <span class="badge badge-statut-<?= strtolower($commande['COM_STATUT']) ?>">
                        <?= Commande::STATUTS[$commande['COM_STATUT']] ?>
                    </span>
                    <!-- Horodatage : seulement si le statut a deja ete modifie -->
                    <?php if ($commande['COM_DATE_MAJ']): ?>
                        <br>Statut modifié le <?= date('d.m.Y à H:i', strtotime($commande['COM_DATE_MAJ'])) ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <?php if ($message === 'statut'): ?>
            <p class="encadre encadre-succes">Le statut de la commande a été modifié.</p>
        <?php elseif ($message === 'annulee'): ?>
            <p class="encadre encadre-succes">La commande a été annulée et ses articles ont été remis en stock.</p>
        <?php elseif ($message === 'refuse'): ?>
            <p class="encadre encadre-echec">Changement de statut refusé.</p>
        <?php elseif ($message === 'non-annulable'): ?>
            <p class="encadre encadre-echec">Cette commande ne peut plus être annulée.</p>
        <?php endif; ?>

        <!-- Gestion de la commande (tache 23) -->
        <div class="carte-admin commande-gestion">
            <h2 class="carte-admin-titre">Gestion de la commande</h2>

            <?php if ($estAnnulee): ?>
                <p class="carte-admin-soustitre">Cette commande est annulée : elle ne peut plus être modifiée.</p>
            <?php else: ?>
                <div class="commande-gestion-actions">
                    <!-- 1. Changer le statut : liste deroulante des statuts
                         SUIVANTS seulement (une commande ne revient jamais en
                         arriere), sans "Annulee" qui a son propre bouton. -->
                    <?php if ($statutsSuivants): ?>
                        <form class="commande-gestion-statut" method="post" action="actions/commande-statut.php">
                            <input type="hidden" name="id" value="<?= (int) $commande['COM_ID'] ?>">
                            <!-- Label invisible a l'ecran mais lu par les lecteurs d'ecran -->
                            <label for="statut" class="visuellement-cache">Statut</label>
                            <!-- 1re option : le statut actuel, pour reperer ou en est
                                 la commande. disabled : on ne peut pas le renvoyer ;
                                 required : il faut choisir un des statuts suivants. -->
                            <select id="statut" name="statut" required>
                                <option value="" selected disabled><?= Commande::STATUTS[$commande['COM_STATUT']] ?> (statut actuel)</option>
                                <?php foreach ($statutsSuivants as $code): ?>
                                    <option value="<?= $code ?>"><?= Commande::STATUTS[$code] ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="bouton-secondaire">Enregistrer</button>
                        </form>
                    <?php else: ?>
                        <p class="carte-admin-soustitre">Commande livrée : son statut ne peut plus changer.</p>
                    <?php endif; ?>

                    <!-- 2. Annuler : seulement avant l'expedition.
                         confirm() demande une confirmation : Annuler ne se defait pas. -->
                    <?php if ($estAnnulable): ?>
                        <form method="post" action="actions/commande-annuler.php"
                              onsubmit="return confirm('Annuler la commande <?= htmlspecialchars($commande['COM_NUMERO']) ?> ? Les articles seront remis en stock.');">
                            <input type="hidden" name="id" value="<?= (int) $commande['COM_ID'] ?>">
                            <button type="submit" class="bouton-secondaire bouton-secondaire-danger">Annuler la commande</button>
                        </form>
                    <?php else: ?>
                        <p class="carte-admin-soustitre">Commande expédiée : l'annulation n'est plus possible.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Deux cartes cote a cote : le client, et la livraison -->
        <div class="commande-detail-infos">
            <div class="carte-admin">
                <h2 class="carte-admin-titre">Client</h2>
                <p><?= htmlspecialchars($commande['CLI_PRENOM'] . ' ' . $commande['CLI_NOM']) ?></p>
                <!-- mailto: ouvre la messagerie avec l'adresse deja remplie -->
                <p><a href="mailto:<?= htmlspecialchars($commande['CLI_EMAIL']) ?>"><?= htmlspecialchars($commande['CLI_EMAIL']) ?></a></p>
                <!-- Le telephone est facultatif : on ne l'affiche que s'il existe -->
                <?php if ($commande['CLI_TELEPHONE']): ?>
                    <p><?= htmlspecialchars($commande['CLI_TELEPHONE']) ?></p>
                <?php endif; ?>
            </div>

            <div class="carte-admin">
                <h2 class="carte-admin-titre">Livraison</h2>
                <p><?= $libellesLivraison[$commande['COM_MODE_LIVRAISON']] ?></p>
                <p>
                    <?= htmlspecialchars($commande['ADR_RUE']) ?><br>
                    <?= htmlspecialchars($commande['ADR_NPA'] . ' ' . $commande['ADR_VILLE']) ?><br>
                    <?= htmlspecialchars($commande['ADR_PAYS']) ?>
                </p>
            </div>

            <!-- Paiement Stripe : le numero de session permet de retrouver
                 le paiement dans le tableau de bord Stripe. -->
            <div class="carte-admin">
                <h2 class="carte-admin-titre">Paiement</h2>
                <?php if (!$commande['COM_PAIEMENT_ID']): ?>
                    <p>Aucun paiement en ligne.</p>
                <?php elseif ($commande['COM_STATUT'] === 'EN_ATTENTE'): ?>
                    <p>Paiement Stripe commencé, pas encore confirmé.</p>
                <?php elseif ($commande['COM_STATUT'] === 'ANNULEE'): ?>
                    <p>Paiement Stripe abandonné ou annulé.</p>
                <?php else: ?>
                    <p>Payée par carte (Stripe).</p>
                <?php endif; ?>
                <?php if ($commande['COM_PAIEMENT_ID']): ?>
                    <p class="panier-detail">Session : <?= htmlspecialchars($commande['COM_PAIEMENT_ID']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="carte-admin">
            <h2 class="carte-admin-titre">Articles</h2>
            <div class="tableau-scroll">
                <table class="tableau-admin tableau-commandes">
                    <thead>
                        <tr>
                            <th>Article</th>
                            <th>Couleur</th>
                            <th>Taille</th>
                            <th>Prix unitaire</th>
                            <th>Quantité</th>
                            <th>Sous-total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lignes as $ligne): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($ligne['PRO_NOM']) ?></strong></td>
                                <td><?= htmlspecialchars(couleur_libelle($ligne['VAR_COULEUR'])) ?></td>
                                <td><?= htmlspecialchars($ligne['VAR_TAILLE']) ?></td>
                                <!-- Prix fige au moment de l'achat, pas le prix actuel du catalogue -->
                                <td><?= number_format((float) $ligne['LCO_PRIX_UNITAIRE'], 2) ?> CHF</td>
                                <td><?= (int) $ligne['LCO_QUANTITE'] ?></td>
                                <td><?= number_format((float) $ligne['LCO_SOUS_TOTAL'], 2) ?> CHF</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

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
            </div>
        </div>
    </main>
</div>
</body>
</html>
