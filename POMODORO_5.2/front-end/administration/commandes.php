<?php
// =====================================================================
// POMODORO STORE - Liste des commandes (tache 22, segment 2)
//
// L'administrateur voit toutes les commandes passees par les clients,
// de la plus recente a la plus ancienne. Des onglets permettent de ne
// garder qu'un statut : commandes.php?statut=EXPEDIEE
//
// Une barre de recherche (?q=) cherche dans le numero de commande, le
// nom et l'email du client ; ?tri=ancien inverse l'ordre des dates.
// Les trois se combinent : chaque lien de la page garde les deux autres.
// =====================================================================

require __DIR__ . '/../../back-end/securite/garde-admin.php';
require __DIR__ . '/../../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../../back-end/classes/Commande.php';

// Statut choisi dans l'URL. Un statut inconnu est ignore par
// listePourAdmin() : on affiche alors toutes les commandes.
$statutChoisi = $_GET['statut'] ?? null;
if (!array_key_exists((string) $statutChoisi, Commande::STATUTS)) {
    $statutChoisi = null;
}

// Recherche : 100 caracteres maximum (mb_substr coupe le reste).
$recherche = mb_substr(trim($_GET['q'] ?? ''), 0, 100);

// Tri : liste blanche, les plus recentes d'abord par defaut.
$tri = array_key_exists($_GET['tri'] ?? '', Commande::TRIS_ADMIN) ? $_GET['tri'] : 'recent';

$commandes = Commande::listePourAdmin($pdo, $statutChoisi, $recherche, $tri);
$nombres = Commande::nombreParStatut($pdo, $recherche);

// Adresse de la page avec les filtres actuels, en remplacant ceux de
// $changements. Les valeurs vides ou par defaut sont retirees pour
// garder des adresses courtes.
// http_build_query : fabrique "statut=EXPEDIEE&q=dupont" et encode les
// caracteres speciaux (espaces, accents...).
function adresseCommandes($changements)
{
    global $statutChoisi, $recherche, $tri;

    $parametres = array_merge(['statut' => $statutChoisi, 'q' => $recherche, 'tri' => $tri], $changements);
    if ($parametres['tri'] === 'recent') {
        $parametres['tri'] = null;
    }
    // array_filter : retire les valeurs vides (null, '').
    $parametres = array_filter($parametres, function ($valeur) {
        return $valeur !== null && $valeur !== '';
    });

    return 'commandes.php' . ($parametres ? '?' . http_build_query($parametres) : '');
}

// array_sum : additionne toutes les valeurs du tableau (total des commandes).
$nombreTotal = array_sum($nombres);

$ongletActif = 'commandes';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Commandes - Admin Pomodoro Store</title>
    <link rel="stylesheet" href="../styles/pomodoro.css?v=68">
</head>
<body>
<div class="admin-shell">
    <?php require __DIR__ . '/../morceaux-de-page/menu-admin.php'; ?>

    <main class="admin-main">
        <div class="admin-topbar">
            <div>
                <h1 class="titre">Commandes</h1>
                <p class="sous-titre">Les commandes passées par les clients.</p>
            </div>
        </div>

        <!-- Recherche et tri. Formulaire GET : les criteres restent dans
             l'adresse, la page peut etre rechargee ou mise en favori. -->
        <form class="commandes-outils" method="get" action="commandes.php" role="search">
            <?php if ($statutChoisi !== null): ?>
                <!-- Le statut choisi est garde quand on lance une recherche. -->
                <input type="hidden" name="statut" value="<?= $statutChoisi ?>">
            <?php endif; ?>
            <label for="recherche-commandes" class="visuellement-cache">Rechercher une commande</label>
            <input type="search" id="recherche-commandes" name="q" maxlength="100"
                   class="commandes-recherche" value="<?= htmlspecialchars($recherche) ?>"
                   placeholder="N° de commande, nom ou email du client">
            <label for="tri-commandes" class="visuellement-cache">Trier par date</label>
            <!-- onchange : le tri s'applique des qu'on le change. -->
            <select id="tri-commandes" name="tri" class="commandes-tri" onchange="this.form.submit()">
                <?php foreach (Commande::TRIS_ADMIN as $cle => $libelle): ?>
                    <option value="<?= $cle ?>" <?= $cle === $tri ? 'selected' : '' ?>><?= $libelle ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="bouton-secondaire">Rechercher</button>
            <?php if ($recherche !== ''): ?>
                <a class="lien-action" href="<?= htmlspecialchars(adresseCommandes(['q' => null])) ?>">Effacer la recherche</a>
            <?php endif; ?>
        </form>

        <!-- Onglets de filtre : "Toutes", puis un onglet par statut,
             avec le nombre de commandes entre parentheses (en tenant
             compte de la recherche en cours). -->
        <nav class="filtres-commandes">
            <a class="filtre-categorie <?= $statutChoisi === null ? 'filtre-categorie-actif' : '' ?>"
               href="<?= htmlspecialchars(adresseCommandes(['statut' => null])) ?>">Toutes (<?= $nombreTotal ?>)</a>
            <?php foreach (Commande::STATUTS as $code => $libelle): ?>
                <a class="filtre-categorie <?= $statutChoisi === $code ? 'filtre-categorie-actif' : '' ?>"
                   href="<?= htmlspecialchars(adresseCommandes(['statut' => $code])) ?>"><?= $libelle ?> (<?= $nombres[$code] ?>)</a>
            <?php endforeach; ?>
        </nav>

        <div class="carte-admin">
            <div class="tableau-scroll">
                <table class="tableau-admin tableau-commandes">
                    <thead>
                        <tr>
                            <th>Numéro</th>
                            <!-- Cliquer sur l'en-tete inverse le tri par date.
                                 aria-sort : annonce le tri actuel aux lecteurs d'ecran. -->
                            <th aria-sort="<?= $tri === 'ancien' ? 'ascending' : 'descending' ?>">
                                <a class="tri-colonne" href="<?= htmlspecialchars(adresseCommandes(['tri' => $tri === 'ancien' ? 'recent' : 'ancien'])) ?>"
                                   title="Inverser l'ordre des dates">
                                    Date <span aria-hidden="true"><?= $tri === 'ancien' ? '▲' : '▼' ?></span>
                                </a>
                            </th>
                            <th>Client</th>
                            <th>Articles</th>
                            <th>Total</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($commandes as $commande): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($commande['COM_NUMERO']) ?></strong></td>
                                <!-- date() + strtotime() : format suisse, ex. 24.09.2026 14:05 -->
                                <td><?= date('d.m.Y H:i', strtotime($commande['COM_DATE'])) ?></td>
                                <td><?= htmlspecialchars($commande['CLI_PRENOM'] . ' ' . $commande['CLI_NOM']) ?></td>
                                <td><?= (int) $commande['NB_ARTICLES'] ?></td>
                                <td><?= number_format((float) $commande['COM_MONTANT_TOTAL'], 2) ?> CHF</td>
                                <td>
                                    <!-- Une couleur de pastille par statut (voir le CSS) -->
                                    <span class="badge badge-statut-<?= strtolower($commande['COM_STATUT']) ?>">
                                        <?= Commande::STATUTS[$commande['COM_STATUT']] ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <a class="lien-action" href="commande-detail.php?id=<?= (int) $commande['COM_ID'] ?>">Détail</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (count($commandes) === 0): ?>
                            <tr><td colspan="7" class="tableau-vide">
                                <?php if ($recherche !== ''): ?>
                                    Aucune commande ne correspond à « <?= htmlspecialchars($recherche) ?> ».
                                <?php else: ?>
                                    Aucune commande pour l'instant.
                                <?php endif; ?>
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
</body>
</html>
