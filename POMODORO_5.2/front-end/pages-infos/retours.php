<?php
// =====================================================================
// POMODORO STORE - Page d'information : Retours et echanges
//
// Une demande de retour passe par le formulaire de contact, sujet
// "Un retour ou un echange" (contact.php?sujet=RETOUR) : elle arrive
// dans l'administration, onglet Messages.
//
// Le delai de retour est une regle commerciale de la boutique : il est
// ecrit une seule fois, dans back-end/fonctions/entreprise.php, et
// partage avec les conditions generales (cgv.php).
// =====================================================================

require __DIR__ . '/../../back-end/fonctions/entreprise.php';

$prefixe = '../'; // pour l'en-tete et le pied : on est dans un sous-dossier

$delaiRetourJours = entreprise()['delai_retour_jours'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Retours et échanges - Pomodoro Store</title>
    <link rel="stylesheet" href="../styles/pomodoro.css?v=66">
</head>
<body>
<?php require __DIR__ . '/../morceaux-de-page/entete.php'; ?>
<main class="page-info">
    <p class="page-info-surtitre">Aide</p>
    <h1 class="titre">Retours et échanges</h1>
    <p class="page-info-intro">
        La taille ne va pas, la couleur ne vous plaît pas ? Vous avez
        <strong><?= $delaiRetourJours ?> jours</strong> après la réception de votre
        colis pour nous retourner un article.
    </p>

    <section class="page-info-section">
        <h2>Comment faire un retour</h2>
        <ol class="page-info-etapes">
            <li>
                <strong>Faites votre demande</strong> avec le
                <a href="../contact.php?sujet=RETOUR">formulaire de contact</a>, sujet
                « Un retour ou un échange ». Indiquez le numéro de commande (POM-…),
                l'article concerné et si vous souhaitez un remboursement ou un échange.
            </li>
            <li>
                <strong>Nous vous répondons par email</strong> avec l'adresse de retour
                et les instructions.
            </li>
            <li>
                <strong>Renvoyez l'article</strong> dans son emballage d'origine, en
                gardant le justificatif d'envoi jusqu'au traitement de votre retour.
            </li>
            <li>
                <strong>Nous traitons le retour</strong> à la réception du colis :
                remboursement ou envoi du nouvel article.
            </li>
        </ol>
    </section>

    <section class="page-info-section">
        <h2>Conditions</h2>
        <ul class="page-info-liste">
            <li>Demande faite dans les <?= $delaiRetourJours ?> jours suivant la réception.</li>
            <li>Article non porté, non lavé, avec ses étiquettes d'origine.</li>
            <li>Le remboursement porte sur le prix payé pour l'article : si vous avez utilisé le code de bienvenue, c'est le prix après réduction.</li>
            <li>Les frais de livraison de la commande ne sont pas remboursés, sauf si l'article est défectueux ou si nous nous sommes trompés.</li>
        </ul>
    </section>

    <section class="page-info-section">
        <h2>Article défectueux ou erreur de notre part</h2>
        <p>
            Un défaut, une taille ou une couleur qui ne correspond pas à votre commande ?
            <a href="../contact.php?sujet=RETOUR">Contactez-nous</a> avec une photo de
            l'article : le retour est alors entièrement à notre charge.
        </p>
    </section>

    <section class="page-info-section">
        <h2>Pas encore expédiée ?</h2>
        <p>
            Si votre commande n'est pas encore partie, inutile d'attendre le colis :
            elle peut simplement être annulée. Voir la page <a href="livraison.php">Livraison</a>.
        </p>
    </section>

    <aside class="page-info-aide">
        <p>Prêt à faire votre demande ?</p>
        <a class="bouton" href="../contact.php?sujet=RETOUR">Demander un retour</a>
    </aside>
</main>
<?php require __DIR__ . '/../morceaux-de-page/pied.php'; ?>
</body>
</html>
