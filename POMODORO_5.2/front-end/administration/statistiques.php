<?php
// =====================================================================
// POMODORO STORE - Statistiques de vente (administration)
//
// Les chiffres utiles pour piloter la boutique : ce qu'elle a vendu,
// a qui, quoi, et ce qu'il faut racheter. Tous les calculs sont dans
// back-end/classes/Statistiques.php ; cette page ne fait que les
// afficher.
//
// Les onglets de periode (?periode=7j, 30j, 12m, tout) filtrent toute
// la page, sauf deux blocs qui ont leur propre echelle de temps : le
// graphique mensuel (toujours les 12 derniers mois) et les listes "a
// faire maintenant" (commandes a traiter, stocks a reapprovisionner).
//
// Les graphiques sont dessines en HTML/CSS, sans librairie : une barre
// est un <span> dont la hauteur (ou la largeur) est un pourcentage
// calcule ici, en PHP.
// =====================================================================
require __DIR__ . '/../../back-end/securite/garde-admin.php';
require __DIR__ . '/../../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../../back-end/classes/Statistiques.php';
require __DIR__ . '/../../back-end/fonctions/couleurs.php';
require __DIR__ . '/../../back-end/fonctions/icones.php';

// Periode choisie : liste blanche, 30 jours par defaut.
$periode = array_key_exists($_GET['periode'] ?? '', Statistiques::PERIODES) ? $_GET['periode'] : '30j';
$depuis = Statistiques::debutPeriode($periode);

$resume = Statistiques::resume($pdo, $depuis);
$aTraiter = Statistiques::commandesATraiter($pdo);
$parMois = Statistiques::chiffreParMois($pdo);
$meilleurs = Statistiques::meilleursProduits($pdo, $depuis);
$parCategorie = Statistiques::ventesParCategorie($pdo, $depuis);
$parTaille = Statistiques::ventesParTaille($pdo, $depuis);
$clients = Statistiques::clients($pdo, $depuis);
$stocks = Statistiques::stocksAReapprovisionner($pdo);

// Montant au format suisse, comme dans les cartes : 1'340.50 CHF.
function chf($montant)
{
    return number_format((float) $montant, 2, '.', "'") . ' CHF';
}

// Haut de l'axe du graphique mensuel : le maximum arrondi a un nombre
// "rond" (1, 2 ou 5 x une puissance de 10) pour des graduations lisibles :
// 1'340 CHF de max donne un axe a 2'000, gradue tous les 500.
function echelleRonde($maximum)
{
    if ($maximum <= 0) {
        return 100;
    }
    $puissance = 10 ** floor(log10($maximum));
    $facteur = $maximum / $puissance;
    $rond = $facteur <= 1 ? 1 : ($facteur <= 2 ? 2 : ($facteur <= 5 ? 5 : 10));
    return $rond * $puissance;
}

$maxMois = max(array_column($parMois, 'chiffre'));
$hautAxe = echelleRonde($maxMois);
$graduations = [0, $hautAxe / 4, $hautAxe / 2, $hautAxe * 3 / 4, $hautAxe];
// Une seule valeur ecrite sur le graphique : le meilleur mois.
// Les autres se lisent au survol et dans le tableau en dessous.
$indexMeilleurMois = $maxMois > 0 ? array_search($maxMois, array_column($parMois, 'chiffre')) : null;

$maxCategorie = $parCategorie ? max(array_column($parCategorie, 'ventes')) : 0;
$maxTaille = $parTaille ? max($parTaille) : 0;
$maxProduit = $meilleurs ? max(array_column($meilleurs, 'quantite')) : 0;

$ongletActif = 'statistiques';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Statistiques - Admin Pomodoro Store</title>
    <link rel="stylesheet" href="../styles/pomodoro.css?v=62">
</head>
<body>
<div class="admin-shell">
    <?php require __DIR__ . '/../morceaux-de-page/menu-admin.php'; ?>

    <main class="admin-main">
        <div class="admin-topbar">
            <div>
                <h1 class="titre">Statistiques</h1>
                <p class="sous-titre">Ventes, clients et stock. Seules les commandes payées sont comptées dans les ventes (ni les annulées, ni celles en attente de paiement).</p>
            </div>
        </div>

        <!-- Periode : un seul rang d'onglets, au-dessus de tout ce qu'il filtre. -->
        <nav class="filtres-commandes" aria-label="Période">
            <?php foreach (Statistiques::PERIODES as $cle => $infos): ?>
                <a class="filtre-categorie <?= $cle === $periode ? 'filtre-categorie-actif' : '' ?>"
                   href="statistiques.php?periode=<?= $cle ?>"><?= htmlspecialchars($infos['libelle']) ?></a>
            <?php endforeach; ?>
        </nav>

        <!-- 1. Les chiffres cles de la periode -->
        <!-- Montants arrondis au franc dans les cartes : un chiffre cle se lit
             d'un coup d'oeil ; le detail au centime est dans les blocs suivants. -->
        <div class="stats-grille stats-grille-large">
            <div class="stat-carte">
                <div>
                    <span class="stat-valeur"><?= number_format($resume['chiffreAffaires'], 0, '.', "'") ?>&nbsp;<span class="stat-unite">CHF</span></span>
                    <span class="stat-label">Chiffre d'affaires TTC</span>
                </div>
                <span class="stat-icone"><?= icone_admin('argent') ?></span>
            </div>
            <div class="stat-carte">
                <div>
                    <span class="stat-valeur"><?= $resume['commandes'] ?></span>
                    <span class="stat-label">Commandes</span>
                </div>
                <span class="stat-icone"><?= icone_admin('commande') ?></span>
            </div>
            <div class="stat-carte">
                <div>
                    <span class="stat-valeur"><?= number_format($resume['panierMoyen'], 0, '.', "'") ?>&nbsp;<span class="stat-unite">CHF</span></span>
                    <span class="stat-label">Panier moyen (port compris)</span>
                </div>
                <span class="stat-icone"><?= icone_admin('panier') ?></span>
            </div>
            <div class="stat-carte">
                <div>
                    <span class="stat-valeur"><?= $resume['articles'] ?></span>
                    <span class="stat-label">Articles vendus</span>
                </div>
                <span class="stat-icone"><?= icone_admin('produit') ?></span>
            </div>
            <a class="stat-carte <?= $aTraiter > 0 ? 'stat-carte-alerte' : '' ?>" href="commandes.php">
                <div>
                    <span class="stat-valeur"><?= $aTraiter ?></span>
                    <span class="stat-label">Commandes à traiter</span>
                </div>
                <span class="stat-icone"><?= icone_admin($aTraiter > 0 ? 'alerte' : 'ok') ?></span>
            </a>
        </div>

        <!-- Detail financier : ce que contient le chiffre d'affaires. -->
        <div class="carte-admin">
            <h2 class="carte-admin-titre">Détail financier</h2>
            <p class="carte-admin-soustitre"><?= htmlspecialchars(Statistiques::PERIODES[$periode]['libelle']) ?></p>
            <dl class="stats-detail">
                <div>
                    <dt>TVA comprise (<?= Commande::libelleTauxTva() ?>)</dt>
                    <dd><?= chf($resume['tva']) ?></dd>
                </div>
                <div>
                    <dt>Chiffre d'affaires hors TVA</dt>
                    <dd><?= chf($resume['chiffreAffaires'] - $resume['tva']) ?></dd>
                </div>
                <div>
                    <dt>Réductions accordées (<?= Commande::CODE_PROMO_BIENVENUE ?>)</dt>
                    <dd><?= chf($resume['remises']) ?></dd>
                </div>
                <!-- Pas encore payees : hors chiffre d'affaires, mais l'admin doit
                     savoir combien d'argent est encore en suspens. -->
                <div>
                    <dt><a href="commandes.php?statut=EN_ATTENTE">En attente de paiement</a> (non comptées)</dt>
                    <dd><?= chf($resume['montantEnAttente']) ?> <span class="stats-detail-note">(<?= $resume['enAttente'] ?> commande<?= $resume['enAttente'] > 1 ? 's' : '' ?>)</span></dd>
                </div>
                <div>
                    <dt>Commandes annulées</dt>
                    <dd><?= $resume['annulees'] ?> <span class="stats-detail-note">(<?= $resume['tauxAnnulation'] ?> %)</span></dd>
                </div>
            </dl>
        </div>

        <!-- 2. Evolution : chiffre d'affaires mois par mois -->
        <div class="carte-admin">
            <h2 class="carte-admin-titre">Chiffre d'affaires par mois</h2>
            <p class="carte-admin-soustitre">Les 12 derniers mois, en CHF TTC. Survolez une colonne pour le détail.</p>

            <div class="graph-mois">
                <!-- Graduations de l'axe vertical, du haut vers le bas. -->
                <div class="graph-mois-axe" aria-hidden="true">
                    <?php foreach (array_reverse($graduations) as $graduation): ?>
                        <span><?= number_format($graduation, 0, '.', "'") ?></span>
                    <?php endforeach; ?>
                </div>
                <div class="graph-mois-zone">
                    <?php foreach ($parMois as $i => $mois):
                        $hauteur = $hautAxe > 0 ? $mois['chiffre'] * 100 / $hautAxe : 0; ?>
                        <!-- tabindex : la colonne se survole aussi au clavier (Tab). -->
                        <div class="graph-mois-colonne" tabindex="0"
                             aria-label="<?= htmlspecialchars($mois['libelle']) ?> : <?= chf($mois['chiffre']) ?>, <?= $mois['commandes'] ?> commande(s)">
                            <div class="graph-mois-piste">
                                <?php if ($i === $indexMeilleurMois): ?>
                                    <span class="graph-mois-valeur" style="bottom: <?= $hauteur ?>%"><?= number_format($mois['chiffre'], 0, '.', "'") ?></span>
                                <?php endif; ?>
                                <span class="graph-mois-barre" style="height: <?= $hauteur ?>%"></span>
                                <span class="graph-infobulle" role="tooltip">
                                    <strong><?= htmlspecialchars($mois['libelle']) ?></strong><br>
                                    <?= chf($mois['chiffre']) ?><br>
                                    <?= $mois['commandes'] ?> commande<?= $mois['commandes'] > 1 ? 's' : '' ?>
                                </span>
                            </div>
                            <span class="graph-mois-libelle"><?= htmlspecialchars(explode(' ', $mois['libelle'])[0]) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Les memes chiffres en tableau : lisibles sans souris, et copiables. -->
            <details class="stats-tableau-repli">
                <summary>Voir les chiffres en tableau</summary>
                <div class="tableau-scroll">
                    <table class="tableau-admin">
                        <tr><th>Mois</th><th>Commandes</th><th>Chiffre d'affaires TTC</th></tr>
                        <?php foreach (array_reverse($parMois) as $mois): ?>
                            <tr>
                                <td><?= htmlspecialchars($mois['libelle']) ?></td>
                                <td><?= $mois['commandes'] ?></td>
                                <td><?= chf($mois['chiffre']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </details>
        </div>

        <!-- 3. Ce qui se vend -->
        <div class="stats-deux-colonnes">
            <div class="carte-admin">
                <h2 class="carte-admin-titre">Produits les plus vendus</h2>
                <p class="carte-admin-soustitre">En nombre de pièces. Ventes = prix payés, avant réduction et sans livraison.</p>
                <?php if (!$meilleurs): ?>
                    <p class="tableau-vide">Aucune vente sur cette période.</p>
                <?php else: ?>
                    <table class="tableau-admin stats-classement">
                        <tr><th>Produit</th><th>Pièces</th><th>Ventes</th></tr>
                        <?php foreach ($meilleurs as $produit): ?>
                            <tr>
                                <td><?= htmlspecialchars($produit['PRO_NOM']) ?></td>
                                <td>
                                    <span class="barre-horizontale">
                                        <span class="barre-horizontale-remplie" style="width: <?= $produit['quantite'] * 100 / $maxProduit ?>%"></span>
                                    </span>
                                    <?= (int) $produit['quantite'] ?>
                                </td>
                                <td><?= chf($produit['ventes']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>

            <div class="carte-admin">
                <h2 class="carte-admin-titre">Ventes par catégorie</h2>
                <p class="carte-admin-soustitre">Quel rayon rapporte le plus. Prix payés, avant réduction et sans livraison.</p>
                <?php if (!$parCategorie): ?>
                    <p class="tableau-vide">Aucune vente sur cette période.</p>
                <?php else: ?>
                    <?php foreach ($parCategorie as $categorie): ?>
                        <div class="stats-ligne-barre">
                            <span class="stats-ligne-barre-nom"><?= htmlspecialchars($categorie['CAT_NOM']) ?></span>
                            <span class="barre-horizontale">
                                <span class="barre-horizontale-remplie" style="width: <?= $categorie['ventes'] * 100 / $maxCategorie ?>%"></span>
                            </span>
                            <span class="stats-ligne-barre-valeur"><?= chf($categorie['ventes']) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="stats-deux-colonnes">
            <div class="carte-admin">
                <h2 class="carte-admin-titre">Pièces vendues par taille</h2>
                <p class="carte-admin-soustitre">Pour savoir quelles tailles commander en plus grande quantité.</p>
                <?php if (!$parTaille): ?>
                    <p class="tableau-vide">Aucune vente sur cette période.</p>
                <?php else: ?>
                    <?php foreach ($parTaille as $taille => $quantite): ?>
                        <div class="stats-ligne-barre">
                            <span class="stats-ligne-barre-nom"><?= $taille === 'UNIQUE' ? 'Taille unique' : htmlspecialchars($taille) ?></span>
                            <span class="barre-horizontale">
                                <span class="barre-horizontale-remplie" style="width: <?= $quantite * 100 / $maxTaille ?>%"></span>
                            </span>
                            <span class="stats-ligne-barre-valeur"><?= $quantite ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- 4. Les clients -->
            <div class="carte-admin">
                <h2 class="carte-admin-titre">Clients</h2>
                <p class="carte-admin-soustitre"><?= htmlspecialchars(Statistiques::PERIODES[$periode]['libelle']) ?></p>
                <dl class="stats-detail">
                    <div>
                        <dt>Nouveaux comptes</dt>
                        <dd><?= $clients['nouveaux'] ?></dd>
                    </div>
                    <div>
                        <dt>Clients ayant commandé</dt>
                        <dd><?= $clients['acheteurs'] ?></dd>
                    </div>
                    <div>
                        <dt>Dont clients fidèles (2 commandes ou plus)</dt>
                        <dd><?= $clients['fideles'] ?> <span class="stats-detail-note">(<?= $clients['tauxFidelite'] ?> %)</span></dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- 5. A faire : le stock a racheter, quelle que soit la periode -->
        <div class="carte-admin">
            <h2 class="carte-admin-titre">Stock à réapprovisionner</h2>
            <p class="carte-admin-soustitre">
                Produits en vente dont une variante est épuisée ou presque (<?= Variante::SEUIL_STOCK_FAIBLE ?> pièces ou moins).
            </p>
            <?php if (!$stocks): ?>
                <p class="tableau-vide">Rien à racheter pour l'instant : toutes les variantes ont plus de <?= Variante::SEUIL_STOCK_FAIBLE ?> pièces.</p>
            <?php else: ?>
                <div class="tableau-scroll">
                    <table class="tableau-admin">
                        <tr><th>Produit</th><th>Taille</th><th>Couleur</th><th>Stock</th><th></th></tr>
                        <?php foreach ($stocks as $stock): ?>
                            <tr>
                                <td><?= htmlspecialchars($stock['PRO_NOM']) ?></td>
                                <td><?= $stock['VAR_TAILLE'] === 'UNIQUE' ? 'Unique' : htmlspecialchars($stock['VAR_TAILLE']) ?></td>
                                <td><?= htmlspecialchars(couleur_libelle($stock['VAR_COULEUR'])) ?></td>
                                <td>
                                    <!-- Etat ecrit en toutes lettres : jamais la couleur seule. -->
                                    <?php if ((int) $stock['VAR_STOCK'] === 0): ?>
                                        <span class="badge badge-non">Épuisé</span>
                                    <?php else: ?>
                                        <?= (int) $stock['VAR_STOCK'] ?> restant<?= $stock['VAR_STOCK'] > 1 ? 's' : '' ?>
                                    <?php endif; ?>
                                </td>
                                <td class="actions">
                                    <a class="lien-action" href="produit-variantes.php?id=<?= (int) $stock['PRO_ID'] ?>">Modifier le stock</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
