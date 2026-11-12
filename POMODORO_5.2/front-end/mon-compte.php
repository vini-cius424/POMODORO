<?php
// =====================================================================
// POMODORO STORE - Mon compte (tache 14 : gerer son profil)
//
// Espace personnel du client, organise en onglets dans une barre
// laterale : Commandes, Adresses, Coordonnees, Mot de passe et
// Fermeture du compte. L'onglet actif est donne par ?section=... et
// affiche seul cote droit (mon-compte.php?section=adresses).
// =====================================================================

// Reserve au client connecte : sinon, retour a la page de connexion.
require __DIR__ . '/../back-end/securite/garde-client.php';
require __DIR__ . '/../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../back-end/classes/Client.php';
require __DIR__ . '/../back-end/classes/Commande.php';
require __DIR__ . '/../back-end/classes/Adresse.php';
require __DIR__ . '/../back-end/fonctions/icones.php';

// On relit le client en base plutot que de se fier a la session :
// les coordonnees affichees sont toujours les vraies.
$client = Client::trouver($pdo, $_SESSION['client_id']);

// Compte introuvable ou desactive : on le deconnecte.
if ($client === null) {
    header('Location: deconnexion.php');
    exit;
}

$erreurs = [];

// Valeurs affichees dans le formulaire : celles de la base au depart.
// ?? "" : le telephone peut etre NULL, on affiche alors une case vide.
$prenom    = $client->getPrenom();
$nom       = $client->getNom();
$email     = $client->getEmail();
$telephone = $client->getTelephone() ?? "";

// --- Traitement du formulaire des coordonnees ---
// Le champ cache "formulaire" dit quel formulaire a ete envoye : la page
// en contiendra plusieurs (coordonnees, mot de passe, fermeture).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['formulaire'] ?? '') === 'coordonnees') {

    $prenom    = trim($_POST['prenom'] ?? '');
    $nom       = trim($_POST['nom'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');

    // Memes regles qu'a l'inscription.
    if ($prenom === '') {
        $erreurs['prenom'] = "Le prénom est obligatoire.";
    } elseif (strlen($prenom) > 100) {
        $erreurs['prenom'] = "Le prénom ne peut pas dépasser 100 caractères.";
    }

    if ($nom === '') {
        $erreurs['nom'] = "Le nom est obligatoire.";
    } elseif (strlen($nom) > 100) {
        $erreurs['nom'] = "Le nom ne peut pas dépasser 100 caractères.";
    }

    if ($email === '') {
        $erreurs['email'] = "L'adresse email est obligatoire.";
    } elseif (strlen($email) > 191) {
        $erreurs['email'] = "L'adresse email ne peut pas dépasser 191 caractères.";
    } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $erreurs['email'] = "Cette adresse email n'est pas valide.";
    } elseif (Client::emailDejaUtilise($pdo, $email, $client->getId())) {
        // Troisieme parametre : son propre email n'est pas un doublon.
        $erreurs['email'] = "Cette adresse email est déjà utilisée.";
    }

    if ($telephone !== '' && strlen($telephone) > 30) {
        $erreurs['telephone'] = "Le numéro ne peut pas dépasser 30 caractères.";
    }

    if (count($erreurs) === 0) {
        Client::modifierCoordonnees($pdo, $client->getId(), $nom, $prenom, $email, $telephone);

        // Le prenom est aussi garde en session (affiche dans l'en-tete) :
        // on le met a jour.
        $_SESSION['client_prenom'] = $prenom;

        // Redirection apres l'enregistrement : si le client rafraichit la
        // page, le formulaire n'est pas renvoye une deuxieme fois.
        header('Location: mon-compte.php?section=coordonnees&compte=coordonnees');
        exit;
    }
}

// --- Traitement du formulaire du mot de passe (segment 3) ---
// Erreurs dans un tableau a part : elles s'affichent sous CE formulaire,
// pas sous celui des coordonnees.
$erreursMdp = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['formulaire'] ?? '') === 'motdepasse') {

    // Les mots de passe ne sont jamais passes dans trim() : un espace
    // peut faire partie du mot de passe choisi.
    $ancien       = $_POST['ancien'] ?? '';
    $nouveau      = $_POST['nouveau'] ?? '';
    $confirmation = $_POST['confirmation'] ?? '';

    // 1. Le mot de passe actuel est obligatoire : quelqu'un qui trouve
    //    un ordinateur reste connecte ne peut pas le changer.
    if (!$client->motDePasseCorrect($ancien)) {
        $erreursMdp['ancien'] = "Le mot de passe actuel est incorrect.";
    }

    // 2. Memes regles qu'a l'inscription pour le nouveau.
    if (strlen($nouveau) < 8) {
        $erreursMdp['nouveau'] = "Le mot de passe doit contenir au moins 8 caractères.";
    } elseif (!preg_match("/[a-zA-Z]/", $nouveau) || !preg_match("/[0-9]/", $nouveau)) {
        $erreursMdp['nouveau'] = "Le mot de passe doit contenir au moins une lettre et un chiffre.";
    } elseif ($nouveau === $ancien) {
        $erreursMdp['nouveau'] = "Le nouveau mot de passe doit être différent de l'actuel.";
    }

    // 3. La confirmation doit etre identique.
    if ($confirmation !== $nouveau) {
        $erreursMdp['confirmation'] = "Les deux mots de passe ne correspondent pas.";
    }

    if (count($erreursMdp) === 0) {
        Client::changerMotDePasse($pdo, $client->getId(), $nouveau);

        // Nouvel identifiant de session, comme a la connexion : une
        // ancienne session volee ne vaut plus rien.
        session_regenerate_id(true);

        header('Location: mon-compte.php?section=motdepasse&compte=motdepasse');
        exit;
    }
}

// --- Traitement de la fermeture du compte (segment 4) ---
// Suppression LOGIQUE : Client::desactiver() passe CLI_ACTIF a 0, la
// ligne reste en base (pour l'historique des futures commandes).
$erreurFermeture = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['formulaire'] ?? '') === 'fermeture') {

    // Mot de passe obligatoire : fermer un compte est definitif pour le
    // client, on s'assure que c'est bien lui.
    if (!$client->motDePasseCorrect($_POST['mot_de_passe'] ?? '')) {
        $erreurFermeture = "Mot de passe incorrect : le compte n'a pas été fermé.";
    } else {
        Client::desactiver($pdo, $client->getId());

        // Meme chose que deconnexion.php : on vide et detruit la session.
        session_unset();
        session_destroy();

        header('Location: index.php');
        exit;
    }
}

// --- Onglet actif ---
// Par defaut : "commandes" (premiere chose que le client veut voir en
// arrivant). Un onglet inconnu dans l'URL retombe aussi sur "commandes".
$sectionsValides = ['commandes', 'adresses', 'coordonnees', 'motdepasse', 'fermeture'];
$sectionActif = $_GET['section'] ?? 'commandes';
if (!in_array($sectionActif, $sectionsValides, true)) {
    $sectionActif = 'commandes';
}

// Si un formulaire vient d'etre renvoye avec une erreur (donc sans la
// redirection ci-dessus), on force son onglet : sinon l'erreur affichee
// ne serait pas sous les yeux du client.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['formulaire'] ?? '') === 'coordonnees') {
        $sectionActif = 'coordonnees';
    } elseif (($_POST['formulaire'] ?? '') === 'motdepasse') {
        $sectionActif = 'motdepasse';
    } elseif (($_POST['formulaire'] ?? '') === 'fermeture') {
        $sectionActif = 'fermeture';
    }
}

// Donnees propres a l'onglet affiche, chargees seulement si besoin.
if ($sectionActif === 'commandes') {
    $commandes = Commande::listePourClient($pdo, $client->getId());
}
if ($sectionActif === 'adresses') {
    $adresses = Adresse::listePourClient($pdo, $client->getId());
}

// Message de succes affiche apres la redirection.
$message = $_GET['compte'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mon compte - Pomodoro Store</title>
    <link rel="stylesheet" href="styles/pomodoro.css?v=60">
</head>
<body>
<?php require __DIR__ . '/morceaux-de-page/entete.php'; ?>
<div class="page-client page-compte">
    <div class="compte-shell">
        <nav class="compte-nav">
            <a class="compte-nav-lien <?= $sectionActif === 'commandes' ? 'compte-nav-lien-actif' : '' ?>"
               href="mon-compte.php?section=commandes"><?= icone_admin('commande') ?> Commandes</a>
            <a class="compte-nav-lien <?= $sectionActif === 'adresses' ? 'compte-nav-lien-actif' : '' ?>"
               href="mon-compte.php?section=adresses"><?= icone_admin('adresse') ?> Adresses</a>
            <a class="compte-nav-lien <?= $sectionActif === 'coordonnees' ? 'compte-nav-lien-actif' : '' ?>"
               href="mon-compte.php?section=coordonnees"><?= icone_admin('profil') ?> Coordonnées</a>
            <a class="compte-nav-lien <?= $sectionActif === 'motdepasse' ? 'compte-nav-lien-actif' : '' ?>"
               href="mon-compte.php?section=motdepasse"><?= icone_admin('cadenas') ?> Mot de passe</a>
            <a class="compte-nav-lien <?= $sectionActif === 'fermeture' ? 'compte-nav-lien-actif' : '' ?>"
               href="mon-compte.php?section=fermeture"><?= icone_admin('alerte') ?> Fermer mon compte</a>

            <hr class="compte-nav-separateur">
            <a class="compte-nav-lien" href="deconnexion.php"><?= icone_admin('deconnexion') ?> Se déconnecter</a>
        </nav>

        <div class="compte-contenu">
            <h1 class="titre">Bonjour <?= htmlspecialchars($client->getPrenom()) ?></h1>
            <p class="sous-titre">Gérez votre compte et suivez vos commandes.</p>

            <?php if ($message === 'coordonnees'): ?>
                <p class="encadre encadre-succes">Vos coordonnées ont été mises à jour.</p>
            <?php elseif ($message === 'motdepasse'): ?>
                <p class="encadre encadre-succes">Votre mot de passe a été modifié.</p>
            <?php endif; ?>

            <!-- ---------- Commandes ---------- -->
            <?php if ($sectionActif === 'commandes'): ?>
                <section class="compte-section compte-section-premiere">
                    <h2 class="compte-section-titre">Mes commandes</h2>

                    <?php if (count($commandes) === 0): ?>
                        <p class="panier-detail">
                            Vous n'avez pas encore passé de commande.
                            <a href="boutique.php">Découvrir la boutique</a>
                        </p>
                    <?php else: ?>
                        <div class="tableau-scroll">
                            <table class="tableau-admin tableau-commandes">
                                <thead>
                                    <tr>
                                        <th>Numéro</th>
                                        <th>Date</th>
                                        <th>Articles</th>
                                        <th>Total</th>
                                        <th>Statut</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($commandes as $commande): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($commande['COM_NUMERO']) ?></strong></td>
                                            <td><?= date('d.m.Y', strtotime($commande['COM_DATE'])) ?></td>
                                            <td><?= (int) $commande['NB_ARTICLES'] ?></td>
                                            <td><?= number_format((float) $commande['COM_MONTANT_TOTAL'], 2) ?> CHF</td>
                                            <td>
                                                <span class="badge badge-statut-<?= strtolower($commande['COM_STATUT']) ?>">
                                                    <?= Commande::STATUTS[$commande['COM_STATUT']] ?>
                                                </span>
                                                <!-- Date du dernier changement de statut, s'il y en a eu un -->
                                                <?php if ($commande['COM_DATE_MAJ']): ?>
                                                    <span class="commande-maj">le <?= date('d.m.Y à H:i', strtotime($commande['COM_DATE_MAJ'])) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="actions">
                                                <a class="lien-action"
                                                   href="commande-confirmation.php?numero=<?= urlencode($commande['COM_NUMERO']) ?>">Voir les détails</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <!-- ---------- Adresses ---------- -->
            <?php if ($sectionActif === 'adresses'): ?>
                <section class="compte-section compte-section-premiere">
                    <div class="compte-section-entete">
                        <h2 class="compte-section-titre">Mes adresses</h2>
                        <a class="bouton-secondaire" href="adresse-formulaire.php">+ Ajouter une adresse</a>
                    </div>

                    <?php if (count($adresses) === 0): ?>
                        <p class="panier-detail">Vous n'avez pas encore enregistré d'adresse.</p>
                    <?php else: ?>
                        <div class="grille-adresses">
                            <?php foreach ($adresses as $adresse): ?>
                                <div class="adresse-carte">
                                    <?php if ((int) $adresse['ADR_PAR_DEFAUT'] === 1): ?>
                                        <span class="badge badge-oui adresse-badge-defaut">Par défaut</span>
                                    <?php endif; ?>
                                    <p class="adresse-rue"><?= htmlspecialchars($adresse['ADR_RUE']) ?></p>
                                    <p class="panier-detail">
                                        <?= htmlspecialchars($adresse['ADR_NPA']) ?> <?= htmlspecialchars($adresse['ADR_VILLE']) ?><br>
                                        <?= htmlspecialchars($adresse['ADR_PAYS']) ?>
                                    </p>
                                    <div class="adresse-carte-actions">
                                        <a class="lien-action" href="adresse-formulaire.php?id=<?= (int) $adresse['ADR_ID'] ?>">Modifier</a>
                                        <?php if ((int) $adresse['ADR_PAR_DEFAUT'] !== 1): ?>
                                            <form method="post" action="actions/adresse-defaut.php">
                                                <input type="hidden" name="id" value="<?= (int) $adresse['ADR_ID'] ?>">
                                                <button type="submit" class="lien-action">Définir par défaut</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" action="actions/adresse-supprimer.php"
                                              onsubmit="return confirm('Supprimer cette adresse ?');">
                                            <input type="hidden" name="id" value="<?= (int) $adresse['ADR_ID'] ?>">
                                            <button type="submit" class="lien-action lien-action-danger">Supprimer</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <!-- ---------- Coordonnees ---------- -->
            <?php if ($sectionActif === 'coordonnees'): ?>
                <section class="compte-section compte-section-premiere">
                    <h2 class="compte-section-titre">Mes coordonnées</h2>

                    <form class="formulaire" method="post" action="mon-compte.php?section=coordonnees">
                        <!-- Champ cache : indique a PHP quel formulaire est envoye -->
                        <input type="hidden" name="formulaire" value="coordonnees">

                        <div class="champ champ-double">
                            <div>
                                <label for="prenom">Prénom</label>
                                <input type="text" id="prenom" name="prenom" maxlength="100"
                                       value="<?= htmlspecialchars($prenom) ?>"
                                       class="<?= isset($erreurs['prenom']) ? 'champ-erreur' : '' ?>">
                                <?php if (isset($erreurs['prenom'])): ?>
                                    <span class="message-erreur"><?= $erreurs['prenom'] ?></span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <label for="nom">Nom</label>
                                <input type="text" id="nom" name="nom" maxlength="100"
                                       value="<?= htmlspecialchars($nom) ?>"
                                       class="<?= isset($erreurs['nom']) ? 'champ-erreur' : '' ?>">
                                <?php if (isset($erreurs['nom'])): ?>
                                    <span class="message-erreur"><?= $erreurs['nom'] ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="champ">
                            <label for="email">Adresse email</label>
                            <input type="email" id="email" name="email" maxlength="191"
                                   value="<?= htmlspecialchars($email) ?>"
                                   class="<?= isset($erreurs['email']) ? 'champ-erreur' : '' ?>">
                            <?php if (isset($erreurs['email'])): ?>
                                <span class="message-erreur"><?= $erreurs['email'] ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="champ">
                            <label for="telephone">Téléphone <span class="facultatif">(facultatif)</span></label>
                            <input type="tel" id="telephone" name="telephone" maxlength="30"
                                   value="<?= htmlspecialchars($telephone) ?>"
                                   class="<?= isset($erreurs['telephone']) ? 'champ-erreur' : '' ?>">
                            <?php if (isset($erreurs['telephone'])): ?>
                                <span class="message-erreur"><?= $erreurs['telephone'] ?></span>
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="bouton">Enregistrer</button>
                    </form>
                </section>
            <?php endif; ?>

            <!-- ---------- Mot de passe ---------- -->
            <!-- Les champs mot de passe ne sont jamais pre-remplis (pas de value). -->
            <?php if ($sectionActif === 'motdepasse'): ?>
                <section class="compte-section compte-section-premiere">
                    <h2 class="compte-section-titre">Changer mon mot de passe</h2>

                    <form class="formulaire" method="post" action="mon-compte.php?section=motdepasse">
                        <input type="hidden" name="formulaire" value="motdepasse">

                        <div class="champ">
                            <label for="ancien">Mot de passe actuel</label>
                            <input type="password" id="ancien" name="ancien" required
                                   class="<?= isset($erreursMdp['ancien']) ? 'champ-erreur' : '' ?>">
                            <?php if (isset($erreursMdp['ancien'])): ?>
                                <span class="message-erreur"><?= $erreursMdp['ancien'] ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="champ">
                            <label for="nouveau">Nouveau mot de passe</label>
                            <input type="password" id="nouveau" name="nouveau" required
                                   class="<?= isset($erreursMdp['nouveau']) ? 'champ-erreur' : '' ?>">
                            <?php if (isset($erreursMdp['nouveau'])): ?>
                                <span class="message-erreur"><?= $erreursMdp['nouveau'] ?></span>
                            <?php else: ?>
                                <span class="aide">Au moins 8 caractères, dont une lettre et un chiffre.</span>
                            <?php endif; ?>
                        </div>

                        <div class="champ">
                            <label for="confirmation">Confirmer le nouveau mot de passe</label>
                            <input type="password" id="confirmation" name="confirmation" required
                                   class="<?= isset($erreursMdp['confirmation']) ? 'champ-erreur' : '' ?>">
                            <?php if (isset($erreursMdp['confirmation'])): ?>
                                <span class="message-erreur"><?= $erreursMdp['confirmation'] ?></span>
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="bouton">Changer le mot de passe</button>
                    </form>
                </section>
            <?php endif; ?>

            <!-- ---------- Fermeture du compte ---------- -->
            <?php if ($sectionActif === 'fermeture'): ?>
                <section class="compte-section compte-section-premiere">
                    <h2 class="compte-section-titre">Fermer mon compte</h2>
                    <p class="sous-titre">Vous ne pourrez plus vous connecter avec ce compte.</p>

                    <!-- onsubmit + confirm() : fenetre "Etes-vous sur ?" du navigateur.
                         "return false" (clic sur Annuler) bloque l'envoi du formulaire. -->
                    <form class="formulaire" method="post" action="mon-compte.php?section=fermeture"
                          onsubmit="return confirm('Fermer définitivement votre compte ?');">
                        <input type="hidden" name="formulaire" value="fermeture">

                        <div class="champ">
                            <label for="mot_de_passe_fermeture">Mot de passe</label>
                            <input type="password" id="mot_de_passe_fermeture" name="mot_de_passe" required
                                   class="<?= $erreurFermeture ? 'champ-erreur' : '' ?>">
                            <?php if ($erreurFermeture): ?>
                                <span class="message-erreur"><?= $erreurFermeture ?></span>
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="bouton bouton-danger">Fermer mon compte</button>
                    </form>
                </section>
            <?php endif; ?>

        </div>
    </div>
</div>
<?php require __DIR__ . '/morceaux-de-page/pied.php'; ?>
</body>
</html>
