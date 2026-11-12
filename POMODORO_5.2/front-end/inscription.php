<?php
// =====================================================================
// POMODORO STORE - Page de connexion et d'inscription
//
// SEGMENT 4 : inscription reelle. La page verifie les champs, controle
// que l'email est libre, puis enregistre le client en base.
// =====================================================================

// __DIR__ est le dossier de CE fichier. Le ".." remonte d'un cran, vers
// site/, pour aller chercher config/ et classes/.
require __DIR__ . "/../back-end/connexion-base-de-donnees.php";
require __DIR__ . "/../back-end/classes/Client.php";
require __DIR__ . "/../back-end/fonctions/brevo.php";

// $erreurs contiendra un message par champ fautif.
// Un tableau vide a la fin = la saisie est correcte.
$erreurs = [];

// On garde ce que le visiteur a tape pour le reafficher : sinon il doit
// tout retaper des qu'une seule erreur apparait.
$prenom    = "";
$nom       = "";
$email     = "";
$telephone = "";

// Passe a true quand le compte a bien ete cree (utilise a l'affichage).
$inscriptionReussie = false;

// Ce qui s'est passe pour l'email de confirmation, une fois le compte cree :
//   'envoye'  : l'email est parti, le client doit cliquer sur le lien ;
//   'echec'   : Brevo a refuse ou n'a pas repondu ;
//   'inutile' : Brevo n'est pas configure sur ce poste, compte confirme d'office.
$etatConfirmation = '';

// Ce bloc ne s'execute que si le formulaire a ete envoye.
// Au premier affichage de la page la methode est GET : on saute tout.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // ?? "" : si le champ n'existe pas, on prend une chaine vide.
    $prenom       = trim($_POST["prenom"] ?? "");
    $nom          = trim($_POST["nom"] ?? "");
    $email        = trim($_POST["email"] ?? "");
    $telephone    = trim($_POST["telephone"] ?? "");
    $motDePasse   = $_POST["mot_de_passe"] ?? "";
    $confirmation = $_POST["confirmation"] ?? "";

    // --- Prenom ---
    if ($prenom === "") {
        $erreurs["prenom"] = "Le prénom est obligatoire.";
    } elseif (strlen($prenom) > 100) {
        $erreurs["prenom"] = "Le prénom ne peut pas dépasser 100 caractères.";
    }

    // --- Nom ---
    if ($nom === "") {
        $erreurs["nom"] = "Le nom est obligatoire.";
    } elseif (strlen($nom) > 100) {
        $erreurs["nom"] = "Le nom ne peut pas dépasser 100 caractères.";
    }

    // --- Email ---
    // filter_var verifie le format : du texte, un @, un domaine.
    if ($email === "") {
        $erreurs["email"] = "L'adresse email est obligatoire.";
    } elseif (strlen($email) > 191) {
        $erreurs["email"] = "L'adresse email ne peut pas dépasser 191 caractères.";
    } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $erreurs["email"] = "Cette adresse email n'est pas valide.";
    }

    // --- Telephone (facultatif : on ne verifie que s'il est rempli) ---
    if ($telephone !== "" && strlen($telephone) > 30) {
        $erreurs["telephone"] = "Le numéro ne peut pas dépasser 30 caractères.";
    }

    // --- Mot de passe ---
    // preg_match cherche un motif : [a-zA-Z] = au moins une lettre,
    // [0-9] = au moins un chiffre.
    if ($motDePasse === "") {
        $erreurs["mot_de_passe"] = "Le mot de passe est obligatoire.";
    } elseif (strlen($motDePasse) < 8) {
        $erreurs["mot_de_passe"] = "Le mot de passe doit contenir au moins 8 caractères.";
    } elseif (!preg_match("/[a-zA-Z]/", $motDePasse) || !preg_match("/[0-9]/", $motDePasse)) {
        $erreurs["mot_de_passe"] = "Le mot de passe doit contenir au moins une lettre et un chiffre.";
    }

    // --- Confirmation ---
    if ($confirmation === "") {
        $erreurs["confirmation"] = "Merci de confirmer le mot de passe.";
    } elseif ($motDePasse !== $confirmation) {
        $erreurs["confirmation"] = "Les deux mots de passe ne correspondent pas.";
    }

    // count() compte les elements du tableau : 0 = aucune erreur.
    // On ne va en base que si tous les champs sont deja corrects : inutile
    // d'interroger MariaDB pour un formulaire a moitie vide.
    if (count($erreurs) === 0) {

        if (Client::emailDejaUtilise($pdo, $email)) {
            $erreurs["email"] = "Cette adresse email est déjà utilisée.";
        } else {
            // Attention a l'ordre des parametres : le constructeur attend
            // le nom AVANT le prenom.
            $client = new Client($nom, $prenom, $email, $motDePasse, $telephone);
            $client->sInscrire($pdo);
            $inscriptionReussie = true;

            // Le compte existe, mais il ne permet de se connecter qu'une fois
            // l'adresse confirmee : on envoie le lien de confirmation.
            if (brevo_est_configure()) {
                $envoye = brevo_envoyer_confirmation($pdo, $client->getId(), $client->getPrenom(), $client->getEmail());
                $etatConfirmation = $envoye ? 'envoye' : 'echec';
            } else {
                Client::marquerEmailConfirme($pdo, $client->getId());
                $etatConfirmation = 'inutile';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Créer un compte - Pomodoro Store</title>
    <link rel="stylesheet" href="styles/pomodoro.css?v=57">
</head>
<body>

<div class="split">

    <!-- ---------- Panneau sombre (gauche) ---------- -->
    <div class="panneau-sombre">
        <a class="logo" href="index.php">Pomodoro<span class="logo-point">.</span></a>
        <p class="accroche">Des vêtements qui vous ressemblent.</p>
        <p class="accroche-texte">
            Créez votre compte pour suivre vos commandes et retrouver
            vos articles préférés.
        </p>

        <!-- Retour vers la boutique, colle en bas du panneau. title
             s'affiche au survol de la souris, aria-label est ce que lisent
             les lecteurs d'ecran : sans lui, une fleche seule ne veut rien
             dire pour une personne aveugle. -->
        <a class="retour" href="boutique.php" title="Retour à la boutique"
           aria-label="Retour à la boutique">&larr;</a>
    </div>

    <!-- ---------- Panneau clair (droite) ---------- -->
    <div class="panneau-clair">

        <div class="onglets">
            <a class="onglet onglet-actif" href="inscription.php">Créer un compte</a>
            <a class="onglet" href="connexion.php">Se connecter</a>
        </div>

        <h1 class="titre">Bienvenue</h1>
        <p class="sous-titre">Quelques informations et c'est terminé.</p>

        <?php if ($inscriptionReussie && $etatConfirmation === 'envoye') { ?>
            <div class="encadre encadre-succes">
                <p class="encadre-titre">Plus qu'une étape : confirmez votre email</p>
                <p>Bienvenue <?php echo htmlspecialchars($prenom); ?> ! Un email vient d'être
                   envoyé à <strong><?php echo htmlspecialchars($email); ?></strong>.
                   Cliquez sur le lien qu'il contient (valable 24 heures) pour activer
                   votre compte : vous recevrez aussitôt votre code de bienvenue
                   (-20 % sur votre première commande). Pensez à regarder dans vos spams.</p>
                <p>Gardez cette page ouverte : elle vous connectera automatiquement
                   dès que l'adresse sera confirmée.</p>
            </div>
            <script src="javascript/attente-confirmation.js?v=2"></script>
        <?php } elseif ($inscriptionReussie && $etatConfirmation === 'echec') { ?>
            <div class="encadre encadre-echec">
                <p class="encadre-titre">Compte créé, mais email non envoyé</p>
                <p>L'email de confirmation n'a pas pu être envoyé. Essayez de vous
                   <a href="connexion.php">connecter</a> : un nouvel email vous sera envoyé.</p>
            </div>
        <?php } elseif ($inscriptionReussie) { ?>
            <div class="encadre encadre-succes">
                <p class="encadre-titre">Votre compte a été créé</p>
                <p>Bienvenue <?php echo htmlspecialchars($prenom); ?>, vous pouvez maintenant
                   vous <a href="connexion.php">connecter</a>.</p>
            </div>
        <?php } elseif (count($erreurs) > 0) { ?>
            <div class="encadre encadre-echec">
                <p class="encadre-titre">Le formulaire contient des erreurs</p>
                <p>Corrigez les champs signalés ci-dessous.</p>
            </div>
        <?php } ?>

        <!-- Une fois le compte cree, le formulaire disparait : il n'y a
             plus rien a saisir. -->
        <?php if (!$inscriptionReussie) { ?>

        <form class="formulaire" method="post" action="inscription.php">

            <div class="champ champ-double">
                <div>
                    <label for="prenom">Prénom</label>
                    <!-- htmlspecialchars : si le visiteur tape du code HTML,
                         il sera affiche comme du texte et non execute. -->
                    <input type="text" id="prenom" name="prenom" maxlength="100"
                           value="<?php echo htmlspecialchars($prenom); ?>"
                           class="<?php echo isset($erreurs["prenom"]) ? "champ-erreur" : ""; ?>">
                    <?php if (isset($erreurs["prenom"])) { ?>
                        <span class="message-erreur"><?php echo $erreurs["prenom"]; ?></span>
                    <?php } ?>
                </div>
                <div>
                    <label for="nom">Nom</label>
                    <input type="text" id="nom" name="nom" maxlength="100"
                           value="<?php echo htmlspecialchars($nom); ?>"
                           class="<?php echo isset($erreurs["nom"]) ? "champ-erreur" : ""; ?>">
                    <?php if (isset($erreurs["nom"])) { ?>
                        <span class="message-erreur"><?php echo $erreurs["nom"]; ?></span>
                    <?php } ?>
                </div>
            </div>

            <div class="champ">
                <label for="email">Adresse email</label>
                <input type="email" id="email" name="email" maxlength="191"
                       value="<?php echo htmlspecialchars($email); ?>"
                       class="<?php echo isset($erreurs["email"]) ? "champ-erreur" : ""; ?>">
                <?php if (isset($erreurs["email"])) { ?>
                    <span class="message-erreur"><?php echo $erreurs["email"]; ?></span>
                <?php } ?>
            </div>

            <div class="champ">
                <label for="telephone">
                    Téléphone <span class="facultatif">(facultatif)</span>
                </label>
                <input type="tel" id="telephone" name="telephone" maxlength="30"
                       value="<?php echo htmlspecialchars($telephone); ?>"
                       class="<?php echo isset($erreurs["telephone"]) ? "champ-erreur" : ""; ?>">
                <?php if (isset($erreurs["telephone"])) { ?>
                    <span class="message-erreur"><?php echo $erreurs["telephone"]; ?></span>
                <?php } ?>
            </div>

            <!-- Les mots de passe ne sont jamais reaffiches : pas de value. -->
            <div class="champ">
                <label for="mot_de_passe">Mot de passe</label>
                <!-- data-force-mdp : id de la barre de robustesse a mettre a
                     jour pendant la frappe (javascript/force-mot-de-passe.js). -->
                <input type="password" id="mot_de_passe" name="mot_de_passe" data-force-mdp="force-mdp"
                       class="<?php echo isset($erreurs["mot_de_passe"]) ? "champ-erreur" : ""; ?>">
                <!-- Cachee tant que le champ est vide. aria-live : le niveau est
                     annonce par les lecteurs d'ecran quand il change. -->
                <div class="force-mdp" id="force-mdp" hidden>
                    <div class="force-mdp-barre"><span class="force-mdp-remplissage"></span></div>
                    <span class="force-mdp-texte" aria-live="polite"></span>
                </div>
                <?php if (isset($erreurs["mot_de_passe"])) { ?>
                    <span class="message-erreur"><?php echo $erreurs["mot_de_passe"]; ?></span>
                <?php } else { ?>
                    <span class="aide">Au moins 8 caractères, dont une lettre et un chiffre.</span>
                <?php } ?>
            </div>

            <div class="champ">
                <label for="confirmation">Confirmer le mot de passe</label>
                <input type="password" id="confirmation" name="confirmation"
                       class="<?php echo isset($erreurs["confirmation"]) ? "champ-erreur" : ""; ?>">
                <?php if (isset($erreurs["confirmation"])) { ?>
                    <span class="message-erreur"><?php echo $erreurs["confirmation"]; ?></span>
                <?php } ?>
            </div>

            <button type="submit" class="bouton">Créer mon compte</button>

        </form>

        <?php } ?>

    </div>
</div>

<script src="javascript/force-mot-de-passe.js"></script>
</body>
</html>
