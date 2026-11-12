<?php
// =====================================================================
// POMODORO STORE - Politique de confidentialite
//
// Devoir d'informer de la loi federale sur la protection des donnees
// (LPD, revisee, en vigueur depuis le 01.09.2023), art. 19 : qui traite
// les donnees, lesquelles, pourquoi, a qui elles sont communiquees (et
// a quel pays), combien de temps, et comment exercer ses droits.
//
// Le contenu decrit ce que le site fait VRAIMENT : seuls le cookie de
// session PHP et le sessionStorage du pop-up sont utilises, aucun outil
// de statistiques ni de publicite ; le seul prestataire externe est
// Brevo (emails et newsletter). A mettre a jour si cela change.
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
    <title>Politique de confidentialité - Pomodoro Store</title>
    <link rel="stylesheet" href="../styles/pomodoro.css?v=66">
</head>
<body>
<?php require __DIR__ . '/../morceaux-de-page/entete.php'; ?>
<main class="page-info">
    <p class="page-info-surtitre">Informations</p>
    <h1 class="titre">Politique de confidentialité</h1>
    <p class="page-info-intro">
        Quelles données nous collectons, pourquoi, et quels sont vos droits, selon
        la loi fédérale sur la protection des données (LPD).
    </p>

    <section class="page-info-section">
        <h2>1. Responsable du traitement</h2>
        <p>
            <?= htmlspecialchars($e['raison_sociale']) ?>, <?= htmlspecialchars($e['adresse']) ?>,
            <?= htmlspecialchars($e['npa_ville']) ?>, <?= htmlspecialchars($e['pays']) ?>.
            Contact : <?= htmlspecialchars($e['email']) ?> ou le
            <a href="../contact.php">formulaire de contact</a>.
        </p>
    </section>

    <section class="page-info-section">
        <h2>2. Données collectées et utilisation</h2>
        <div class="tableau-scroll">
            <table>
                <tr><th>Données</th><th>Pourquoi</th></tr>
                <tr>
                    <td>Compte client : nom, prénom, email, téléphone (facultatif), mot de passe</td>
                    <td>Créer et sécuriser votre compte, vous identifier. Le mot de passe n'est jamais stocké en clair, seulement son empreinte chiffrée.</td>
                </tr>
                <tr>
                    <td>Adresses de livraison</td>
                    <td>Livrer vos commandes.</td>
                </tr>
                <tr>
                    <td>Commandes : articles, prix, livraison, code promo</td>
                    <td>Exécuter le contrat de vente, suivre la commande, respecter nos obligations comptables.</td>
                </tr>
                <tr>
                    <td>Avis sur les produits : note, commentaire</td>
                    <td>Les publier sur la fiche du produit, avec votre prénom uniquement.</td>
                </tr>
                <tr>
                    <td>Messages du formulaire de contact</td>
                    <td>Répondre à votre demande.</td>
                </tr>
                <tr>
                    <td>Email d'inscription à la newsletter</td>
                    <td>Vous envoyer nos nouveautés, seulement après votre confirmation par le lien reçu par email.</td>
                </tr>
            </table>
        </div>
        <p>
            Nous ne vendons pas vos données et ne les utilisons pas pour de la publicité ciblée.
        </p>
    </section>

    <section class="page-info-section">
        <h2>3. Cookies</h2>
        <p>
            Le site n'utilise qu'un seul cookie, strictement nécessaire : le cookie de
            session, qui garde votre connexion pendant la visite. Il
            disparaît à la fermeture du navigateur. Le navigateur retient aussi, le temps
            de l'onglet, que vous avez fermé la fenêtre de bienvenue. Aucun outil de
            statistiques ni de publicité n'est utilisé.
        </p>
    </section>

    <section class="page-info-section">
        <h2>4. Prestataires et communication à l'étranger</h2>
        <ul class="page-info-liste">
            <li>
                <strong>Brevo (Sendinblue SAS, France)</strong> envoie nos emails
                (confirmation d'adresse, de commande, mot de passe oublié) et gère la liste
                de la newsletter. La France fait partie des États dont la législation
                assure un niveau de protection adéquat selon le Conseil fédéral
                (annexe 1 de l'ordonnance sur la protection des données).
            </li>
            <li>
                <strong>Hébergement du site</strong> : <?= htmlspecialchars($e['hebergeur']) ?>.
            </li>
        </ul>
        <p>Ces prestataires traitent les données uniquement pour notre compte.</p>
    </section>

    <section class="page-info-section">
        <h2>5. Durée de conservation</h2>
        <ul class="page-info-liste">
            <li>Compte et adresses : tant que le compte existe.</li>
            <li>
                Commandes : 10 ans, durée de conservation des pièces comptables imposée par
                le Code des obligations (art. 958f CO). Une adresse ou un article que vous
                retirez de votre compte est masqué, mais reste lié aux commandes déjà passées.
            </li>
            <li>Messages de contact : le temps de traiter la demande et son suivi.</li>
            <li>Newsletter : jusqu'à votre désinscription, possible à tout moment par le lien présent dans chaque envoi.</li>
        </ul>
    </section>

    <section class="page-info-section">
        <h2>6. Sécurité</h2>
        <p>
            Nous prenons des mesures techniques pour protéger vos données : mots de passe
            chiffrés, liens de confirmation à usage unique et limités dans le temps, accès
            à l'administration réservé aux personnes autorisées.
        </p>
    </section>

    <section class="page-info-section">
        <h2>7. Vos droits</h2>
        <p>Selon la LPD, vous pouvez à tout moment :</p>
        <ul class="page-info-liste">
            <li>savoir quelles données nous avons sur vous (droit d'accès, art. 25 LPD) ;</li>
            <li>les faire corriger si elles sont inexactes ;</li>
            <li>demander leur effacement, sous réserve des données que la loi nous oblige à conserver ;</li>
            <li>vous opposer à leur traitement, par exemple pour la newsletter ;</li>
            <li>recevoir les données que vous nous avez fournies dans un format courant (art. 28 LPD).</li>
        </ul>
        <p>
            Écrivez-nous avec le <a href="../contact.php">formulaire de contact</a> ou à
            <?= htmlspecialchars($e['email']) ?> : nous répondons dans un délai de 30 jours.
            Vous pouvez aussi vous adresser au Préposé fédéral à la protection des données
            et à la transparence (PFPDT).
        </p>
        <p class="page-info-maj">Dernière mise à jour : <?= htmlspecialchars($e['mise_a_jour']) ?></p>
    </section>
</main>
<?php require __DIR__ . '/../morceaux-de-page/pied.php'; ?>
</body>
</html>
