<?php
// =====================================================================
// POMODORO STORE - Fonctions couleurs
//
// En base, une couleur est stockee sous forme de code en majuscules
// (VAR_COULEUR, IMG_COULEUR) : "NOIR", "BLEU"...
// Ces deux fonctions transforment ce code en quelque chose d'affichable :
//  - couleur_libelle() : le nom lisible ("NOIR" -> "Noir")
//  - couleur_hex()     : la couleur CSS de la pastille ("NOIR" -> "#171512")
//
// Tout est regroupe ici pour que l'admin et la boutique affichent
// exactement les memes noms et les memes couleurs.
// =====================================================================


// Renvoie le nom lisible d'une couleur.
// Exemple : couleur_libelle("BEIGE") renvoie "Beige".
//
// "string $code" : le parametre doit etre une chaine de caracteres.
// ": string"     : la fonction renvoie aussi une chaine.
function couleur_libelle(string $code): string
{
    // Tableau associatif : a gauche le code stocke en base,
    // a droite le texte affiche au visiteur.
    $libelles = [
        'NOIR'  => 'Noir',
        'BLANC' => 'Blanc',
        'ROUGE' => 'Rouge',
        'BLEU'  => 'Bleu',
        'VERT'  => 'Vert',
        'BEIGE' => 'Beige',
        'GRIS'  => 'Gris',
        'AUTRE' => 'Autres',
    ];

    // ?? : si le code n'existe pas dans le tableau, on renvoie le code
    // tel quel plutot que de provoquer une erreur.
    return $libelles[$code] ?? $code;
}


// Renvoie le code couleur hexadecimal utilise en CSS pour dessiner
// la pastille de couleur.
// Exemple : couleur_hex("ROUGE") renvoie "#B3271D".
function couleur_hex(string $code): string
{
    // Meme principe : code en base => couleur CSS.
    $hex = [
        'NOIR'  => '#171512',
        'BLANC' => '#FFFFFF',
        'ROUGE' => '#B3271D',
        'BLEU'  => '#2A4D8F',
        'VERT'  => '#3F6B4A',
        'BEIGE' => '#D8C6A6',
        'GRIS'  => '#8C8C8C',
        'AUTRE' => '#9B8CA8',
    ];

    // Code inconnu : on renvoie un gris clair neutre par defaut.
    return $hex[$code] ?? '#CCCCCC';
}
