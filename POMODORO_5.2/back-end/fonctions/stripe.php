<?php
// =====================================================================
// POMODORO STORE - Paiement avec Stripe Checkout
//
// Principe :
//   1. le site demande a Stripe une "session de paiement" pour une
//      commande (montant, monnaie, adresses de retour) ;
//   2. Stripe renvoie l'adresse d'une page de paiement hebergee chez
//      lui : le client y tape sa carte (jamais sur notre site) ;
//   3. apres le paiement, Stripe renvoie le client chez nous. On
//      redemande alors a Stripe si la session est vraiment payee :
//      on ne croit jamais une simple adresse de retour.
//
// Comme pour Brevo, on appelle directement l'API REST avec cURL : pas
// besoin de Composer ni de librairie.
//
// Sources consultees :
//  - Stripe Checkout : https://docs.stripe.com/api/checkout/sessions
//  - Cartes de test   : https://docs.stripe.com/testing
//  - cURL en PHP      : https://www.php.net/manual/fr/book.curl.php
// =====================================================================

require_once __DIR__ . '/env.php';

// Lit les reglages Stripe dans le .env une seule fois (static : la valeur
// est gardee entre deux appels). Voir .env.exemple pour chaque reglage.
function stripe_config()
{
    static $config = null;

    if ($config === null) {
        $config = [
            'cle_secrete' => preg_replace('/\s+/', '', env('STRIPE_CLE_SECRETE')),
            'devise'      => strtolower(env('STRIPE_DEVISE', 'chf')),
            'url_site'    => rtrim(env('URL_SITE', 'http://localhost:8000'), '/'),
            'certificats' => env('CERTIFICATS'),
        ];
    }

    return $config;
}

// Stripe est-il utilisable sur ce poste ? Sans cle, le site enregistre
// les commandes sans paiement, comme avant.
function stripe_est_configure()
{
    $config = stripe_config();

    return $config !== null && $config['cle_secrete'] !== '';
}

// Envoie une requete a l'API Stripe et renvoie la reponse sous forme de
// tableau PHP, ou null en cas d'echec (le detail part dans le journal
// d'erreurs : terminal de "php -S" ou logs de WAMP).
//  - $methode : 'GET' pour lire, 'POST' pour creer ;
//  - $chemin  : par exemple '/checkout/sessions' ;
//  - $donnees : les parametres a envoyer (POST uniquement).
function stripe_requete($methode, $chemin, $donnees = [])
{
    $config = stripe_config();

    $requete = curl_init('https://api.stripe.com/v1' . $chemin);
    curl_setopt_array($requete, [
        // La cle secrete sert d'identifiant ("Bearer" = porteur de la cle).
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $config['cle_secrete']],
        CURLOPT_RETURNTRANSFER => true, // renvoie la reponse au lieu de l'afficher
        CURLOPT_TIMEOUT        => 15,   // secondes : la page ne reste pas bloquee
    ]);

    if ($methode === 'POST') {
        // Stripe n'attend pas du JSON (contrairement a Brevo) mais un
        // formulaire classique. http_build_query transforme le tableau
        // en "mode=payment&line_items[0][quantity]=1&...".
        curl_setopt($requete, CURLOPT_POST, true);
        curl_setopt($requete, CURLOPT_POSTFIELDS, http_build_query($donnees));
    }

    if ($config['certificats'] !== '') {
        curl_setopt($requete, CURLOPT_CAINFO, $config['certificats']);
    }

    $reponse = curl_exec($requete);
    $code = curl_getinfo($requete, CURLINFO_HTTP_CODE);

    if ($reponse === false) {
        error_log('Stripe ' . $chemin . ' : erreur cURL ' . curl_errno($requete) . ' - ' . curl_error($requete));
        return null;
    }

    if ($code < 200 || $code >= 300) {
        error_log('Stripe ' . $chemin . ' : HTTP ' . $code . ' - ' . $reponse);
        return null;
    }

    // json_decode(..., true) : la reponse JSON devient un tableau PHP.
    return json_decode($reponse, true);
}

// Cree la session de paiement d'une commande.
// $commande : tableau avec COM_ID, COM_NUMERO et COM_MONTANT_TOTAL.
// Renvoie ['id' => 'cs_test_...', 'url' => 'https://checkout.stripe.com/...']
// ou null si Stripe a refuse.
function stripe_creer_session($commande, $emailClient)
{
    $config = stripe_config();

    // Stripe compte en centimes, en nombre entier : 51.00 CHF -> 5100.
    // round() avant (int) : 0.1 + 0.2 donne 0.30000000000000004 en PHP.
    $montantCentimes = (int) round($commande['COM_MONTANT_TOTAL'] * 100);

    $session = stripe_requete('POST', '/checkout/sessions', [
        'mode' => 'payment',

        // Une seule ligne avec le total de la commande : le total tient
        // deja compte des frais de port et du code promo, calcules par
        // Commande::passer(). Aucun montant ne vient du navigateur.
        'line_items' => [[
            'quantity'   => 1,
            'price_data' => [
                'currency'     => $config['devise'],
                'unit_amount'  => $montantCentimes,
                'product_data' => ['name' => 'Commande ' . $commande['COM_NUMERO'] . ' - Pomodoro Store'],
            ],
        ]],

        // Pre-remplit l'email du client sur la page de paiement.
        'customer_email' => $emailClient,

        // Pour retrouver la commande depuis le tableau de bord Stripe.
        'client_reference_id' => $commande['COM_ID'],
        'metadata'            => ['commande' => $commande['COM_NUMERO']],

        // Adresses de retour. {CHECKOUT_SESSION_ID} est remplace par
        // Stripe lui-meme par le numero de la session.
        'success_url' => $config['url_site'] . '/actions/paiement-retour.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'  => $config['url_site'] . '/actions/paiement-annule.php?commande=' . (int) $commande['COM_ID'],
    ]);

    if ($session === null || empty($session['url'])) {
        return null;
    }

    return ['id' => $session['id'], 'url' => $session['url']];
}

// Relit une session aupres de Stripe (GET), pour savoir si elle a
// vraiment ete payee : $session['payment_status'] vaut alors 'paid'.
// null si la session n'existe pas ou si Stripe ne repond pas.
function stripe_lire_session($sessionId)
{
    // rawurlencode : le numero de session est place dans l'adresse.
    return stripe_requete('GET', '/checkout/sessions/' . rawurlencode($sessionId));
}
