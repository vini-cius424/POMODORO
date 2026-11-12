<?php
// =====================================================================
// POMODORO STORE - Modele de configuration Stripe (paiement)
//
// Copier ce fichier en "config-stripe.php" dans le meme dossier, puis le
// remplir :
//     cp back-end/config-stripe.exemple.php back-end/config-stripe.php
//
// Ce fichier n'est pas versionne : la cle secrete permet d'encaisser
// et de rembourser au nom du site, elle n'a rien a faire dans le depot.
//
// Sans config-stripe.php (ou avec une cle vide), le site fonctionne
// comme avant : la commande est enregistree sans paiement.
// =====================================================================

return [
    // Stripe > Developpeurs > Cles API, en MODE TEST : commence par
    // "sk_test_". Ne jamais mettre une cle "sk_live_" (vrai argent).
    'cle_secrete' => '',

    // Monnaie des paiements, en minuscules (code ISO) : franc suisse.
    'devise' => 'chf',

    // Adresse a laquelle on ouvre le site, SANS / final. Sert a construire
    // les adresses de retour apres le paiement (reussi ou annule).
    // Ecrite ici plutot que devinee a partir de la requete : l'en-tete
    // Host peut etre falsifie.
    'url_site' => 'http://localhost:8000',

    // Fichier de certificats racine pour les requetes HTTPS vers Stripe.
    // Sous WAMP, PHP n'en a aucun par defaut (erreur cURL 60). Git pour
    // Windows en fournit un :
    //     C:/Program Files/Git/mingw64/ssl/certs/ca-bundle.crt
    // '' = certificats du systeme (Linux, macOS).
    'certificats' => '',
];
