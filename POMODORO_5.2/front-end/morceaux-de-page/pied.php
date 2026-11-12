<?php
/**
 * Pied de page commun du site : marque, colonnes Boutique/Aide/
 * Informations (accordeon sur mobile), newsletter, bas de page. Attend
 * $prefixe avant l'inclusion : '' depuis front-end/, '../' depuis un
 * sous-dossier (meme convention que morceaux-de-page/entete.php).
 */
$prefixe = $prefixe ?? '';

require_once __DIR__ . '/../../back-end/connexion-base-de-donnees.php';
require_once __DIR__ . '/../../back-end/classes/Categorie.php';

if (!isset($categoriesPied)) {
    $categoriesPied = Categorie::toutes($pdo);
}

// Pop-up "-20 % sur votre premiere commande" : il invite a creer un
// compte, car le code ne vaut que pour la premiere commande d'un compte
// client. Il n'est donc affiche qu'aux visiteurs NON connectes. Le "deja
// ferme" est gere dans le navigateur, par javascript/pied.js.
$popupBienvenue = empty($_SESSION['client_id']) && empty($_SESSION['admin_id']);
?>
<footer class="pied" id="pied">
    <div class="pied-corps">
        <div class="pied-rangee">
            <div class="pied-colonne pied-marque pied-reveal">
                <a class="logo" href="<?= $prefixe ?>index.php">Pomodoro<span class="logo-point">.</span></a>
                <p class="pied-slogan">Votre style. Votre identité.</p>
                <p class="pied-accroche">Découvrez une sélection de vêtements pensée pour accompagner votre style au quotidien.</p>
            </div>

            <div class="pied-colonne pied-reveal">
                <button type="button" class="pied-titre pied-accordeon-bouton">
                    Boutique
                    <span class="pied-accordeon-signe">+</span>
                </button>
                <div class="pied-accordeon-contenu">
                    <!-- pied-liens-2-colonnes : une quinzaine de categories, on les
                         repartit sur 2 colonnes pour ne pas allonger tout le footer. -->
                    <div class="pied-accordeon-interieur pied-liens-2-colonnes">
                        <a href="<?= $prefixe ?>index.php">Accueil</a>
                        <?php foreach ($categoriesPied as $categorie): ?>
                            <a href="<?= $prefixe ?>boutique.php?categorie=<?= (int) $categorie->id ?>"><?= htmlspecialchars($categorie->nom) ?></a>
                        <?php endforeach; ?>
                        <a href="<?= $prefixe ?>boutique.php?nouveautes=1">Nouveautés</a>
                    </div>
                </div>
            </div>

            <div class="pied-colonne pied-reveal">
                <button type="button" class="pied-titre pied-accordeon-bouton">
                    Aide
                    <span class="pied-accordeon-signe">+</span>
                </button>
                <div class="pied-accordeon-contenu">
                    <div class="pied-accordeon-interieur">
                        <a href="<?= $prefixe ?>contact.php">Contact</a>
                        <a href="<?= $prefixe ?>pages-infos/faq.php">FAQ</a>
                        <a href="<?= $prefixe ?>pages-infos/livraison.php">Livraison</a>
                        <a href="<?= $prefixe ?>pages-infos/retours.php">Retours</a>
                    </div>
                </div>
            </div>

            <div class="pied-colonne pied-reveal">
                <button type="button" class="pied-titre pied-accordeon-bouton">
                    Informations
                    <span class="pied-accordeon-signe">+</span>
                </button>
                <div class="pied-accordeon-contenu">
                    <div class="pied-accordeon-interieur">
                        <a href="<?= $prefixe ?>pages-infos/cgv.php">Conditions générales</a>
                        <a href="<?= $prefixe ?>pages-infos/confidentialite.php">Politique de confidentialité</a>
                        <a href="<?= $prefixe ?>pages-infos/mentions-legales.php">Mentions légales</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="pied-newsletter pied-reveal">
            <div class="pied-newsletter-texte">
                <h3>Rejoignez Pomodoro<span class="logo-point">.</span></h3>
                <p>Recevez nos nouveautés, collections et offres exclusives.</p>
            </div>
            <!-- Envoye en arriere-plan par javascript/pied.js (fetch vers action). -->
            <form class="pied-newsletter-form" id="pied-newsletter-form"
                  method="post" action="<?= $prefixe ?>actions/newsletter-inscrire.php">
                <input type="email" name="email" placeholder="Votre adresse email" required>
                <button type="submit">S'inscrire</button>
                <p class="pied-newsletter-message" hidden></p>
            </form>
        </div>
    </div>

    <div class="pied-corps pied-bas">
        <span>&copy; <?= date('Y') ?> Pomodoro. Tous droits réservés.</span>
        <div class="pied-bas-liens">
            <a href="<?= $prefixe ?>pages-infos/cgv.php">Conditions générales</a>
            <a href="<?= $prefixe ?>pages-infos/confidentialite.php">Confidentialité</a>
        </div>
        <span class="pied-paiement">Paiement sécurisé</span>
    </div>
</footer>

<?php if ($popupBienvenue): ?>
    <!-- <dialog> : fenetre modale native du navigateur. showModal() (dans
         pied.js) l'ouvre par-dessus la page et gere la touche Echap. -->
    <dialog class="popup-bienvenue" id="popup-bienvenue" aria-labelledby="popup-bienvenue-titre">
        <div class="popup-bienvenue-contenu">
            <button type="button" class="popup-bienvenue-fermer" data-popup-fermer aria-label="Fermer">&times;</button>

            <div class="popup-bienvenue-haut">
                <p class="popup-bienvenue-accroche">Nouveau chez Pomodoro ?</p>
                <p class="popup-bienvenue-remise">-20%</p>
                <h2 class="popup-bienvenue-titre" id="popup-bienvenue-titre">sur votre première commande</h2>
            </div>

            <div class="popup-bienvenue-bas">
                <!-- Le code n'est pas affiche ici : il est envoye par email des
                     que le client a confirme son adresse (confirmer-email.php). -->
                <p class="popup-bienvenue-texte">
                    Créez votre compte et confirmez votre adresse email :
                    votre code de réduction vous est aussitôt envoyé par email.
                </p>
                <a class="bouton popup-bienvenue-bouton" href="<?= $prefixe ?>inscription.php" autofocus>Créer mon compte</a>
                <a class="popup-bienvenue-lien" href="<?= $prefixe ?>connexion.php">J'ai déjà un compte</a>
                <button type="button" class="popup-bienvenue-non" data-popup-fermer>Plus tard</button>
            </div>
        </div>
    </dialog>
<?php endif; ?>

<script src="<?= $prefixe ?>javascript/pied.js?v=5"></script>
