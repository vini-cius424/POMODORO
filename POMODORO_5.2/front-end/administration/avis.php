<?php
require __DIR__ . '/../../back-end/securite/garde-admin.php';
require __DIR__ . '/../../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../../back-end/classes/Avis.php';
require __DIR__ . '/../../back-end/fonctions/icones.php';

$avis = Avis::toutesPourAdmin($pdo);
$ongletActif = 'avis';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Avis - Admin Pomodoro Store</title>
    <link rel="stylesheet" href="../styles/pomodoro.css?v=62">
</head>
<body>
<div class="admin-shell">
    <?php require __DIR__ . '/../morceaux-de-page/menu-admin.php'; ?>

    <main class="admin-main">
        <div class="admin-topbar">
            <div>
                <h1 class="titre">Avis clients</h1>
                <p class="sous-titre">Les avis sont publiés directement. Retirez ceux qui posent problème.</p>
            </div>
        </div>

        <div class="carte-admin">
            <div class="tableau-scroll">
                <table class="tableau-admin">
                    <tr>
                        <th>Produit</th>
                        <th>Client</th>
                        <th>Note</th>
                        <th>Commentaire</th>
                        <th>Date</th>
                        <th>État</th>
                        <th></th>
                    </tr>
                    <?php foreach ($avis as $ligne): ?>
                        <tr>
                            <td>
                                <a class="lien-action" href="../fiche-produit.php?id=<?= (int) $ligne['PRO_ID'] ?>" target="_blank">
                                    <?= htmlspecialchars($ligne['PRO_NOM']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($ligne['CLI_PRENOM'] . ' ' . $ligne['CLI_NOM']) ?></td>
                            <td class="avis-admin-note">
                                <span aria-hidden="true"><?= str_repeat('★', (int) $ligne['AVI_NOTE']) . str_repeat('☆', 5 - (int) $ligne['AVI_NOTE']) ?></span>
                                <span class="visuellement-cache"><?= (int) $ligne['AVI_NOTE'] ?> sur 5</span>
                            </td>
                            <td class="avis-admin-commentaire">
                                <?= $ligne['AVI_COMMENTAIRE']
                                    ? nl2br(htmlspecialchars($ligne['AVI_COMMENTAIRE']))
                                    : '<span class="tableau-vide">Aucun commentaire</span>' ?>
                            </td>
                            <td><?= (new DateTime($ligne['AVI_DATE']))->format('d.m.Y') ?></td>
                            <td>
                                <span class="badge <?= $ligne['AVI_VALIDE'] ? 'badge-oui' : 'badge-non' ?>">
                                    <?= $ligne['AVI_VALIDE'] ? 'En ligne' : 'Retiré' ?>
                                </span>
                            </td>
                            <td class="actions">
                                <form method="post" action="actions/avis-valider.php">
                                    <input type="hidden" name="id" value="<?= (int) $ligne['AVI_ID'] ?>">
                                    <input type="hidden" name="sens" value="<?= $ligne['AVI_VALIDE'] ? 'retirer' : 'valider' ?>">
                                    <button type="submit" class="lien-action">
                                        <?= $ligne['AVI_VALIDE'] ? 'Retirer' : 'Remettre en ligne' ?>
                                    </button>
                                </form>
                                <form method="post" action="actions/avis-supprimer.php"
                                      onsubmit="return confirm('Supprimer cet avis définitivement ?');">
                                    <input type="hidden" name="id" value="<?= (int) $ligne['AVI_ID'] ?>">
                                    <button type="submit" class="lien-action lien-action-danger">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$avis): ?>
                        <tr><td colspan="7" class="tableau-vide">Aucun avis pour l'instant.</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </main>
</div>
</body>
</html>
