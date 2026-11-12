<?php
/**
 * Barre laterale de la section admin. A inclure apres garde-admin.php,
 * a l'interieur d'un <div class="admin-shell">. Attend $ongletActif
 * ('tableau-de-bord', 'categories', 'produits', 'commandes', 'messages' ou
 * 'statistiques') defini par la page avant l'inclusion.
 */
require_once __DIR__ . '/../../back-end/fonctions/icones.php';
require_once __DIR__ . '/../../back-end/classes/MessageContact.php';

// Pastille du lien "Messages" : messages pas encore ouverts. Toutes les
// pages admin ouvrent la connexion ($pdo) avant d'inclure ce menu.
$messagesNouveaux = isset($pdo) ? MessageContact::nombreNouveaux($pdo) : 0;

$ongletActif = $ongletActif ?? '';
$prenomAdmin = $_SESSION['admin_prenom'] ?? '';
$initialeAdmin = $prenomAdmin !== '' ? mb_strtoupper(mb_substr($prenomAdmin, 0, 1)) : 'A';
?>
<aside class="admin-sidebar">
    <div class="admin-sidebar-entete">
        <div>         
            <a class="logo" href="tableau-de-bord.php">Pomodoro<span class="logo-point">.</span></a>
            <p class="admin-sidebar-souslogo">Espace administrateur</p>
        </div>
        <button type="button" class="admin-nav-toggle" id="admin-nav-toggle" aria-expanded="false" aria-controls="admin-nav">
            <?= icone_admin('menu') ?>
            Menu
        </button>
    </div>

    <nav class="admin-nav" id="admin-nav">
        <a class="admin-nav-lien <?= $ongletActif === 'tableau-de-bord' ? 'admin-nav-lien-actif' : '' ?>" href="tableau-de-bord.php">
            <?= icone_admin('tableau-de-bord') ?> Tableau de bord
        </a>
        <a class="admin-nav-lien <?= $ongletActif === 'categories' ? 'admin-nav-lien-actif' : '' ?>" href="categories.php">
            <?= icone_admin('categorie') ?> Catégories
        </a>
        <a class="admin-nav-lien <?= $ongletActif === 'produits' ? 'admin-nav-lien-actif' : '' ?>" href="produits.php">
            <?= icone_admin('produit') ?> Produits
        </a>
        <a class="admin-nav-lien <?= $ongletActif === 'commandes' ? 'admin-nav-lien-actif' : '' ?>" href="commandes.php">
            <?= icone_admin('commande') ?> Commandes
        </a>
        <a class="admin-nav-lien <?= $ongletActif === 'messages' ? 'admin-nav-lien-actif' : '' ?>" href="messages.php">
            <?= icone_admin('message') ?> Messages
            <?php if ($messagesNouveaux > 0): ?>
                <span class="admin-nav-pastille" title="Messages non lus"><?= $messagesNouveaux ?></span>
            <?php endif; ?>
        </a>
        <a class="admin-nav-lien <?= $ongletActif === 'statistiques' ? 'admin-nav-lien-actif' : '' ?>" href="statistiques.php">
            <?= icone_admin('graphique') ?> Statistiques
        </a>
        <a class="admin-nav-lien" href="../boutique.php" target="_blank">
            <?= icone_admin('boutique') ?> Voir la boutique
        </a>

        <div class="admin-nav-bas">
            <hr class="admin-nav-separateur">
            <div class="admin-compte">
                <span class="admin-avatar"><?= htmlspecialchars($initialeAdmin) ?></span>
                <div>
                    <p class="admin-compte-nom"><?= htmlspecialchars($prenomAdmin) ?></p>
                    <p class="admin-compte-role">Administrateur</p>
                </div>
            </div>
            <a class="admin-deconnexion" href="../deconnexion.php">
                <?= icone_admin('deconnexion') ?> Se déconnecter
            </a>
        </div>
    </nav>
</aside>
<script>
    (function () {
        var bouton = document.getElementById('admin-nav-toggle');
        var nav = document.getElementById('admin-nav');
        if (!bouton || !nav) return;
        bouton.addEventListener('click', function () {
            var ouvert = nav.classList.toggle('admin-nav-ouvert');
            bouton.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
        });
    })();

    // Desactive le bouton d'enregistrement pendant l'envoi, pour eviter
    // un double-clic et donner un retour visuel. Les formulaires de
    // suppression ont deja leur propre onsubmit (confirm()) : on ne les
    // touche pas ici, sinon le bouton resterait bloque si l'utilisateur
    // annule la confirmation.
    //
    // Attend DOMContentLoaded : ce script est inclus tout en haut de la
    // page (dans la barre laterale), avant que <main> n'ait ete analyse -
    // sans l'attente, querySelectorAll ne trouverait encore aucun
    // formulaire.
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.admin-main form').forEach(function (formulaire) {
            if (formulaire.hasAttribute('onsubmit')) return;
            // Un formulaire en GET (la recherche des produits) n'enregistre
            // rien : pas de bouton "Enregistrement..." pour lui.
            if (formulaire.method !== 'post') return;
            formulaire.addEventListener('submit', function () {
                // :not([form]) exclut les boutons qui, comme le × de
                // suppression d'une variante, ciblent un AUTRE formulaire
                // via l'attribut form="...".
                var bouton = formulaire.querySelector('button[type="submit"]:not([form])');
                if (!bouton || bouton.disabled) return;
                bouton.disabled = true;
                bouton.textContent = 'Enregistrement...';
            });
        });
    });
</script>
