<?php
// =====================================================================
// POMODORO STORE - Icones de l'espace admin
//
// Les icones du menu admin (tableau de bord, produits, deconnexion...)
// sont de petits dessins SVG ecrits directement dans le code, sans
// librairie externe a telecharger.
//
// Utilisation dans une page :
//     echo icone_admin('produit');
// =====================================================================


// Renvoie le code HTML complet d'une icone : <svg> ... </svg>.
// $nom : le nom de l'icone voulue, par exemple 'photo' ou 'plus'.
//
// Pas besoin de htmlspecialchars() ici : le contenu est ecrit par nous
// et ne vient jamais d'un utilisateur, il n'y a donc aucun risque.
function icone_admin(string $nom): string
{
    // Balise d'ouverture commune a toutes les icones.
    //  - viewBox="0 0 24 24" : le dessin est fait sur une grille de 24 x 24
    //  - stroke="currentColor" : le trait prend la couleur du texte autour,
    //    donc l'icone change de couleur toute seule avec le CSS
    $depart = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">';
    $fin = '</svg>';

    // Pour chaque nom d'icone, les formes qui la dessinent :
    // <rect> = rectangle, <circle> = cercle, <path> = trait libre.
    $chemins = [
        'tableau-de-bord' => '<rect x="3" y="3" width="7" height="7" rx="1.3"/><rect x="14" y="3" width="7" height="7" rx="1.3"/><rect x="3" y="14" width="7" height="7" rx="1.3"/><rect x="14" y="14" width="7" height="7" rx="1.3"/>',
        'categorie'       => '<path d="M20 12.5 12.5 20a2 2 0 0 1-2.8 0L4 14.3a2 2 0 0 1 0-2.8L11.5 4H19a1 1 0 0 1 1 1v7.5Z"/><circle cx="15.3" cy="8.7" r="1.2"/>',
        'produit'         => '<path d="M3 8.5 12 4l9 4.5-9 4.5-9-4.5Z"/><path d="M3 8.5V16l9 4.5 9-4.5V8.5"/><path d="M12 13v7.5"/>',
        'boutique'        => '<path d="M14 4h6v6"/><path d="M20 4 10 14"/><path d="M18 13v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h6"/>',
        'deconnexion'     => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'menu'            => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'plus'            => '<path d="M12 5v14M5 12h14"/>',
        'ok'              => '<circle cx="12" cy="12" r="9"/><path d="m8.3 12.5 2.3 2.3L16 9.7"/>',
        'alerte'          => '<path d="M12 4 3 20h18L12 4Z"/><path d="M12 10v5"/><path d="M12 17.5v.01"/>',
        'pile'            => '<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 13 9 5 9-5"/>',
        'commande'        => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z"/><path d="M9 8h6M9 12h6"/>',
        'photo'           =>'<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5.5-5-9.5 8.5"/>',
        'profil'          => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.5 3-6 7-6s7 2.5 7 6"/>',
        'cadenas'         => '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'adresse'         => '<path d="M12 21s7-6.5 7-11.5a7 7 0 1 0-14 0C5 14.5 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'message'         => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6.5 8.5-6.5"/>',
        'graphique'       => '<path d="M4 20h16"/><path d="M7 16v-5"/><path d="M12 16V7"/><path d="M17 16v-8"/>',
        'argent'          => '<rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6.5 9.5v.01M17.5 14.5v.01"/>',
        'panier'          => '<path d="M5 8h14l-1.3 11a1 1 0 0 1-1 .9H7.3a1 1 0 0 1-1-.9L5 8Z"/><path d="M9 8V6.5a3 3 0 0 1 6 0V8"/>',
        'clients'         => '<circle cx="9" cy="8.5" r="3"/><path d="M3.5 19c0-3 2.5-5 5.5-5s5.5 2 5.5 5"/><path d="M15.5 5.8a3 3 0 0 1 0 5.4"/><path d="M17 14.3c2 .6 3.5 2.3 3.5 4.7"/>',
    ];

    // On colle les trois morceaux : ouverture + formes + fermeture.
    // Nom inconnu (?? '') : on renvoie un <svg> vide, donc rien ne
    // s'affiche mais la page ne plante pas.
    return $depart . ($chemins[$nom] ?? '') . $fin;
}
