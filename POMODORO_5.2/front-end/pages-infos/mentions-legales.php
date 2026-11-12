<?php
// =====================================================================
// POMODORO STORE - Mentions legales (impressum)
//
// En Suisse, un site de commerce electronique doit indiquer clairement
// son identite et une adresse de contact, courrier electronique compris
// (LCD, art. 3 al. 1 let. s). Les donnees viennent de
// back-end/fonctions/entreprise.php.
// =====================================================================

require __DIR__ . '/../../back-end/fonctions/entreprise.php';

$prefixe = '../'; // pour l'en-tete et le pied : on est dans un sous-dossier
$e = entreprise();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mentions légales - Pomodoro Store</title>
    <link rel="stylesheet" href="../styles/pomodoro.css?v=66">
</head>
<body>
<?php require __DIR__ . '/../morceaux-de-page/entete.php'; ?>
<main class="page-info">
    <p class="page-info-surtitre">Informations</p>
    <h1 class="titre">Mentions légales</h1>
    <p class="page-info-intro">Qui se trouve derrière Pomodoro Store, et comment nous joindre.</p>

    <section class="page-info-section">
        <h2>Exploitant du site</h2>
        <dl class="page-info-fiche">
            <dt>Raison sociale</dt>
            <dd><?= htmlspecialchars($e['raison_sociale']) ?></dd>
            <dt>Forme juridique</dt>
            <dd><?= htmlspecialchars($e['forme']) ?></dd>
            <dt>Responsable</dt>
            <dd><?= htmlspecialchars($e['responsable']) ?></dd>
            <dt>Adresse</dt>
            <dd>
                <?= htmlspecialchars($e['adresse']) ?><br>
                <?= htmlspecialchars($e['npa_ville']) ?><br>
                <?= htmlspecialchars($e['pays']) ?>
            </dd>
            <dt>Email</dt>
            <dd><?= htmlspecialchars($e['email']) ?></dd>
            <dt>Numéro IDE</dt>
            <dd><?= htmlspecialchars($e['ide']) ?></dd>
            <dt>Numéro TVA</dt>
            <dd><?= htmlspecialchars($e['tva']) ?></dd>
        </dl>
        <p>
            Vous pouvez aussi nous écrire avec le <a href="../contact.php">formulaire de contact</a>.
        </p>
    </section>

    <section class="page-info-section">
        <h2>Hébergement</h2>
        <p><?= htmlspecialchars($e['hebergeur']) ?></p>
    </section>

    <section class="page-info-section">
        <h2>Propriété intellectuelle</h2>
        <p>
            Les textes, photos, logos et le design de ce site appartiennent à
            <?= htmlspecialchars($e['raison_sociale']) ?> ou sont utilisés avec
            l'autorisation de leurs auteurs. Ils sont protégés par la loi fédérale sur
            le droit d'auteur (LDA) et ne peuvent pas être copiés ou réutilisés sans
            notre accord écrit.
        </p>
    </section>

    <section class="page-info-section">
        <h2>Responsabilité</h2>
        <p>
            Nous veillons à ce que les informations du site (descriptions, prix,
            disponibilités) soient exactes et à jour. Une erreur reste toutefois
            possible : en cas d'erreur manifeste de prix, nous vous contactons avant
            de traiter la commande. Les liens vers des sites externes sont fournis
            pour votre confort ; nous ne sommes pas responsables de leur contenu.
        </p>
    </section>

    <section class="page-info-section">
        <h2>Pages liées</h2>
        <ul class="page-info-liste">
            <li><a href="cgv.php">Conditions générales de vente</a></li>
            <li><a href="confidentialite.php">Politique de confidentialité</a></li>
        </ul>
        <p class="page-info-maj">Dernière mise à jour : <?= htmlspecialchars($e['mise_a_jour']) ?></p>
    </section>
</main>
<?php require __DIR__ . '/../morceaux-de-page/pied.php'; ?>
</body>
</html>
