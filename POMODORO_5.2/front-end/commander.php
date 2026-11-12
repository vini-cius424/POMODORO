<?php
// =====================================================================
// POMODORO STORE - Page "Passer commande" (tache 10, segment 2)
//
// Dernier ecran avant la commande : recapitulatif du panier, adresse
// de livraison et mode de livraison.
// Le formulaire est traite par actions/commande-passer.php (segment 3).
// =====================================================================

// Reserve au client connecte : sinon, retour a la page de connexion.
require __DIR__ . '/../back-end/securite/garde-client.php';
require __DIR__ . '/../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../back-end/classes/Commande.php'; // charge aussi Panier.php
require __DIR__ . '/../back-end/classes/Adresse.php';
require __DIR__ . '/../back-end/fonctions/couleurs.php';
require __DIR__ . '/../back-end/fonctions/stripe.php';

$lignes = Panier::lignesPourClient($pdo, $_SESSION['client_id']);

// Les adresses deja enregistrees par le client (Mon compte > Adresses),
// l'adresse par defaut en premier.
$adresses = Adresse::listePourClient($pdo, $_SESSION['client_id']);

// Premiere commande du client ? Seule condition du code POMO20.
$premiereCommande = Commande::nombrePourClient($pdo, $_SESSION['client_id']) === 0;

// Panier vide : rien a commander, on renvoie vers le panier.
if (count($lignes) === 0) {
    header('Location: panier.php');
    exit;
}

$totalArticles = 0;
foreach ($lignes as $ligne) {
    $totalArticles += $ligne['PRIX_UNITAIRE'] * $ligne['LPA_QUANTITE'];
}

// Les 3 modes de livraison : code enregistre en base => texte affiche.
// Le code doit rester dans la liste de la contrainte CK_COM_LIVRAISON.
$modesLivraison = [
    'STANDARD'        => 'Livraison standard (3 à 5 jours)',
    'EXPRESS'         => 'Livraison express (24 h)',
    'RETRAIT_MAGASIN' => 'Retrait en magasin'
];

// Message renvoye par l'action du segment 3 si les champs sont invalides.
$message = $_GET['commande'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Passer commande - Pomodoro Store</title>
    <link rel="stylesheet" href="styles/pomodoro.css?v=59">
</head>
<body>
<?php require __DIR__ . '/morceaux-de-page/entete.php'; ?>
<div class="page-panier page-commander">
    <h1 class="titre">Passer commande</h1>

    <?php if ($message === 'invalide'): ?>
        <p class="encadre encadre-echec">Adresse ou mode de livraison invalide. Vérifiez les champs.</p>
    <?php elseif ($message === 'code'): ?>
        <!-- Meme verification que Commande::erreurCodePromo() : si c'est la
             premiere commande, le probleme ne peut etre que le code lui-meme. -->
        <p class="encadre encadre-echec">
            <?= $premiereCommande
                ? "Ce code promo n'existe pas. Votre commande n'a pas été passée."
                : "Le code " . Commande::CODE_PROMO_BIENVENUE . " est réservé à votre première commande. Votre commande n'a pas été passée." ?>
        </p>
    <?php elseif ($message === 'paiement'): ?>
        <!-- Renvoye par actions/commande-passer.php si Stripe n'a pas repondu -->
        <p class="encadre encadre-echec">Le service de paiement est momentanément indisponible. Votre commande n'a pas été passée, réessayez dans quelques instants.</p>
    <?php endif; ?>

    <!-- 1. Recapitulatif : ce que le client s'apprete a acheter -->
    <section class="compte-section">
        <h2 class="compte-section-titre">Récapitulatif</h2>
        <div class="tableau-scroll">
            <table class="tableau-admin">
                <thead>
                    <tr>
                        <th>Article</th>
                        <th>Quantité</th>
                        <th>Sous-total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lignes as $ligne): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($ligne['PRO_NOM']) ?></strong>
                                <p class="panier-detail"><?= htmlspecialchars(couleur_libelle($ligne['VAR_COULEUR'])) ?> · Taille <?= htmlspecialchars($ligne['VAR_TAILLE']) ?></p>
                            </td>
                            <td><?= (int) $ligne['LPA_QUANTITE'] ?></td>
                            <td><?= number_format($ligne['PRIX_UNITAIRE'] * $ligne['LPA_QUANTITE'], 2) ?> CHF</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <!-- Texte a gauche, bouton de retour au panier a droite -->
        <div class="commande-modifier-panier">
            <span class="panier-detail">D'autres envies ?</span>
            <a class="bouton-secondaire" href="panier.php">Modifier le panier</a>
        </div>
    </section>

    <form method="post" action="actions/commande-passer.php">
        <!-- 2. Adresse de livraison -->
        <section class="compte-section">
            <h2 class="compte-section-titre">Adresse de livraison</h2>

            <?php if (count($adresses) > 0): ?>
                <!-- Le client choisit une de ses adresses, ou "Autre adresse".
                     La premiere (celle par defaut) est cochee d'office. -->
                <div class="livraison-choix">
                    <?php foreach ($adresses as $position => $adresse): ?>
                        <label class="livraison-option">
                            <input type="radio" name="adresse_id" value="<?= (int) $adresse['ADR_ID'] ?>"
                                   <?= $position === 0 ? 'checked' : '' ?>>
                            <span>
                                <?= htmlspecialchars($adresse['ADR_RUE']) ?>,
                                <?= htmlspecialchars($adresse['ADR_NPA'] . ' ' . $adresse['ADR_VILLE']) ?>
                            </span>
                            <?php if ((int) $adresse['ADR_PAR_DEFAUT'] === 1): ?>
                                <span class="badge badge-oui">Par défaut</span>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                    <label class="livraison-option">
                        <input type="radio" name="adresse_id" value="nouvelle" id="adresse-nouvelle">
                        <span>Livrer à une autre adresse</span>
                    </label>
                </div>
            <?php else: ?>
                <!-- Aucune adresse enregistree : on saisit directement la nouvelle. -->
                <input type="hidden" name="adresse_id" value="nouvelle">
            <?php endif; ?>

            <!-- Champs de la nouvelle adresse. Si le client a deja des
                 adresses, ils ne s'affichent que pour "Autre adresse"
                 (voir le script en bas de page). -->
            <div class="formulaire adresse-nouvelle-champs" id="adresse-nouvelle-champs">
                <div class="champ">
                    <label for="rue">Rue et numéro</label>
                    <input type="text" id="rue" name="rue" maxlength="200" required>
                </div>
                <div class="champ champ-double">
                    <div>
                        <label for="npa">NPA</label>
                        <!-- pattern : exactement 4 chiffres (NPA suisse) -->
                        <input type="text" id="npa" name="npa" maxlength="4"
                               pattern="[0-9]{4}" inputmode="numeric" required>
                    </div>
                    <div>
                        <label for="ville">Ville</label>
                        <input type="text" id="ville" name="ville" maxlength="100" required>
                    </div>
                </div>
            </div>
        </section>

        <!-- 3. Mode de livraison : un bouton radio par mode -->
        <section class="compte-section">
            <h2 class="compte-section-titre">Mode de livraison</h2>
            <div class="livraison-choix">
                <?php foreach ($modesLivraison as $code => $libelle): ?>
                    <!-- data-frais : le prix, lu par le petit script plus bas -->
                    <label class="livraison-option">
                        <input type="radio" name="mode_livraison" value="<?= $code ?>"
                               data-frais="<?= Commande::fraisDePort($code) ?>"
                               <?= $code === 'STANDARD' ? 'checked' : '' ?>>
                        <span><?= $libelle ?></span>
                        <strong><?= number_format(Commande::fraisDePort($code), 2) ?> CHF</strong>
                    </label>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- 4. Code promo (facultatif). Le bouton "Appliquer" ne fait que
             mettre a jour l'affichage : le serveur reverifie le code. -->
        <section class="compte-section">
            <h2 class="compte-section-titre">Code promo</h2>
            <?php if ($premiereCommande): ?>
                <p class="panier-detail code-promo-astuce">
                    Première commande ? Saisissez le code de bienvenue reçu par email
                    à la confirmation de votre compte : -20 % sur vos articles.
                </p>
            <?php endif; ?>
            <div class="code-promo">
                <input type="text" id="code-promo" name="code_promo" maxlength="20"
                       autocomplete="off" aria-label="Code promo" placeholder="Votre code">
                <button type="button" class="bouton-secondaire" id="code-promo-appliquer">Appliquer</button>
            </div>
            <p class="code-promo-message" id="code-promo-message" hidden></p>
        </section>

        <!-- 5. Total et validation -->
        <div class="commande-total">
            <p>Articles : <?= number_format($totalArticles, 2) ?> CHF</p>
            <p id="ligne-remise" hidden>
                Réduction <?= Commande::CODE_PROMO_BIENVENUE ?> (-<?= (int) (Commande::TAUX_PROMO_BIENVENUE * 100) ?> %) :
                -<span id="remise-affichee">0.00</span> CHF
            </p>
            <p>Livraison : <span id="frais-affiches"><?= number_format(Commande::fraisDePort('STANDARD'), 2) ?></span> CHF</p>
            <p class="panier-total">Total : <strong><span id="total-affiche"><?= number_format($totalArticles + Commande::fraisDePort('STANDARD'), 2) ?></span> CHF</strong></p>
            <p class="ligne-tva">
                dont TVA <?= Commande::libelleTauxTva() ?> :
                <span id="tva-affichee"><?= number_format(Commande::tvaComprise($totalArticles + Commande::fraisDePort('STANDARD')), 2) ?></span> CHF
            </p>
            <!-- Avec Stripe, le bouton mene a la page de paiement -->
            <button type="submit" class="bouton commande-confirmer">
                <?= stripe_est_configure() ? 'Payer la commande' : 'Confirmer la commande' ?>
            </button>
        </div>
    </form>
</div>

<script>
// Quand le client change de mode de livraison, on met a jour les frais
// et le total affiches. C'est seulement de l'affichage : le vrai montant
// est recalcule par le serveur (Commande::passer), on ne fait jamais
// confiance a un prix envoye par le navigateur.
var totalArticles = <?= $totalArticles ?>;
var frais = <?= Commande::fraisDePort('STANDARD') ?>;
var remise = 0;
var tauxTva = <?= Commande::TAUX_TVA ?>;
var boutonsRadio = document.querySelectorAll('input[name="mode_livraison"]');

// Total affiche = articles - remise + frais de port (meme calcul que
// Commande::passer, qui fait foi).
function afficherTotal() {
    // toFixed(2) : affiche 2 decimales (7 -> "7.00").
    document.getElementById('frais-affiches').textContent = frais.toFixed(2);
    document.getElementById('remise-affichee').textContent = remise.toFixed(2);
    document.getElementById('ligne-remise').hidden = remise === 0;
    var total = totalArticles - remise + frais;
    document.getElementById('total-affiche').textContent = total.toFixed(2);
    // TVA comprise dans le total : meme calcul que Commande::tvaComprise().
    var tva = Math.round(total * tauxTva / (1 + tauxTva) * 100) / 100;
    document.getElementById('tva-affichee').textContent = tva.toFixed(2);
}

for (var i = 0; i < boutonsRadio.length; i++) {
    boutonsRadio[i].addEventListener('change', function () {
        // parseFloat : transforme le texte "7" en nombre 7.
        frais = parseFloat(this.dataset.frais);
        afficherTotal();
    });
}

// Code promo : memes regles que Commande::erreurCodePromo(), pour
// prevenir le client tout de suite. Le serveur reverifie de toute facon.
var codeBienvenue = '<?= Commande::CODE_PROMO_BIENVENUE ?>';
var tauxBienvenue = <?= Commande::TAUX_PROMO_BIENVENUE ?>;
var premiereCommande = <?= $premiereCommande ? 'true' : 'false' ?>;
var champCode = document.getElementById('code-promo');
var messageCode = document.getElementById('code-promo-message');

function appliquerCode() {
    var code = champCode.value.trim().toUpperCase();
    remise = 0;
    messageCode.hidden = code === '';
    messageCode.className = 'code-promo-message';

    if (code === codeBienvenue && premiereCommande) {
        // Math.round(x * 100) / 100 : arrondi au centime, comme round(..., 2) en PHP.
        remise = Math.round(totalArticles * tauxBienvenue * 100) / 100;
        messageCode.textContent = 'Code appliqué : -' + Math.round(tauxBienvenue * 100) + ' % sur vos articles.';
        messageCode.className += ' code-promo-message-ok';
    } else if (code === codeBienvenue) {
        messageCode.textContent = 'Le code ' + codeBienvenue + ' est réservé à votre première commande.';
    } else if (code !== '') {
        messageCode.textContent = "Ce code promo n'existe pas.";
    }
    afficherTotal();
}

document.getElementById('code-promo-appliquer').addEventListener('click', appliquerCode);
champCode.addEventListener('change', appliquerCode);

// Entree dans le champ du code : on applique le code, sans envoyer la
// commande (le comportement normal d'Entree dans un formulaire).
champCode.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        appliquerCode();
    }
});

// Adresse : les champs rue / NPA / ville ne servent que pour
// "Livrer a une autre adresse". Pour une adresse enregistree, on les
// cache ET on les desactive (disabled) : un champ desactive n'est ni
// verifie par "required", ni envoye au serveur.
var champsAdresse = document.getElementById('adresse-nouvelle-champs');
var choixAdresse = document.querySelectorAll('input[name="adresse_id"][type="radio"]');

function afficherChampsAdresse() {
    var nouvelle = document.getElementById('adresse-nouvelle');
    // Pas de bouton "autre adresse" = le client n'a aucune adresse
    // enregistree : les champs restent toujours visibles.
    var afficher = nouvelle === null || nouvelle.checked;

    champsAdresse.hidden = !afficher;
    var champs = champsAdresse.querySelectorAll('input');
    for (var j = 0; j < champs.length; j++) {
        champs[j].disabled = !afficher;
    }
}

for (var k = 0; k < choixAdresse.length; k++) {
    choixAdresse[k].addEventListener('change', afficherChampsAdresse);
}
afficherChampsAdresse(); // etat de depart, au chargement de la page
</script>
<?php require __DIR__ . '/morceaux-de-page/pied.php'; ?>
</body>
</html>
