<?php
// =====================================================================
// POMODORO STORE - Modele de configuration Brevo (emails)
//
// Copier ce fichier en "config-brevo.php" dans le meme dossier, puis le
// remplir :
//     cp back-end/config-brevo.exemple.php back-end/config-brevo.php
//
// Ce fichier n'est pas versionne : la cle API permet d'envoyer des
// emails au nom du site, elle n'a rien a faire dans le depot.
//
// Sans config-brevo.php (ou avec une cle vide), le site fonctionne
// quand meme : les comptes sont crees directement confirmes et la
// newsletter affiche "pas encore disponible". Pratique pour un poste
// qui n'a pas de compte Brevo.
// =====================================================================

return [
    // Brevo > SMTP et API > Cles API.
    'cle_api' => '',

    // Adresse validee dans Brevo > Expediteurs. Les emails partent "de" elle.
    'expediteur_email' => '',
    'expediteur_nom'   => 'Pomodoro Store',

    // Boite qui recoit les notifications de la boutique (nouveau message
    // du formulaire de contact). Vide = l'adresse expediteur ci-dessus.
    'email_notifications' => '',

    // Adresse a laquelle on ouvre le site, SANS / final. Sert a construire
    // les liens des emails. Volontairement ecrite ici plutot que devinee a
    // partir de la requete : l'en-tete Host peut etre falsifie, et le lien
    // de confirmation pointerait alors vers le site d'un attaquant.
    'url_site' => 'http://localhost:8000',

    // Newsletter : ID de la liste Brevo (Contacts > Listes) et ID du
    // modele d'email de confirmation "double opt-in". 0 = newsletter
    // desactivee.
    'liste_newsletter'    => 0,
    'modele_double_optin' => 0,

    // Fichier de certificats racine pour les requetes HTTPS vers Brevo.
    // Sous WAMP, PHP n'en a aucun par defaut (erreur cURL 60 "unable to
    // get local issuer certificate"). Git pour Windows en fournit un :
    //     C:/Program Files/Git/mingw64/ssl/certs/ca-bundle.crt
    // Sinon, telecharger https://curl.se/ca/cacert.pem et indiquer son
    // chemin. '' = certificats du systeme (Linux, macOS).
    'certificats' => '',
];
