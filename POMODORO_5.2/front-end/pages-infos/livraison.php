<?php
// =====================================================================
// POMODORO STORE - Page d'information : Livraison
//
// Les tarifs ne sont pas recopies a la main : ils viennent de
// Commande::fraisDePort(), la fonction qui calcule vraiment les frais au
// moment de commander. Changer un tarif la-bas met cette page a jour.
// =====================================================================

require __DIR__ . '/../../back-end/classes/Commande.php';

$prefixe = '../'; // pour l'en-tete et le pied : on est dans un sous-dossier

function tarif($mode)
{
    $frais = Commande::fraisDePort($mode);
    return $frais > 0 ? number_format($frais, 2) . ' CHF' : 'Gratuit';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Livraison - Pomodoro Store</title>
    <link rel="stylesheet" href="../styles/pomodoro.css?v=66">
</head>
<body>
<?php require __DIR__ . '/../morceaux-de-page/entete.php'; ?>
<main class="page-info">
    <p class="page-info-surtitre">Aide</p>
    <h1 class="titre">Livraison</h1>
    <p class="page-info-intro">
        Choisissez votre mode de livraison au moment de commander. Le prix est
        affiché avant la validation, et le total le prend en compte tout de suite.
    </p>

    <!-- Les trois modes proposes sur la page Commander. -->
    <div class="livraison-modes">
        <div class="livraison-mode">
            <h2>Livraison standard</h2>
            <p class="livraison-mode-prix"><?= tarif('STANDARD') ?></p>
            <p class="livraison-mode-delai">3 à 5 jours ouvrables</p>
            <p>Livraison à l'adresse de votre choix. Le mode le plus économique.</p>
        </div>
        <div class="livraison-mode">
            <h2>Livraison express</h2>
            <p class="livraison-mode-prix"><?= tarif('EXPRESS') ?></p>
            <p class="livraison-mode-delai">24 heures</p>
            <p>Pour un cadeau de dernière minute ou une envie pressée.</p>
        </div>
        <div class="livraison-mode">
            <h2>Retrait en magasin</h2>
            <p class="livraison-mode-prix"><?= tarif('RETRAIT_MAGASIN') ?></p>
            <p class="livraison-mode-delai">Dès que la commande est prête</p>
            <p>Aucun frais de port : venez chercher votre commande en boutique.</p>
        </div>
    </div>

    <section class="page-info-section">
        <h2>Zone de livraison</h2>
        <p>
            Nous livrons actuellement en Suisse. La livraison vers d'autres pays
            n'est pas encore proposée.
        </p>
    </section>

    <section class="page-info-section">
        <h2>Suivre ma commande</h2>
        <p>
            Chaque commande reçoit un numéro (par exemple <strong>POM-000012</strong>).
            Vous retrouvez toutes vos commandes et leur état dans
            <a href="../mon-compte.php?section=commandes">Mon compte › Mes commandes</a> :
        </p>
        <ol class="page-info-etapes">
            <li><strong>En attente</strong> : nous avons bien reçu votre commande.</li>
            <li><strong>Validée</strong> puis <strong>En préparation</strong> : nous préparons votre colis.</li>
            <li><strong>Expédiée</strong> : le colis est en route.</li>
            <li><strong>Livrée</strong> : le colis vous a été remis.</li>
        </ol>
    </section>

    <section class="page-info-section">
        <h2>Annuler une commande</h2>
        <p>
            Une commande peut être annulée tant qu'elle n'est pas encore expédiée.
            <a href="../contact.php?sujet=COMMANDE">Écrivez-nous</a> en indiquant son
            numéro. Une fois le colis parti, il faudra passer par un
            <a href="retours.php">retour</a>.
        </p>
    </section>

    <section class="page-info-section">
        <h2>Bon à savoir</h2>
        <ul class="page-info-liste">
            <li>Nos prix sont affichés en francs suisses, TVA comprise.</li>
            <li>Les frais de livraison s'ajoutent au prix des articles et apparaissent sur le récapitulatif avant de confirmer.</li>
            <li>Le code de bienvenue -20 % s'applique aux articles, pas aux frais de livraison.</li>
        </ul>
    </section>

    <aside class="page-info-aide">
        <p>Une question sur votre livraison ?</p>
        <a class="bouton" href="../contact.php?sujet=LIVRAISON">Nous contacter</a>
    </aside>
</main>
<?php require __DIR__ . '/../morceaux-de-page/pied.php'; ?>
</body>
</html>
