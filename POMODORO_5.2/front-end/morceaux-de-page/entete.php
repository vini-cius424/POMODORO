<?php
/**
 * En-tete commun du site : pastilles categories + logo centre + icones de
 * compte (desktop), hamburger + panneau plein ecran (mobile), recherche
 * repliable et entete sticky/compacte au scroll. Attend $prefixe avant
 * l'inclusion : '' depuis front-end/, '../' depuis un sous-dossier
 * (pages-infos/, administration/).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$prefixe = $prefixe ?? '';

// L'entete n'a pas forcement $pdo, $categories, $filtres : une page comme
// mon-compte.php ne les charge pas pour elle-meme. On ouvre une connexion
// dediee, juste pour ce que l'entete affiche.
require_once __DIR__ . '/../../back-end/connexion-base-de-donnees.php';
require_once __DIR__ . '/../../back-end/classes/Categorie.php';
require_once __DIR__ . '/../../back-end/classes/Produit.php';
require_once __DIR__ . '/../../back-end/classes/Panier.php';

if (!isset($categories)) {
    $categories = Categorie::toutes($pdo);
}

$categorieActuelle = isset($filtres['categorie']) ? (int) $filtres['categorie'] : null;
$typeActuel = $filtres['type'] ?? '';

/** Lien vers le catalogue : repart des filtres actuels s'ils sont connus (on est sur boutique.php), sinon un lien simple. */
function lien_catalogue_entete(string $prefixe, array $remplacements): string
{
    if (function_exists('url_catalogue') && isset($GLOBALS['filtres'])) {
        return $prefixe . url_catalogue($GLOBALS['filtres'], $remplacements);
    }
    $parametres = array_filter($remplacements, fn ($valeur) => $valeur !== null && $valeur !== '');
    return $prefixe . 'boutique.php' . ($parametres ? '?' . http_build_query($parametres) : '');
}

if (!function_exists('icone_entete')) {
    function icone_entete(string $nom): string
    {
        $chemins = [
            'compte'    => '<path d="M12 12a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9Z"/><path d="M4 20.5c1.4-3.6 4.6-5.5 8-5.5s6.6 1.9 8 5.5"/>',
            'sortie'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
            'tableau'   => '<rect x="3" y="3" width="7" height="7" rx="1.3"/><rect x="14" y="3" width="7" height="7" rx="1.3"/><rect x="3" y="14" width="7" height="7" rx="1.3"/><rect x="14" y="14" width="7" height="7" rx="1.3"/>',
            'loupe'     => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
            'hamburger' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
            'panier'    => '<path d="M5 8h14l-1.2 12H6.2L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
        ];
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . ($chemins[$nom] ?? '') . '</svg>';
    }
}
?>
<header class="entete" id="entete">
    <div class="entete-topbar">
        <button type="button" class="entete-hamburger" id="entete-hamburger" aria-label="Ouvrir le menu" aria-expanded="false">
            <?= icone_entete('hamburger') ?>
        </button>

        <nav class="entete-pastilles" aria-label="Categories">
            <a class="filtre-categorie <?= !$categorieActuelle ? 'filtre-categorie-actif' : '' ?>" data-entete-categorie=""
               href="<?= htmlspecialchars(lien_catalogue_entete($prefixe, ['categorie' => null, 'type' => null, 'page' => null])) ?>">Tout</a>
            <?php foreach ($categories as $categorie): ?>
                <a class="filtre-categorie <?= $categorieActuelle === (int) $categorie->id ? 'filtre-categorie-actif' : '' ?>" data-entete-categorie="<?= (int) $categorie->id ?>"
                   href="<?= htmlspecialchars(lien_catalogue_entete($prefixe, ['categorie' => $categorie->id, 'page' => null])) ?>">
                    <?= htmlspecialchars($categorie->nom) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <a class="logo entete-logo" href="<?= $prefixe ?>index.php">Pomodoro<span class="logo-point">.</span></a>

        <div class="entete-icones">
            <a class="entete-lien-texte entete-masque-mobile" href="<?= $prefixe ?>boutique.php">Boutique</a>

            <button type="button" class="entete-icone" id="entete-recherche-ouvrir" aria-label="Rechercher" aria-expanded="false">
                <?= icone_entete('loupe') ?>
            </button>

            <?php if (!empty($_SESSION['admin_id'])): ?>
                <a class="entete-icone entete-masque-mobile" href="<?= $prefixe ?>administration/tableau-de-bord.php" title="Tableau de bord" aria-label="Tableau de bord"><?= icone_entete('tableau') ?></a>
                <a class="entete-icone entete-masque-mobile" href="<?= $prefixe ?>deconnexion.php" title="Déconnexion" aria-label="Déconnexion"><?= icone_entete('sortie') ?></a>
            <?php elseif (!empty($_SESSION['client_id'])): ?>
                <?php
                // Compteur du panier : nombre total d'articles du client.
                // Seul un client connecte a un panier (regle A).
                $nombreArticles = Panier::nombreArticles($pdo, $_SESSION['client_id']);
                ?>
                <a class="entete-icone" href="<?= $prefixe ?>panier.php" title="Mon panier" aria-label="Mon panier : <?= $nombreArticles ?> article(s)">
                    <?= icone_entete('panier') ?>
                    <?php if ($nombreArticles > 0): ?>
                        <span class="entete-compteur"><?= $nombreArticles ?></span>
                    <?php endif; ?>
                </a>
                <a class="entete-icone entete-masque-mobile" href="<?= $prefixe ?>mon-compte.php" title="Mon compte" aria-label="Mon compte"><?= icone_entete('compte') ?></a>
                <a class="entete-icone entete-masque-mobile" href="<?= $prefixe ?>deconnexion.php" title="Déconnexion" aria-label="Déconnexion"><?= icone_entete('sortie') ?></a>
            <?php else: ?>
                <a class="entete-lien-texte entete-masque-mobile" href="<?= $prefixe ?>inscription.php">Créer un compte</a>
                <a class="entete-icone entete-masque-mobile" href="<?= $prefixe ?>connexion.php" title="Connexion" aria-label="Connexion"><?= icone_entete('compte') ?></a>
            <?php endif; ?>
        </div>
    </div>

    <div class="entete-sousrangee">
        <nav class="entete-types" aria-label="Types de produits">
            <?php foreach (Produit::TYPES as $valeur): ?>
                <?php // Recliquer sur le type deja choisi le retire (lien sans type). ?>
                <a class="<?= $typeActuel === $valeur ? 'entete-type-actif' : '' ?>" data-entete-type="<?= $valeur ?>"
                   href="<?= htmlspecialchars(lien_catalogue_entete($prefixe, ['type' => $typeActuel === $valeur ? null : $valeur, 'page' => null])) ?>">
                    <?= Produit::libelleType($valeur) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>

    <div class="entete-recherche-panel" id="entete-recherche-panel">
        <form method="get" action="<?= $prefixe ?>boutique.php">
            <?= icone_entete('loupe') ?>
            <input type="search" name="q" placeholder="Rechercher un produit..." id="entete-recherche-champ">
            <button type="button" class="entete-recherche-fermer" id="entete-recherche-fermer" aria-label="Fermer la recherche">✕</button>
        </form>
    </div>
</header>

<div class="entete-panel-voile" id="entete-panel-voile" hidden></div>
<nav class="entete-panel-mobile" id="entete-panel-mobile" aria-label="Menu">
    <div class="entete-panel-entete">
        <a class="logo" href="<?= $prefixe ?>index.php">Pomodoro<span class="logo-point">.</span></a>
        <button type="button" class="entete-panel-fermer" id="entete-panel-fermer" aria-label="Fermer le menu">✕</button>
    </div>

    <div class="entete-panel-section">
        <a href="<?= $prefixe ?>index.php">Accueil</a>
        <a href="<?= $prefixe ?>boutique.php">Boutique</a>
        <?php foreach ($categories as $categorie): ?>
            <a data-entete-categorie="<?= (int) $categorie->id ?>" href="<?= htmlspecialchars(lien_catalogue_entete($prefixe, ['categorie' => $categorie->id, 'page' => null])) ?>">
                <?= htmlspecialchars($categorie->nom) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="entete-panel-section entete-panel-types">
        <?php foreach (Produit::TYPES as $valeur): ?>
            <a data-entete-type="<?= $valeur ?>" href="<?= htmlspecialchars(lien_catalogue_entete($prefixe, ['type' => $typeActuel === $valeur ? null : $valeur, 'page' => null])) ?>">
                <?= Produit::libelleType($valeur) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="entete-panel-section entete-panel-compte">
        <?php if (!empty($_SESSION['admin_id'])): ?>
            <a href="<?= $prefixe ?>administration/tableau-de-bord.php">Tableau de bord</a>
            <a href="<?= $prefixe ?>deconnexion.php">Déconnexion</a>
        <?php elseif (!empty($_SESSION['client_id'])): ?>
            <a href="<?= $prefixe ?>mon-compte.php">Mon compte</a>
            <a href="<?= $prefixe ?>deconnexion.php">Déconnexion</a>
        <?php else: ?>
            <a class="entete-lien-texte" href="<?= $prefixe ?>inscription.php">Créer un compte</a>
            <a href="<?= $prefixe ?>connexion.php">Connexion</a>
        <?php endif; ?>
    </div>
</nav>

<script src="<?= $prefixe ?>javascript/entete.js"></script>
