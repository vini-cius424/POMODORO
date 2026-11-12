<?php
require __DIR__ . '/../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../back-end/classes/Produit.php';
require __DIR__ . '/../back-end/classes/Variante.php';
require __DIR__ . '/../back-end/classes/ImageProduit.php';
require __DIR__ . '/../back-end/classes/Avis.php';
require __DIR__ . '/../back-end/fonctions/couleurs.php';

session_start();

$produitId = $_GET['id'] ?? null;
$produit = $produitId ? Produit::trouver($pdo, $produitId) : null;

// Une fiche produit inactive (ou dans une categorie inactive) ou
// inexistante n'a rien a montrer au public.
if (!$produit || !$produit->estEnVente()) {
    header('Location: boutique.php');
    exit;
}

// --- Depot d'un avis ------------------------------------------------
// Reserve au client connecte : un administrateur n'a pas de client_id en
// session, il ne peut donc pas noter. Un visiteur non connecte non plus.
$clientId = $_SESSION['client_id'] ?? null;
$avisDuClient = $clientId ? Avis::pourClientEtProduit($pdo, $clientId, $produitId) : null;
$erreurAvis = '';
$avisEnregistre = isset($_GET['avis']) && $_GET['avis'] === 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $clientId && !$avisDuClient) {
    $note = $_POST['note'] ?? '';
    $commentaire = trim($_POST['commentaire'] ?? '');

    if (!is_numeric($note) || (int) $note < Avis::NOTE_MIN || (int) $note > Avis::NOTE_MAX) {
        $erreurAvis = "Merci de choisir une note de 1 à 5 étoiles.";
    } elseif (mb_strlen($commentaire) > 2000) {
        $erreurAvis = "Le commentaire ne peut pas dépasser 2000 caractères.";
    } else {
        try {
            Avis::creer($pdo, $clientId, $produitId, (int) $note, $commentaire);
            // Redirection apres ecriture : un F5 ne rejoue pas l'envoi.
            header('Location: fiche-produit.php?id=' . (int) $produitId . '&avis=ok');
            exit;
        } catch (PDOException $e) {
            // Deux cas que la base refuse, et qu'on traduit plutot que de
            // laisser passer une erreur technique :
            //  - FK_AVI_CLI : la session designe un compte supprime entre
            //    temps (un onglet reste ouvert, par exemple) ;
            //  - UQ_AVI_CLI_PRO : deux envois quasi simultanes du meme
            //    formulaire, le second arrive apres le premier.
            $erreurAvis = "Votre avis n'a pas pu être enregistré. "
                . "Reconnectez-vous et réessayez.";
        }
    }
}

$variantes = Variante::pourProduit($pdo, $produitId);
$images = ImageProduit::pourProduit($pdo, $produitId);

$imagesGenerales = array_values(array_filter($images, fn ($img) => $img->couleur === null));
if (!$imagesGenerales) {
    // Rien de "general" : au pire, la principale sert de repli pour toutes les couleurs.
    $imagesGenerales = array_values(array_filter($images, fn ($img) => $img->estPrincipale));
}

// Pour chaque couleur presente dans les variantes : ses tailles en stock
// (taille => variante id, pour le JS) et sa galerie (photos taguees,
// sinon les generales).
$couleursDonnees = [];
$ordrePhotoParCouleur = [];
$couleurPrincipale = null;
foreach (Variante::COULEURS as $couleur) {
    $taillesCouleur = [];
    $stocksFaibles = []; // taille => stock, seulement s'il en reste peu (marketing)
    foreach ($variantes as $variante) {
        if ($variante->couleur === $couleur && (int) $variante->stock > 0) {
            $taillesCouleur[$variante->taille] = (int) $variante->id;
            if ((int) $variante->stock <= Variante::SEUIL_STOCK_FAIBLE) {
                $stocksFaibles[$variante->taille] = (int) $variante->stock;
            }
        }
    }
    if (!$taillesCouleur) {
        continue;
    }

    $galerieCouleur = array_values(array_filter($images, fn ($img) => $img->couleur === $couleur));
    // Une couleur avec sa propre photo passe avant celles qui empruntent
    // les photos generales : ordre = celui de la premiere photo ajoutee.
    $ordrePhotoParCouleur[$couleur] = $galerieCouleur ? $galerieCouleur[0]->ordre : PHP_INT_MAX;

    // Au sein d'une meme couleur, la photo principale s'affiche d'abord,
    // meme si ce n'est pas la premiere ajoutee pour cette couleur.
    usort($galerieCouleur, fn ($a, $b) => $b->estPrincipale <=> $a->estPrincipale);
    $galerie = $galerieCouleur ?: $imagesGenerales;

    if ($couleurPrincipale === null && array_filter($galerieCouleur, fn ($img) => $img->estPrincipale)) {
        $couleurPrincipale = $couleur;
    }

    // Ordonnees selon TAILLES pour un affichage previsible (XS avant XXL).
    $taillesOrdonnees = [];
    foreach (Variante::TAILLES as $taille) {
        if (isset($taillesCouleur[$taille])) {
            $taillesOrdonnees[$taille] = $taillesCouleur[$taille];
        }
    }

    $couleursDonnees[$couleur] = [
        'tailles' => $taillesOrdonnees,
        // Seuls les stocks faibles partent dans la page : le stock exact
        // d'une taille bien fournie n'est jamais revele, meme dans le code.
        'stocksFaibles' => $stocksFaibles,
        'images' => array_map(fn ($img) => $img->chemin, $galerie),
    ];
}

// Trie par ordre d'ajout des photos plutot que par l'ordre fixe de la
// constante COULEURS (uasort/uksort : PHP 8 garde les egalites stables).
uksort($couleursDonnees, fn ($a, $b) => $ordrePhotoParCouleur[$a] <=> $ordrePhotoParCouleur[$b]);

// La couleur ouverte par defaut reste celle de la photo principale (donc
// la premiere ajoutee, sauf choix explicite via "Definir comme
// principale") : les autres restent dans leur ordre d'ajout.
if ($couleurPrincipale !== null && isset($couleursDonnees[$couleurPrincipale])) {
    $couleursDonnees = [$couleurPrincipale => $couleursDonnees[$couleurPrincipale]] + $couleursDonnees;
}

$couleurParDefaut = array_key_first($couleursDonnees);
$enRupture = !$couleursDonnees;

// Arrivee depuis la boutique avec une couleur (filtre couleur ou pastille
// cliquee sur la carte) : la fiche s'ouvre sur cette couleur, si elle est
// en stock. Une valeur inconnue dans l'URL est simplement ignoree.
$couleurDemandee = $_GET['couleur'] ?? '';
if (is_string($couleurDemandee) && isset($couleursDonnees[$couleurDemandee])) {
    $couleurParDefaut = $couleurDemandee;
}

$noteResume = Avis::resumePourProduits($pdo, [$produitId])[$produitId] ?? null;
$avisListe = Avis::pourProduit($pdo, $produitId);

$galerieParDefaut = $couleurParDefaut ? $couleursDonnees[$couleurParDefaut]['images'] : array_map(fn ($img) => $img->chemin, $imagesGenerales);
if (!$galerieParDefaut) {
    $galerieParDefaut = [null]; // un seul emplacement vide, pour garder la mise en page
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($produit->nom) ?> - Pomodoro Store</title>
    <link rel="stylesheet" href="styles/pomodoro.css?v=59">
</head>
<body>
<?php require __DIR__ . '/morceaux-de-page/entete.php'; ?>

<div class="fiche-produit">
    <a class="admin-fil fiche-produit-fil" href="boutique.php">← Retour à la boutique</a>

    <div class="fiche-produit-corps">
        <div class="fiche-produit-galerie">
            <div class="fiche-produit-image-principale">
                <?php if ($galerieParDefaut[0]): ?>
                    <img id="image-principale" src="<?= htmlspecialchars($galerieParDefaut[0]) ?>" alt="<?= htmlspecialchars($produit->nom) ?>">
                <?php else: ?>
                    <div id="image-principale" class="fiche-produit-image-vide"></div>
                <?php endif; ?>
                <?php if (count($galerieParDefaut) > 1): ?>
                    <button type="button" class="fiche-produit-fleche fiche-produit-fleche-gauche" id="fleche-gauche" aria-label="Photo précédente">‹</button>
                    <button type="button" class="fiche-produit-fleche fiche-produit-fleche-droite" id="fleche-droite" aria-label="Photo suivante">›</button>
                <?php endif; ?>
                <?php if ($enRupture): ?>
                    <span class="badge-rupture">Rupture de stock</span>
                <?php endif; ?>
            </div>
            <div class="fiche-produit-vignettes" id="vignettes"></div>
        </div>

        <div class="fiche-produit-info">
            <p class="fiche-produit-categorie"><?= htmlspecialchars($produit->categorieNom) ?></p>
            <h1 class="titre fiche-produit-nom"><?= htmlspecialchars($produit->nom) ?></h1>

            <p class="fiche-produit-notes">
                <?php if ($noteResume): ?>
                    <span class="fiche-produit-etoiles" aria-hidden="true"><?= str_repeat('★', (int) round($noteResume['moyenne'])) . str_repeat('☆', 5 - (int) round($noteResume['moyenne'])) ?></span>
                    <?= number_format($noteResume['moyenne'], 1) ?> (<?= $noteResume['nombre'] ?>)
                <?php else: ?>
                    Pas encore d'avis
                <?php endif; ?>
            </p>

            <p class="fiche-produit-prix">
                <?php if ($produit->prixPromo !== null): ?>
                    <span class="fiche-produit-prix-promo"><?= number_format((float) $produit->prixPromo, 2) ?> CHF</span>
                    <span class="fiche-produit-prix-barre"><?= number_format((float) $produit->prix, 2) ?> CHF</span>
                <?php else: ?>
                    <?= number_format((float) $produit->prix, 2) ?> CHF
                <?php endif; ?>
            </p>

            <?php if ($produit->description): ?>
                <p class="fiche-produit-description"><?= nl2br(htmlspecialchars($produit->description)) ?></p>
            <?php endif; ?>

            <?php
            // Message renvoye par actions/panier-ajouter.php dans l'URL.
            $messagePanier = $_GET['panier'] ?? '';
            ?>
            <?php if ($messagePanier === 'ajoute'): ?>
                <!-- Message a gauche, lien vers le panier a droite -->
                <p class="encadre encadre-succes encadre-avec-lien">
                    <span>Article ajouté au panier.</span>
                    <a href="panier.php">Voir le panier →</a>
                </p>
            <?php elseif ($messagePanier === 'stock'): ?>
                <p class="encadre encadre-echec">Stock insuffisant pour cette taille.</p>
            <?php endif; ?>

            <?php if ($enRupture): ?>
                <p class="encadre encadre-echec">Ce produit est actuellement en rupture de stock, dans toutes les couleurs.</p>
            <?php else: ?>
                <div class="fiche-produit-couleurs">
                    <span class="fiche-produit-label">Couleur : <strong id="libelle-couleur"><?= htmlspecialchars(couleur_libelle($couleurParDefaut)) ?></strong></span>
                    <div class="fiche-produit-swatches" id="swatches-couleur">
                        <?php foreach ($couleursDonnees as $couleur => $donnees): ?>
                            <button type="button" class="couleur-swatch couleur-swatch-uni couleur-swatch-bouton <?= $couleur === $couleurParDefaut ? 'couleur-swatch-selectionne' : '' ?> <?= $couleur === 'AUTRE' ? 'couleur-swatch-autre' : '' ?>"
                                    style="background-color: <?= couleur_hex($couleur) ?>;"
                                    data-couleur="<?= $couleur ?>" title="<?= htmlspecialchars(couleur_libelle($couleur)) ?>"></button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Formulaire d'ajout au panier. Chaque taille est un bouton
                     radio dont la valeur est l'identifiant de la variante :
                     le formulaire envoie donc directement la bonne variante
                     (couleur + taille). -->
                <form method="post" action="actions/panier-ajouter.php">
                    <div class="fiche-produit-tailles">
                        <span class="fiche-produit-label">Tailles disponibles</span>
                        <div class="fiche-produit-boutons-taille" id="boutons-taille"></div>
                        <p class="alerte-stock" id="alerte-stock" hidden></p>
                    </div>

                    <button type="submit" class="bouton fiche-produit-ajouter">Ajouter au panier</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="fiche-produit-avis">
        <h2 class="carte-admin-titre">Avis clients</h2>

        <?php if ($avisEnregistre): ?>
            <div class="encadre encadre-succes">
                <p class="encadre-titre">Merci pour votre avis</p>
                <p>Il est publié sur cette page.</p>
            </div>
        <?php endif; ?>

        <?php if ($erreurAvis): ?>
            <div class="encadre encadre-echec">
                <p class="encadre-titre">Avis non enregistré</p>
                <p><?= htmlspecialchars($erreurAvis) ?></p>
            </div>
        <?php endif; ?>

        <?php if ($avisDuClient): ?>
            <!-- Rien a afficher ici : l'avis est publie, il figure donc
                 deja dans la liste ci-dessous, signale comme etant le sien.
                 On montre seulement qu'il n'y a plus rien a saisir. -->
        <?php elseif ($clientId): ?>
            <form class="avis-formulaire" method="post" action="fiche-produit.php?id=<?= (int) $produitId ?>">
                <h3 class="avis-formulaire-titre">Donnez votre avis</h3>

                <!-- Boutons radio affiches en etoiles par le CSS. Sans CSS,
                     ils restent cinq choix utilisables au clavier. -->
                <fieldset class="avis-note-champ">
                    <legend>Votre note</legend>
                    <div class="avis-etoiles">
                        <?php for ($n = 5; $n >= 1; $n--): ?>
                            <input class="visuellement-cache" type="radio" id="note-<?= $n ?>" name="note" value="<?= $n ?>" required>
                            <label for="note-<?= $n ?>" title="<?= $n ?> sur 5"><span aria-hidden="true">★</span><span class="visuellement-cache"><?= $n ?> étoiles</span></label>
                        <?php endfor; ?>
                    </div>
                </fieldset>

                <div class="champ">
                    <label for="commentaire">Votre commentaire <span class="facultatif">(facultatif)</span></label>
                    <textarea id="commentaire" name="commentaire" rows="4" maxlength="2000"
                              placeholder="Qu'avez-vous pensé de ce produit ?"></textarea>
                </div>

                <button type="submit" class="bouton">Publier mon avis</button>
            </form>
        <?php else: ?>
            <p class="avis-invite">
                <a href="connexion.php">Connectez-vous</a> pour laisser un avis sur ce produit.
            </p>
        <?php endif; ?>

        <?php if (!$avisListe): ?>
            <p class="sous-titre">Aucun avis publié pour l'instant.</p>
        <?php else: ?>
            <?php foreach ($avisListe as $rang => $avis):
                // Le visiteur connecte reconnait son propre avis dans la liste.
                $estLeMien = $clientId && (int) $avis['AVI_CLI_ID'] === (int) $clientId;
                // La liste est triee par date decroissante : le rang 0 est le
                // dernier avis depose, le seul affiche d'entree. Les suivants
                // portent la classe que le JavaScript replie.
                $estReplie = $rang > 0;
            ?>
                <div class="avis-carte <?= $estLeMien ? 'avis-carte-mien' : '' ?> <?= $estReplie ? 'avis-carte-suite' : '' ?>">
                    <p class="fiche-produit-etoiles" aria-hidden="true"><?= str_repeat('★', (int) $avis['AVI_NOTE']) . str_repeat('☆', 5 - (int) $avis['AVI_NOTE']) ?></p>
                    <p class="avis-auteur"><?= $estLeMien ? 'Votre avis' : htmlspecialchars($avis['CLI_PRENOM']) ?> · <?= (new DateTime($avis['AVI_DATE']))->format('d.m.Y') ?></p>
                    <?php if ($avis['AVI_COMMENTAIRE']): ?>
                        <p><?= nl2br(htmlspecialchars($avis['AVI_COMMENTAIRE'])) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <?php if (count($avisListe) > 1): ?>
                <!-- Le bouton nait cache et c'est le JavaScript qui le montre,
                     en repliant les avis au passage. Sans JavaScript, la liste
                     reste entiere et aucun bouton inerte n'apparait. -->
                <button type="button" class="bouton-secondaire avis-voir-tout"
                        id="avis-voir-tout" aria-expanded="true" hidden>
                    Voir tout (<?= count($avisListe) ?> avis)
                </button>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    (function () {
        var bouton = document.getElementById('avis-voir-tout');
        if (!bouton) return;

        var suite = document.querySelectorAll('.avis-carte-suite');
        if (!suite.length) return;

        var libelleReplie = bouton.textContent.trim();

        function afficherSuite(ouvert) {
            suite.forEach(function (carte) { carte.hidden = !ouvert; });
            bouton.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
            bouton.textContent = ouvert ? 'Voir moins' : libelleReplie;
        }

        // On replie d'abord, on montre le bouton ensuite : si ce script ne
        // s'executait pas, la page resterait utilisable telle quelle.
        afficherSuite(false);
        bouton.hidden = false;

        bouton.addEventListener('click', function () {
            afficherSuite(bouton.getAttribute('aria-expanded') === 'false');
        });
    })();
</script>

<script>
    var donneesCouleurs = <?= json_encode($couleursDonnees) ?>;
    var couleurActuelle = <?= json_encode($couleurParDefaut) ?>;
    var indexImage = 0;

    function libelleCouleur(code) {
        var libelles = {NOIR:'Noir', BLANC:'Blanc', ROUGE:'Rouge', BLEU:'Bleu', VERT:'Vert', BEIGE:'Beige', GRIS:'Gris', AUTRE:'Autres'};
        return libelles[code] || code;
    }

    function afficherGalerie() {
        var images = (donneesCouleurs[couleurActuelle] || {}).images || [];
        var conteneur = document.getElementById('image-principale');
        if (!images.length) return;
        if (conteneur.tagName === 'IMG') {
            conteneur.src = images[indexImage];
        }
        var vignettes = document.getElementById('vignettes');
        vignettes.innerHTML = '';
        if (images.length > 1) {
            images.forEach(function (chemin, i) {
                var vignette = document.createElement('button');
                vignette.type = 'button';
                vignette.className = 'fiche-produit-vignette' + (i === indexImage ? ' fiche-produit-vignette-active' : '');
                vignette.innerHTML = '<img src="' + chemin + '" alt="">';
                vignette.addEventListener('click', function () { indexImage = i; afficherGalerie(); });
                vignettes.appendChild(vignette);
            });
        }
    }

    function afficherTailles() {
        var conteneur = document.getElementById('boutons-taille');
        if (!conteneur) return;
        var tailles = (donneesCouleurs[couleurActuelle] || {}).tailles || {};
        conteneur.innerHTML = '';
        // Une taille = un bouton radio cache dans une etiquette cliquable.
        // tailles[taille] est l'identifiant de la variante (ex. M noir = 12).
        // "required" : le navigateur refuse l'envoi tant qu'aucune taille
        // n'est choisie.
        Object.keys(tailles).forEach(function (taille) {
            var etiquette = document.createElement('label');
            etiquette.className = 'fiche-produit-bouton-taille';

            var radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = 'variante_id';
            radio.value = tailles[taille];
            radio.required = true;
            radio.addEventListener('change', function () { afficherAlerteStock(taille); });

            etiquette.appendChild(radio);
            etiquette.appendChild(document.createTextNode(taille));
            conteneur.appendChild(etiquette);
        });
        afficherAlerteStock(null); // nouvelle couleur : aucune taille choisie
    }

    // "Plus que 2 en stock dans cette taille !" sous les tailles, quand la
    // taille choisie est presque epuisee (Variante::SEUIL_STOCK_FAIBLE).
    function afficherAlerteStock(taille) {
        var alerte = document.getElementById('alerte-stock');
        if (!alerte) return;
        var stocks = (donneesCouleurs[couleurActuelle] || {}).stocksFaibles || {};
        var reste = taille !== null ? stocks[taille] : undefined;
        alerte.hidden = reste === undefined;
        if (reste !== undefined) {
            alerte.textContent = 'Plus que ' + reste + ' en stock dans cette taille !';
        }
    }

    function selectionnerCouleur(couleur) {
        couleurActuelle = couleur;
        indexImage = 0;
        document.getElementById('libelle-couleur').textContent = libelleCouleur(couleur);
        document.querySelectorAll('.couleur-swatch-bouton').forEach(function (b) {
            b.classList.toggle('couleur-swatch-selectionne', b.dataset.couleur === couleur);
        });
        afficherGalerie();
        afficherTailles();
    }

    document.querySelectorAll('.couleur-swatch-bouton').forEach(function (bouton) {
        bouton.addEventListener('click', function () { selectionnerCouleur(bouton.dataset.couleur); });
    });

    var flecheGauche = document.getElementById('fleche-gauche');
    var flecheDroite = document.getElementById('fleche-droite');
    if (flecheGauche) {
        flecheGauche.addEventListener('click', function () {
            var images = (donneesCouleurs[couleurActuelle] || {}).images || [];
            indexImage = (indexImage - 1 + images.length) % images.length;
            afficherGalerie();
        });
    }
    if (flecheDroite) {
        flecheDroite.addEventListener('click', function () {
            var images = (donneesCouleurs[couleurActuelle] || {}).images || [];
            indexImage = (indexImage + 1) % images.length;
            afficherGalerie();
        });
    }

    if (couleurActuelle) {
        afficherGalerie();
        afficherTailles();
    }
</script>
<?php require __DIR__ . '/morceaux-de-page/pied.php'; ?>
</body>
</html>
