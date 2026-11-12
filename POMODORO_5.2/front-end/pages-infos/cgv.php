<?php
// =====================================================================
// POMODORO STORE - Conditions generales de vente (CGV)
//
// Vente en ligne de vetements a des particuliers, en Suisse. Points de
// droit suisse repris ici :
//  - LCD art. 3 al. 1 let. s : identite du vendeur, etapes de la
//    commande, correction des erreurs de saisie, confirmation ;
//  - OIP (ordonnance sur l'indication des prix) : prix en CHF, TVA
//    comprise ;
//  - CO art. 197 ss et 210 : garantie pour les defauts, 2 ans pour un
//    bien de consommation neuf ;
//  - CO art. 40a ss : pas de droit de revocation legal pour un achat en
//    ligne, le retour est donc un geste commercial ;
//  - CPC art. 32 : for du domicile du consommateur reserve.
//
// Les chiffres (TVA, frais de port, delai de retour) ne sont pas recopies
// a la main : ils viennent du code qui les applique vraiment.
// =====================================================================

require __DIR__ . '/../../back-end/classes/Commande.php';
require __DIR__ . '/../../back-end/fonctions/entreprise.php';

$prefixe = '../'; // pour l'en-tete et le pied : on est dans un sous-dossier
$e = entreprise();
$chf = fn ($montant) => number_format($montant, 2) . ' CHF';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Conditions générales de vente - Pomodoro Store</title>
    <link rel="stylesheet" href="../styles/pomodoro.css?v=66">
</head>
<body>
<?php require __DIR__ . '/../morceaux-de-page/entete.php'; ?>
<main class="page-info">
    <p class="page-info-surtitre">Informations</p>
    <h1 class="titre">Conditions générales de vente</h1>
    <p class="page-info-intro">
        Les règles qui s'appliquent à tout achat sur Pomodoro Store. En confirmant
        une commande, vous déclarez les avoir lues et acceptées.
    </p>

    <section class="page-info-section">
        <h2>1. Vendeur et champ d'application</h2>
        <p>
            Le site est exploité par <?= htmlspecialchars($e['raison_sociale']) ?>,
            <?= htmlspecialchars($e['adresse']) ?>, <?= htmlspecialchars($e['npa_ville']) ?>
            (<a href="mentions-legales.php">mentions légales</a>). Les présentes conditions
            s'appliquent aux ventes conclues sur ce site avec des clients particuliers,
            pour une livraison en Suisse. La version applicable est celle en vigueur au
            moment de la commande.
        </p>
    </section>

    <section class="page-info-section">
        <h2>2. Commande et conclusion du contrat</h2>
        <p>Une commande se passe en quelques étapes, depuis un compte client :</p>
        <ol class="page-info-etapes">
            <li>choix des articles (taille, couleur, quantité) et ajout au panier ;</li>
            <li>vérification du panier, où les quantités peuvent être modifiées ou les articles retirés ;</li>
            <li>sur la page Commander : adresse, mode de livraison et code promo éventuel ;</li>
            <li>contrôle du récapitulatif (articles, réduction, frais de livraison, total TTC), puis clic sur « Confirmer la commande ».</li>
        </ol>
        <p>
            Tant que la commande n'est pas confirmée, vous pouvez revenir en arrière et
            corriger toute erreur de saisie. La commande confirmée reçoit un numéro
            (POM-…) et s'affiche aussitôt à l'écran ; une confirmation vous est aussi
            envoyée par email. Le contrat de vente est conclu à ce moment. Une offre du
            site ne vaut que dans la limite des stocks disponibles.
        </p>
    </section>

    <section class="page-info-section">
        <h2>3. Prix</h2>
        <p>
            Les prix sont indiqués en francs suisses (CHF), TVA suisse comprise
            (taux de <?= Commande::libelleTauxTva() ?>). Les frais de livraison s'y ajoutent
            et sont affichés avant la confirmation : livraison standard
            <?= $chf(Commande::fraisDePort('STANDARD')) ?>, express
            <?= $chf(Commande::fraisDePort('EXPRESS')) ?>, retrait en magasin gratuit.
            Le prix applicable est celui affiché au moment de la commande ; une
            modification ultérieure ne change pas une commande déjà passée.
        </p>
        <p>
            Le code de bienvenue <?= Commande::CODE_PROMO_BIENVENUE ?> donne
            <?= (int) (Commande::TAUX_PROMO_BIENVENUE * 100) ?> % de réduction sur les
            articles (hors frais de livraison), une seule fois, sur la première commande
            d'un compte. Il n'est ni cumulable ni échangeable contre de l'argent.
        </p>
    </section>

    <section class="page-info-section">
        <h2>4. Paiement</h2>
        <p>[Modalités de paiement à compléter : moyens acceptés, moment du paiement. Le site n'intègre pas encore de paiement en ligne.]</p>
    </section>

    <section class="page-info-section">
        <h2>5. Livraison</h2>
        <p>
            Nous livrons en Suisse. Les délais (3 à 5 jours ouvrables en standard,
            24 heures en express) sont indicatifs ; un retard ne donne pas droit à
            des dommages-intérêts, sauf faute grave de notre part. Les profits et les
            risques de la marchandise vous sont transférés à la remise du colis. Détails
            sur la page <a href="livraison.php">Livraison</a>.
        </p>
    </section>

    <section class="page-info-section">
        <h2>6. Annulation</h2>
        <p>
            Une commande peut être annulée sans frais tant qu'elle n'a pas été expédiée :
            il suffit de nous écrire avec le <a href="../contact.php?sujet=COMMANDE">formulaire de contact</a>.
            Les articles sont alors remis en stock et le montant éventuellement payé vous est remboursé.
        </p>
    </section>

    <section class="page-info-section">
        <h2>7. Retours et échanges</h2>
        <p>
            Le droit suisse ne prévoit pas de droit de rétractation pour les achats en
            ligne. Pomodoro Store vous accorde toutefois, à titre commercial, la
            possibilité de retourner un article dans les <?= (int) $e['delai_retour_jours'] ?>
            jours suivant sa réception, aux conditions décrites sur la page
            <a href="retours.php">Retours et échanges</a>.
        </p>
    </section>

    <section class="page-info-section">
        <h2>8. Garantie</h2>
        <p>
            Vérifiez votre colis dès sa réception et signalez-nous sans délai tout défaut
            ou toute erreur (article, taille, couleur), photo à l'appui
            (art. 201 du Code des obligations). Un défaut caché doit être signalé dès
            sa découverte.
        </p>
        <p>
            Nos articles sont couverts par la garantie légale pour les défauts de deux
            ans à compter de la livraison (art. 197 ss et 210 CO). En cas de défaut
            reconnu, nous proposons au choix la réparation, le remplacement ou le
            remboursement de l'article. L'usure normale et les dommages dus à un
            entretien qui ne respecte pas l'étiquette ne sont pas des défauts.
        </p>
    </section>

    <section class="page-info-section">
        <h2>9. Responsabilité</h2>
        <p>
            Notre responsabilité est limitée aux dommages causés intentionnellement ou
            par négligence grave, dans la mesure permise par la loi. La responsabilité
            prévue par la loi fédérale sur la responsabilité du fait des produits (LRFP)
            est réservée.
        </p>
    </section>

    <section class="page-info-section">
        <h2>10. Protection des données</h2>
        <p>
            Les données nécessaires à votre commande sont traitées conformément à la loi
            fédérale sur la protection des données (LPD). Voir notre
            <a href="confidentialite.php">politique de confidentialité</a>.
        </p>
    </section>

    <section class="page-info-section">
        <h2>11. Droit applicable et for</h2>
        <p>
            Les contrats conclus sur ce site sont soumis au droit suisse, à l'exclusion
            de la Convention des Nations Unies sur les contrats de vente internationale
            de marchandises (CVIM). Le for est au siège de
            <?= htmlspecialchars($e['raison_sociale']) ?> ; en tant que consommateur, vous
            pouvez toujours agir devant le tribunal de votre domicile (art. 32 du Code
            de procédure civile).
        </p>
        <p class="page-info-maj">Dernière mise à jour : <?= htmlspecialchars($e['mise_a_jour']) ?></p>
    </section>
</main>
<?php require __DIR__ . '/../morceaux-de-page/pied.php'; ?>
</body>
</html>
