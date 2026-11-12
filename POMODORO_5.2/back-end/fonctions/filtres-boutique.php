<?php
// =====================================================================
// POMODORO STORE - Filtres de la boutique
//
// Quand le visiteur filtre la boutique, ses choix arrivent dans l'URL :
//     boutique.php?categorie=2&taille=S,M&tri=prix_asc
// PHP les recoit dans le tableau $_GET.
//
// Ce fichier fait trois choses :
//  1. lire_filtres_catalogue() : lit $_GET et ne garde que les valeurs valides
//  2. page_catalogue_courante() : lit le numero de page
//  3. url_catalogue()           : reconstruit une URL a partir des filtres
//
// Il est utilise par DEUX pages :
//  - boutique.php         : le premier affichage (fonctionne meme sans JavaScript)
//  - boutique-filtrer.php : les mises a jour sans rechargement (AJAX)
// Comme les deux passent par ce meme fichier, elles comprennent toujours
// les filtres exactement de la meme facon.
// =====================================================================


// ---------------------------------------------------------------------
// 1. Lire et valider les filtres
//
// $categories : la liste des categories, obtenue avec Categorie::toutes().
// Renvoie un tableau propre, par exemple :
//     ['categorie' => 2, 'taille' => ['S', 'M'], 'tri' => 'prix_asc', ...]
//
// Regle importante : on ne fait JAMAIS confiance a l'URL. Le visiteur
// peut y taper n'importe quoi ; tout ce qui n'est pas valide est ignore.
// ---------------------------------------------------------------------
function lire_filtres_catalogue(array $categories): array
{
    // --- Categorie ---
    // On verifie que la categorie demandee existe vraiment en base.
    $categorieId = $_GET['categorie'] ?? null;
    $categorieValide = false;
    foreach ($categories as $categorie) {
        // (int) convertit en nombre entier : "2" devient 2.
        if ((int) $categorie->id === (int) $categorieId) {
            $categorieValide = true;
            break; // trouvee : inutile de continuer la boucle
        }
    }

    // --- Prix ---
    // Lus ici, verifies plus bas dans le return.
    $prixMin = $_GET['prix_min'] ?? '';
    $prixMax = $_GET['prix_max'] ?? '';

    // --- Tailles ---
    // Les tailles peuvent arriver sous deux formes :
    //  - taille[]=S&taille[]=M : PHP en fait deja un tableau
    //  - taille=S,M            : une seule chaine (envoyee par le JavaScript)
    // Les deux doivent donner le meme resultat : ['S', 'M'].
    $tailleBrute = $_GET['taille'] ?? '';

    // is_array() : vrai si c'est deja un tableau.
    // explode(',', ...) : decoupe "S,M" en ['S', 'M'].
    $taillesDemandees = is_array($tailleBrute) ? $tailleBrute : explode(',', (string) $tailleBrute);

    // On nettoie en plusieurs etapes (a lire de l'interieur vers l'exterieur) :
    //  - array_map('trim', ...)        : enleve les espaces autour de chaque taille
    //  - array_intersect(..., TAILLES) : ne garde que les tailles autorisees
    //  - array_unique(...)             : supprime les doublons
    //  - array_values(...)             : renumerote les cases 0, 1, 2...
    $tailles = array_values(array_unique(array_intersect(array_map('trim', $taillesDemandees), Variante::TAILLES)));

    // Tableau final. Chaque ligne suit le meme principe :
    // "si la valeur est valide, on la garde, sinon valeur vide".
    // in_array(valeur, liste, true) : vrai si la valeur est dans la liste
    // (le true impose le meme type, "1" n'est pas egal a 1).
    return [
        'categorie' => $categorieValide ? (int) $categorieId : null,

        // Recherche texte libre : juste les espaces enleves autour.
        'q' => trim((string) ($_GET['q'] ?? '')),

        // Type de vetement : doit faire partie de Produit::TYPES.
        'type' => in_array($_GET['type'] ?? '', Produit::TYPES, true) ? $_GET['type'] : '',

        'sous_categorie' => trim((string) ($_GET['sous_categorie'] ?? '')),
        'taille' => $tailles,

        // Couleur : doit faire partie de Variante::COULEURS.
        'couleur' => in_array($_GET['couleur'] ?? '', Variante::COULEURS, true) ? $_GET['couleur'] : '',

        // is_numeric() : vrai si c'est bien un nombre ("49.90" oui, "abc" non).
        'prix_min' => is_numeric($prixMin) ? (float) $prixMin : '',
        'prix_max' => is_numeric($prixMax) ? (float) $prixMax : '',

        // Cases a cocher : 1 si cochee, 0 sinon.
        // !empty() : vrai si la valeur existe et n'est pas vide.
        'promo' => !empty($_GET['promo']) ? 1 : 0,
        'nouveautes' => !empty($_GET['nouveautes']) ? 1 : 0,

        // Tri : seulement trois valeurs acceptees ('' = tri par defaut).
        'tri' => in_array($_GET['tri'] ?? '', ['prix_asc', 'prix_desc', 'nouveautes'], true) ? $_GET['tri'] : '',
    ];
}


// ---------------------------------------------------------------------
// 2. Numero de la page courante
//
// ?page=3 dans l'URL -> renvoie 3.
// Absent, zero, negatif ou texte -> renvoie 1 (premiere page).
// ---------------------------------------------------------------------
function page_catalogue_courante(): int
{
    // (int) "abc" donne 0, donc un texte finit aussi sur la page 1.
    $page = (int) ($_GET['page'] ?? 1);
    return $page > 0 ? $page : 1;
}


// ---------------------------------------------------------------------
// 3. Construire une URL de la boutique
//
// Repart des filtres actuels et en remplace certains.
// Utilise par exemple pour les liens de pagination : on garde tous les
// filtres du visiteur et on change seulement la page.
//
// Exemple :
//     url_catalogue(['couleur' => 'NOIR', 'page' => 1], ['page' => 2])
//     renvoie "boutique.php?couleur=NOIR&page=2"
// ---------------------------------------------------------------------
function url_catalogue(array $filtresActuels, array $remplacements = []): string
{
    // array_merge() : fusionne les deux tableaux ; en cas de meme cle,
    // c'est la valeur de $remplacements qui gagne.
    // array_filter() : retire les filtres vides pour garder une URL courte
    // (pas de "&couleur=&promo=0" inutiles).
    // fn ($valeur) => ... : petite fonction ecrite sur une ligne, qui
    // renvoie vrai pour les valeurs a garder.
    $parametres = array_filter(
        array_merge($filtresActuels, $remplacements),
        fn ($valeur) => $valeur !== null && $valeur !== '' && $valeur !== 0 && $valeur !== []
    );

    // Les tailles sont un tableau ['S', 'M'] : on le remet en texte "S,M",
    // le meme format que celui qu'utilise le JavaScript.
    // implode(',', ...) fait l'inverse de explode : ['S', 'M'] -> "S,M".
    foreach ($parametres as $cle => $valeur) {
        if (is_array($valeur)) {
            $parametres[$cle] = implode(',', $valeur);
        }
    }

    // http_build_query() : transforme le tableau en texte d'URL
    // ['couleur' => 'NOIR', 'page' => 2] -> "couleur=NOIR&page=2".
    // Aucun filtre : on renvoie simplement "boutique.php".
    return $parametres ? 'boutique.php?' . http_build_query($parametres) : 'boutique.php';
}
