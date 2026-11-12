<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pomodoro Store</title>
    <link rel="stylesheet" href="styles/pomodoro.css?v=54">
    <link rel="stylesheet" href="styles/accueil.css?v=1">
</head>
<body class="corps-accueil">

<nav class="accueil-nav" id="accueil-nav">
    <a class="logo accueil-nav-logo" href="index.php">Pomodoro<span class="logo-point">.</span></a>
    <div class="accueil-nav-liens">
        <a href="boutique.php">Boutique</a>
        <?php if (!empty($_SESSION['admin_id'])): ?>
            <a href="administration/tableau-de-bord.php">Tableau de bord</a>
        <?php elseif (!empty($_SESSION['client_id'])): ?>
            <a href="mon-compte.php">Mon compte</a>
        <?php else: ?>
            <a href="connexion.php">Connexion</a>
        <?php endif; ?>
    </div>
</nav>

<section class="accueil-hero">
    <video class="accueil-hero-video" autoplay muted loop playsinline
           poster="images/accueil/t_shirt_noir_coteAcote.jpg">
        <source src="videos/hero.mp4" type="video/mp4">
    </video>
    <div class="accueil-hero-voile"></div>
    <div class="accueil-hero-contenu">
        <h1 class="accueil-hero-titre">Pomodoro<span class="logo-point">.</span></h1>

    </div>
    <a class="accueil-scroll-cue" href="#manifeste">
        <span class="accueil-scroll-cue-trait"></span>
        Découvrir
    </a>
</section>

<section class="accueil-texte accueil-reveal" id="manifeste">
    <p class="accueil-eyebrow accueil-reveal-enfant">Nées dans les serres</p>
    <h2 class="accueil-texte-titre accueil-reveal-enfant">Pensées pour la ville, cultivées avec soin.</h2>
    <p class="accueil-texte-corps accueil-reveal-enfant">
        Chaque pièce Pomodoro porte le nom qui nous a inspirés : la tomate,
        cultivée sous serre, généreuse et sans artifice. Une mode simple,
        portée par des gens vrais.
    </p>
</section>

<section class="accueil-visuel accueil-reveal" data-sens="inverse">
    <div class="accueil-visuel-image-cadre">
        <img class="accueil-visuel-image" src="images/accueil/t_shirt_noir_coteAcote.jpg" alt="Duo portant le t-shirt Pomodoro noir">
    </div>
    <div class="accueil-visuel-texte accueil-reveal-enfant">
        <p class="accueil-eyebrow">Nouvelle collection</p>
        <h2>Le noir, réinventé</h2>
        <p>Coupe ample, imprimé signature, coton épais. La collection qui a lancé Pomodoro.</p>
        <a class="accueil-cta" href="boutique.php">Découvrir la collection</a>
    </div>
</section>

<section class="accueil-visuel accueil-reveal">
    <div class="accueil-visuel-image-cadre">
        <img class="accueil-visuel-image" src="images/accueil/t_shirt_noir_homme.jpg" alt="Homme portant un t-shirt Pomodoro">
    </div>
    <div class="accueil-visuel-texte accueil-reveal-enfant">
        <p class="accueil-eyebrow">Pour lui</p>
        <h2>Collection Homme</h2>
        <p>Des essentiels taillés pour durer, du t-shirt au manteau d'hiver.</p>
        <a class="accueil-cta" href="boutique.php?categorie=1">Voir la collection Homme</a>
    </div>
</section>

<section class="accueil-visuel accueil-reveal" data-sens="inverse">
    <div class="accueil-visuel-image-cadre">
        <img class="accueil-visuel-image" src="images/accueil/t_shirt_blanc_femme_aGenou.jpg" alt="Femme portant un t-shirt Pomodoro blanc">
    </div>
    <div class="accueil-visuel-texte accueil-reveal-enfant">
        <p class="accueil-eyebrow">Pour elle</p>
        <h2>Collection Femme</h2>
        <p>Des pièces fluides et affirmées, pensées pour être portées partout.</p>
        <a class="accueil-cta" href="boutique.php?categorie=2">Voir la collection Femme</a>
    </div>
</section>

<section class="accueil-visuel accueil-reveal">
    <div class="accueil-visuel-image-cadre">
        <img class="accueil-visuel-image" src="images/accueil/topBagMainImage.jpg" alt="Tote bag Pomodoro">
    </div>
    <div class="accueil-visuel-texte accueil-reveal-enfant">
        <p class="accueil-eyebrow">Accessoires</p>
        <h2>Jusqu'au bout du détail</h2>
        <p>Le tote bag Pomodoro, brodé main, pensé pour suivre chaque saison.</p>
        <a class="accueil-cta" href="boutique.php?type=SAC">Voir les accessoires</a>
    </div>
</section>

<section class="accueil-cloture accueil-reveal">
    <h2 class="accueil-reveal-enfant">Tout l'univers Pomodoro</h2>
    <a class="accueil-cloture-bouton accueil-reveal-enfant" href="boutique.php">Explorer la boutique</a>
</section>

<?php require __DIR__ . '/morceaux-de-page/pied.php'; ?>

<script src="javascript/accueil.js"></script>
</body>
</html>
