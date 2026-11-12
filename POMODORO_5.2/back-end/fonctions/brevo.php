<?php
// =====================================================================
// POMODORO STORE - Envoi d'emails avec Brevo
//
// Deux usages :
//   1. l'email de confirmation d'adresse envoye a l'inscription ;
//   2. l'inscription a la newsletter, en "double opt-in" : Brevo envoie
//      lui-meme un email "confirmez votre inscription", et la personne
//      n'entre dans la liste qu'apres avoir clique.
//
// On appelle directement l'API REST de Brevo avec cURL : pas besoin de
// Composer ni de librairie. La cle API reste cote serveur, elle n'est
// jamais envoyee au navigateur.
//
// Sources consultees :
//  - API Brevo : https://developers.brevo.com/reference
//  - cURL en PHP : https://www.php.net/manual/fr/book.curl.php
// =====================================================================

require_once __DIR__ . '/../classes/Client.php';
require_once __DIR__ . '/../classes/Commande.php'; // code promo de bienvenue
require_once __DIR__ . '/env.php';

// Lit les reglages Brevo dans le .env une seule fois (static : la valeur
// est gardee entre deux appels). Voir .env.exemple pour chaque reglage.
function brevo_config()
{
    static $config = null;

    if ($config === null) {
        $config = [
            // Une cle collee avec un espace ou un retour a la ligne est
            // refusee par Brevo : on retire ces caracteres, qui n'en font
            // jamais partie.
            'cle_api'             => preg_replace('/\s+/', '', env('BREVO_CLE_API')),
            'expediteur_email'    => env('BREVO_EXPEDITEUR_EMAIL'),
            'expediteur_nom'      => env('BREVO_EXPEDITEUR_NOM', 'Pomodoro Store'),
            'email_notifications' => env('BREVO_EMAIL_NOTIFICATIONS'),
            'liste_newsletter'    => (int) env('BREVO_LISTE_NEWSLETTER', '0'),
            'modele_double_optin' => (int) env('BREVO_MODELE_DOUBLE_OPTIN', '0'),
            'url_site'            => rtrim(env('URL_SITE', 'http://localhost:8000'), '/'),
            'certificats'         => env('CERTIFICATS'),
        ];
    }

    return $config;
}

// Brevo est-il utilisable sur ce poste ? Sans cle dans le .env, la
// confirmation d'email est desactivee (voir .env.exemple).
function brevo_est_configure()
{
    $config = brevo_config();

    return $config !== null && $config['cle_api'] !== '' && $config['expediteur_email'] !== '';
}

function brevo_newsletter_configuree()
{
    $config = brevo_config();

    return brevo_est_configure()
        && (int) $config['liste_newsletter'] > 0
        && (int) $config['modele_double_optin'] > 0;
}

// Envoie une requete a l'API Brevo : POST si $donnees est fourni, GET
// sinon. Renvoie ['code' => code HTTP, 'corps' => reponse texte].
// code 0 = Brevo injoignable (le detail va dans le journal d'erreurs).
function brevo_appel($chemin, $donnees = null)
{
    $config = brevo_config();

    $requete = curl_init('https://api.brevo.com/v3' . $chemin);
    curl_setopt_array($requete, [
        CURLOPT_HTTPHEADER     => [
            'api-key: ' . $config['cle_api'],
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true, // renvoie la reponse au lieu de l'afficher
        CURLOPT_TIMEOUT        => 10,   // secondes : la page ne reste pas bloquee
    ]);

    if ($donnees !== null) {
        curl_setopt($requete, CURLOPT_POST, true);
        curl_setopt($requete, CURLOPT_POSTFIELDS, json_encode($donnees));
    }

    if ($config['certificats'] !== '') {
        curl_setopt($requete, CURLOPT_CAINFO, $config['certificats']);
    }

    $reponse = curl_exec($requete);

    if ($reponse === false) {
        error_log('Brevo ' . $chemin . ' : erreur cURL ' . curl_errno($requete) . ' - ' . curl_error($requete));
        return ['code' => 0, 'corps' => ''];
    }

    return ['code' => curl_getinfo($requete, CURLINFO_HTTP_CODE), 'corps' => $reponse];
}

// Envoie une requete POST a l'API Brevo. Renvoie true si Brevo repond
// par un code 2xx (succes). En cas d'echec, le detail est ecrit dans le
// journal d'erreurs de PHP (terminal de "php -S", ou logs de WAMP) : le
// visiteur, lui, ne voit qu'un message general.
function brevo_post($chemin, $donnees)
{
    $resultat = brevo_appel($chemin, $donnees);

    if ($resultat['code'] < 200 || $resultat['code'] >= 300) {
        if ($resultat['code'] !== 0) {
            error_log('Brevo ' . $chemin . ' : HTTP ' . $resultat['code'] . ' - ' . $resultat['corps']);
        }
        return false;
    }

    return true;
}

// --- 1. Confirmation de l'adresse email --------------------------------

// Cree un nouveau jeton pour le client et lui envoie le lien de
// confirmation. Renvoie true si Brevo a accepte l'email.
function brevo_envoyer_confirmation($pdo, $clientId, $prenom, $email)
{
    $config = brevo_config();

    $jeton = Client::nouveauJetonConfirmation($pdo, $clientId);

    // urlencode par securite, meme si le jeton n'est fait que de 0-9 a-f.
    $lien = $config['url_site'] . '/confirmer-email.php?jeton=' . urlencode($jeton);

    // htmlspecialchars : le prenom vient du visiteur, il ne doit pas
    // pouvoir glisser du HTML dans l'email.
    $prenomHtml = htmlspecialchars($prenom);
    $lienHtml = htmlspecialchars($lien);

    $html = <<<HTML
<div style="font-family: Arial, sans-serif; color: #221E19; max-width: 520px;">
    <h1 style="font-size: 22px;">Bienvenue chez Pomodoro, {$prenomHtml} !</h1>
    <p>Merci pour votre inscription. Pour activer votre compte, confirmez votre adresse email :</p>
    <p style="margin: 28px 0;">
        <a href="{$lienHtml}" style="background: #221E19; color: #FAF6EF; padding: 12px 22px;
           border-radius: 999px; text-decoration: none; font-weight: bold;">Confirmer mon adresse</a>
    </p>
    <p style="font-size: 13px; color: #756E61;">
        Ce lien est valable 24 heures. Si vous n'avez pas créé de compte chez Pomodoro,
        ignorez simplement cet email.
    </p>
</div>
HTML;

    // Version texte : lue par les messageries qui n'affichent pas le HTML,
    // et elle aide l'email a ne pas etre classe en spam.
    $texte = "Bienvenue chez Pomodoro, $prenom !\n\n"
           . "Pour activer votre compte, ouvrez ce lien (valable 24 heures) :\n$lien\n\n"
           . "Si vous n'avez pas créé de compte chez Pomodoro, ignorez cet email.";

    return brevo_post('/smtp/email', [
        'sender'      => ['name' => $config['expediteur_nom'], 'email' => $config['expediteur_email']],
        'to'          => [['email' => $email, 'name' => $prenom]],
        'subject'     => 'Confirmez votre adresse email - Pomodoro',
        'htmlContent' => $html,
        'textContent' => $texte,
    ]);
}

// Confirmation de commande : envoyee juste apres Commande::passer(), avec
// le recapitulatif complet (articles, remise, livraison, total). Les
// donnees sont relues en base : l'email montre exactement ce qui a ete
// enregistre. Renvoie true si Brevo a accepte l'email.
function brevo_envoyer_confirmation_commande($pdo, $clientId, $numero)
{
    // Charge ici seulement : couleurs.php n'est pas protege contre un
    // double chargement, et seul cet email en a besoin.
    require_once __DIR__ . '/couleurs.php';

    $config = brevo_config();
    $client = Client::trouver($pdo, $clientId);
    $commande = Commande::trouverPourClient($pdo, $numero, $clientId);

    if ($client === null || $commande === null) {
        return false;
    }

    $lignes = Commande::lignes($pdo, $commande['COM_ID']);

    $libellesLivraison = [
        'STANDARD'        => 'Livraison standard (3 à 5 jours)',
        'EXPRESS'         => 'Livraison express (24 h)',
        'RETRAIT_MAGASIN' => 'Retrait en magasin'
    ];

    // Le total enregistre a deja la remise deduite (RG3) : on la rajoute
    // pour retrouver le prix des articles.
    $remise = (float) $commande['COM_REMISE'];
    $totalArticles = $commande['COM_MONTANT_TOTAL'] - $commande['COM_FRAIS_PORT'] + $remise;
    $date = date('d.m.Y à H:i', strtotime($commande['COM_DATE']));
    $lien = $config['url_site'] . '/commande-confirmation.php?numero=' . urlencode($numero);

    // Tout ce qui vient de la base passe par htmlspecialchars() avant
    // d'entrer dans le HTML de l'email.
    $prenomHtml = htmlspecialchars($client->getPrenom());
    $numeroHtml = htmlspecialchars($numero);
    $lienHtml = htmlspecialchars($lien);
    $modeHtml = htmlspecialchars($libellesLivraison[$commande['COM_MODE_LIVRAISON']] ?? $commande['COM_MODE_LIVRAISON']);

    // Une ligne de tableau par article, et la meme chose en texte brut.
    $lignesHtml = '';
    $lignesTexte = '';
    foreach ($lignes as $ligne) {
        $details = couleur_libelle($ligne['VAR_COULEUR']) . ' · Taille ' . $ligne['VAR_TAILLE']
                 . ' · Quantité ' . (int) $ligne['LCO_QUANTITE'];
        $sousTotal = number_format((float) $ligne['LCO_SOUS_TOTAL'], 2) . ' CHF';

        $lignesHtml .= '<tr>'
            . '<td style="padding:12px 0;border-bottom:1px solid #DCD3C0;font-size:14px;">'
            . '<strong>' . htmlspecialchars($ligne['PRO_NOM']) . '</strong><br>'
            . '<span style="font-size:12px;color:#756E61;">' . htmlspecialchars($details) . '</span></td>'
            . '<td align="right" style="padding:12px 0;border-bottom:1px solid #DCD3C0;font-size:14px;white-space:nowrap;">'
            . $sousTotal . '</td></tr>';

        $lignesTexte .= '- ' . $ligne['PRO_NOM'] . ' (' . $details . ') : ' . $sousTotal . "\n";
    }

    $ligneRemiseHtml = '';
    $ligneRemiseTexte = '';
    if ($remise > 0) {
        $code = htmlspecialchars($commande['COM_CODE_PROMO']);
        $montantRemise = number_format($remise, 2);
        $ligneRemiseHtml = "<tr><td style=\"padding:4px 0;color:#5B7A4B;\">Réduction {$code}</td>"
                         . "<td align=\"right\" style=\"padding:4px 0;color:#5B7A4B;\">-{$montantRemise} CHF</td></tr>";
        $ligneRemiseTexte = "Réduction " . $commande['COM_CODE_PROMO'] . " : -$montantRemise CHF\n";
    }

    // Retrait en magasin : pas d'adresse de livraison a afficher.
    $adresseHtml = '';
    $adresseTexte = '';
    if ($commande['COM_MODE_LIVRAISON'] !== 'RETRAIT_MAGASIN') {
        $adresseHtml = '<br><span style="color:#756E61;">' . htmlspecialchars($commande['ADR_RUE']) . ', '
                     . htmlspecialchars($commande['ADR_NPA'] . ' ' . $commande['ADR_VILLE']) . '</span>';
        $adresseTexte = "\n" . $commande['ADR_RUE'] . ', ' . $commande['ADR_NPA'] . ' ' . $commande['ADR_VILLE'];
    }

    $articles = number_format($totalArticles, 2);
    $frais = number_format((float) $commande['COM_FRAIS_PORT'], 2);
    $total = number_format((float) $commande['COM_MONTANT_TOTAL'], 2);
    $tva = number_format(Commande::tvaComprise((float) $commande['COM_MONTANT_TOTAL']), 2);
    $tauxTva = Commande::libelleTauxTva();

    $html = <<<HTML
<div style="margin:0;padding:24px 12px;background-color:#EAE2D0;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;margin:0 auto;background-color:#FAF6EF;border-radius:6px;font-family:Arial,Helvetica,sans-serif;color:#221E19;">
  <tr><td align="center" style="padding:26px 24px 18px;font-family:Georgia,'Times New Roman',serif;font-style:italic;font-weight:bold;font-size:34px;">
    Pomodoro<span style="color:#D6402B;">.</span>
  </td></tr>
  <tr><td align="center" style="background-color:#221E19;padding:30px 32px;color:#FAF6EF;">
    <p style="margin:0 0 8px;font-size:12px;font-weight:bold;letter-spacing:3px;text-transform:uppercase;color:#E9968B;">Commande confirmée</p>
    <p style="margin:0;font-family:Georgia,'Times New Roman',serif;font-size:26px;">Merci pour votre commande, {$prenomHtml} !</p>
    <p style="margin:10px 0 0;font-size:14px;color:#C9C1B6;">Commande <strong style="color:#FAF6EF;">{$numeroHtml}</strong> du {$date}</p>
  </td></tr>
  <tr><td style="padding:26px 40px 6px;">
    <p style="margin:0 0 6px;font-size:12px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;color:#756E61;">Vos articles</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">{$lignesHtml}</table>
  </td></tr>
  <tr><td style="padding:10px 40px 0;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px;">
      <tr><td style="padding:4px 0;">Articles</td><td align="right" style="padding:4px 0;">{$articles} CHF</td></tr>
      {$ligneRemiseHtml}
      <tr><td style="padding:4px 0;">Livraison</td><td align="right" style="padding:4px 0;">{$frais} CHF</td></tr>
      <tr><td style="padding:10px 0 0;font-size:17px;font-weight:bold;border-top:2px solid #221E19;">Total</td>
          <td align="right" style="padding:10px 0 0;font-size:17px;font-weight:bold;border-top:2px solid #221E19;">{$total} CHF</td></tr>
      <tr><td style="padding:4px 0;font-size:12px;color:#756E61;">dont TVA {$tauxTva}</td>
          <td align="right" style="padding:4px 0;font-size:12px;color:#756E61;">{$tva} CHF</td></tr>
    </table>
  </td></tr>
  <tr><td style="padding:24px 40px 0;font-size:14px;line-height:1.6;">
    <p style="margin:0 0 4px;font-size:12px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;color:#756E61;">Livraison</p>
    {$modeHtml}{$adresseHtml}
  </td></tr>
  <tr><td align="center" style="padding:30px 40px 34px;">
    <a href="{$lienHtml}" style="display:inline-block;padding:14px 36px;background-color:#221E19;border-radius:999px;font-size:13px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:#FAF6EF;text-decoration:none;">Voir ma commande</a>
  </td></tr>
  <tr><td align="center" style="background-color:#221E19;padding:18px 40px;border-radius:0 0 6px 6px;font-size:11px;color:#9A9184;">
    &copy; Pomodoro Store &mdash; Vous recevez cet email suite à votre commande sur notre site.
  </td></tr>
</table>
</div>
HTML;

    $texte = "Merci pour votre commande, " . $client->getPrenom() . " !\n\n"
           . "Commande $numero du $date\n\n"
           . "Vos articles :\n$lignesTexte\n"
           . "Articles : $articles CHF\n"
           . $ligneRemiseTexte
           . "Livraison : $frais CHF\n"
           . "Total : $total CHF\n"
           . "dont TVA $tauxTva : $tva CHF\n\n"
           . "Livraison : " . ($libellesLivraison[$commande['COM_MODE_LIVRAISON']] ?? $commande['COM_MODE_LIVRAISON']) . "$adresseTexte\n\n"
           . "Voir ma commande : $lien";

    return brevo_post('/smtp/email', [
        'sender'      => ['name' => $config['expediteur_nom'], 'email' => $config['expediteur_email']],
        'to'          => [['email' => $client->getEmail(), 'name' => $client->getPrenom()]],
        'subject'     => "Confirmation de votre commande $numero - Pomodoro",
        'htmlContent' => $html,
        'textContent' => $texte,
    ]);
}

// Mot de passe oublie : envoie le lien pour choisir un nouveau mot de
// passe (valable 1 heure). Renvoie true si Brevo a accepte l'email.
function brevo_envoyer_reinitialisation($prenom, $email, $jeton)
{
    $config = brevo_config();

    $lien = $config['url_site'] . '/reinitialiser-mot-de-passe.php?jeton=' . urlencode($jeton);
    $prenomHtml = htmlspecialchars($prenom);
    $lienHtml = htmlspecialchars($lien);

    $html = <<<HTML
<div style="font-family: Arial, sans-serif; color: #221E19; max-width: 520px;">
    <h1 style="font-size: 22px;">Bonjour {$prenomHtml},</h1>
    <p>Vous avez demandé à changer le mot de passe de votre compte Pomodoro. Cliquez sur le bouton pour en choisir un nouveau :</p>
    <p style="margin: 28px 0;">
        <a href="{$lienHtml}" style="background: #221E19; color: #FAF6EF; padding: 12px 22px;
           border-radius: 999px; text-decoration: none; font-weight: bold;">Choisir un nouveau mot de passe</a>
    </p>
    <p style="font-size: 13px; color: #756E61;">
        Ce lien est valable 1 heure et ne sert qu'une fois. Si vous n'avez rien demandé,
        ignorez simplement cet email : votre mot de passe actuel reste valable.
    </p>
</div>
HTML;

    $texte = "Bonjour $prenom,\n\n"
           . "Pour choisir un nouveau mot de passe, ouvrez ce lien (valable 1 heure) :\n$lien\n\n"
           . "Si vous n'avez rien demandé, ignorez cet email : votre mot de passe actuel reste valable.";

    return brevo_post('/smtp/email', [
        'sender'      => ['name' => $config['expediteur_nom'], 'email' => $config['expediteur_email']],
        'to'          => [['email' => $email, 'name' => $prenom]],
        'subject'     => 'Réinitialisez votre mot de passe - Pomodoro',
        'htmlContent' => $html,
        'textContent' => $texte,
    ]);
}

// Email envoye juste apres la confirmation de l'adresse : le code de
// bienvenue (-20 % sur la premiere commande). Le code n'est affiche nulle
// part sur le site : il faut un compte confirme pour le recevoir.
// Renvoie true si Brevo a accepte l'email.
function brevo_envoyer_code_bienvenue($prenom, $email)
{
    $config = brevo_config();

    $code = Commande::CODE_PROMO_BIENVENUE;
    $pourcentage = (int) (Commande::TAUX_PROMO_BIENVENUE * 100);
    $prenomHtml = htmlspecialchars($prenom);
    $lienHtml = htmlspecialchars($config['url_site'] . '/boutique.php');

    $html = <<<HTML
<div style="margin:0;padding:24px 12px;background-color:#EAE2D0;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;margin:0 auto;background-color:#FAF6EF;border-radius:6px;">
  <tr><td align="center" style="padding:26px 24px 20px;font-family:Georgia,'Times New Roman',serif;font-style:italic;font-weight:bold;font-size:36px;color:#221E19;">
    Pomodoro<span style="color:#D6402B;">.</span>
  </td></tr>
  <tr><td align="center" style="background-color:#221E19;padding:42px 32px;">
    <p style="margin:0 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:bold;letter-spacing:3px;text-transform:uppercase;color:#E9968B;">Bienvenue {$prenomHtml} !</p>
    <p style="margin:0;font-family:Georgia,'Times New Roman',serif;font-size:72px;line-height:1;font-weight:bold;color:#FAF6EF;">-{$pourcentage}%</p>
    <p style="margin:14px 0 0;font-family:Georgia,'Times New Roman',serif;font-style:italic;font-size:22px;color:#FAF6EF;">sur votre première commande</p>
  </td></tr>
  <tr><td style="padding:34px 44px 6px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.7;color:#221E19;">
    Votre compte est activé. Pour fêter votre arrivée, profitez de <strong>{$pourcentage}&nbsp;% de réduction sur tous les articles</strong> de votre première commande.
  </td></tr>
  <tr><td align="center" style="padding:24px 44px 8px;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border:2px dashed #D6402B;border-radius:6px;background-color:#FFFFFF;">
      <tr><td align="center" style="padding:16px 38px;">
        <p style="margin:0 0 6px;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;color:#756E61;">Votre code</p>
        <p style="margin:0;font-family:'Courier New',Courier,monospace;font-size:32px;font-weight:bold;letter-spacing:6px;color:#D6402B;">{$code}</p>
      </td></tr>
    </table>
    <p style="margin:12px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#756E61;">À saisir dans « Code promo » au moment de passer commande.</p>
  </td></tr>
  <tr><td align="center" style="padding:24px 44px 36px;">
    <a href="{$lienHtml}" style="display:inline-block;padding:15px 40px;background-color:#D6402B;border-radius:999px;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:#FAF6EF;text-decoration:none;">Découvrir la boutique</a>
  </td></tr>
  <tr><td align="center" style="background-color:#EAE2D0;padding:16px 44px;font-family:Arial,Helvetica,sans-serif;font-size:11px;line-height:1.6;color:#756E61;">
    Code valable une seule fois, sur la première commande de votre compte, frais de port non compris.
  </td></tr>
  <tr><td align="center" style="background-color:#221E19;padding:20px 44px;border-radius:0 0 6px 6px;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#9A9184;">
    &copy; Pomodoro Store &mdash; Vous recevez cet email suite à la création de votre compte.
  </td></tr>
</table>
</div>
HTML;

    $texte = "Bienvenue $prenom !\n\n"
           . "Votre compte est activé. Profitez de $pourcentage % de réduction sur votre première commande avec le code :\n\n"
           . "    $code\n\n"
           . "À saisir dans « Code promo » au moment de passer commande : " . $config['url_site'] . "/boutique.php\n\n"
           . "Code valable une seule fois, sur la première commande de votre compte, frais de port non compris.";

    return brevo_post('/smtp/email', [
        'sender'      => ['name' => $config['expediteur_nom'], 'email' => $config['expediteur_email']],
        'to'          => [['email' => $email, 'name' => $prenom]],
        'subject'     => "Bienvenue ! Votre code -$pourcentage% : $code",
        'htmlContent' => $html,
        'textContent' => $texte,
    ]);
}

// Notification a la boutique : un client vient d'ecrire avec le
// formulaire de contact. Envoyee a BREVO_EMAIL_NOTIFICATIONS (.env,
// a defaut l'adresse expediteur). "replyTo" = l'email du client : cliquer
// sur "Repondre" dans la messagerie lui repond directement.
// Le message est deja enregistre en base avant l'appel : si l'email ne
// part pas, il reste lisible dans l'administration. Renvoie true si Brevo
// a accepte l'email.
function brevo_notifier_message_contact($messageId, $nom, $email, $libelleSujet, $numeroCommande, $message)
{
    $config = brevo_config();
    $destinataire = ($config['email_notifications'] ?? '') !== '' ? $config['email_notifications'] : $config['expediteur_email'];
    $lien = $config['url_site'] . '/administration/message-detail.php?id=' . (int) $messageId;

    // Tout ce qui vient du visiteur passe par htmlspecialchars() : un
    // message ne doit pas pouvoir glisser du HTML dans l'email.
    $nomHtml = htmlspecialchars($nom);
    $emailHtml = htmlspecialchars($email);
    $sujetHtml = htmlspecialchars($libelleSujet);
    $messageHtml = nl2br(htmlspecialchars($message));
    $lienHtml = htmlspecialchars($lien);
    $commandeHtml = $numeroCommande !== ''
        ? '<p style="margin:0 0 6px;"><strong>Commande :</strong> ' . htmlspecialchars($numeroCommande) . '</p>'
        : '';

    $html = <<<HTML
<div style="font-family: Arial, sans-serif; color: #221E19; max-width: 560px;">
    <p style="margin:0 0 4px;font-size:12px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;color:#D6402B;">Nouveau message</p>
    <h1 style="margin:0 0 18px;font-size:20px;">{$sujetHtml}</h1>
    <p style="margin:0 0 6px;"><strong>De :</strong> {$nomHtml} &lt;{$emailHtml}&gt;</p>
    {$commandeHtml}
    <div style="margin:18px 0;padding:16px 18px;background:#FAF6EF;border-left:3px solid #D6402B;line-height:1.6;">{$messageHtml}</div>
    <p style="margin:24px 0;">
        <a href="{$lienHtml}" style="background: #221E19; color: #FAF6EF; padding: 12px 22px;
           border-radius: 999px; text-decoration: none; font-weight: bold;">Voir dans l'administration</a>
    </p>
    <p style="font-size: 13px; color: #756E61;">
        Répondez simplement à cet email : votre réponse partira vers {$emailHtml}.
        Pensez ensuite à marquer le message comme traité dans l'administration.
    </p>
</div>
HTML;

    $texte = "Nouveau message de contact : $libelleSujet\n\n"
           . "De : $nom <$email>\n"
           . ($numeroCommande !== '' ? "Commande : $numeroCommande\n" : '')
           . "\n$message\n\n"
           . "Voir dans l'administration : $lien\n"
           . "Répondez à cet email pour répondre directement au client.";

    return brevo_post('/smtp/email', [
        'sender'      => ['name' => $config['expediteur_nom'], 'email' => $config['expediteur_email']],
        'to'          => [['email' => $destinataire, 'name' => $config['expediteur_nom']]],
        'replyTo'     => ['email' => $email, 'name' => $nom],
        'subject'     => "Nouveau message : $libelleSujet - $nom",
        'htmlContent' => $html,
        'textContent' => $texte,
    ]);
}

// Reponse de la boutique a un message de contact, ecrite dans
// l'administration (administration/message-repondre.php). Elle part au
// nom de Pomodoro Store ; "replyTo" = la boite de la boutique, pour que
// la reponse du client y arrive. Le message d'origine est cite en
// dessous. $message : la ligne de MessageContact::trouver().
// Renvoie true si Brevo a accepte l'email.
function brevo_repondre_message_contact($message, $objet, $reponse)
{
    $config = brevo_config();
    $boiteBoutique = ($config['email_notifications'] ?? '') !== '' ? $config['email_notifications'] : $config['expediteur_email'];
    $date = date('d.m.Y à H:i', strtotime($message['MSG_DATE']));

    $reponseHtml = nl2br(htmlspecialchars($reponse));
    $origineHtml = nl2br(htmlspecialchars($message['MSG_MESSAGE']));
    $dateHtml = htmlspecialchars($date);

    $html = <<<HTML
<div style="font-family: Arial, sans-serif; color: #221E19; max-width: 560px; line-height: 1.6;">
    <p style="margin:0 0 24px;">{$reponseHtml}</p>
    <div style="margin-top:28px;padding:14px 16px;border-left:3px solid #DCD3C0;color:#756E61;font-size:13px;">
        <p style="margin:0 0 6px;"><strong>Votre message du {$dateHtml} :</strong></p>
        <p style="margin:0;">{$origineHtml}</p>
    </div>
    <p style="margin-top:24px;font-size:12px;color:#756E61;">Pomodoro Store - répondez simplement à cet email pour nous écrire.</p>
</div>
HTML;

    $texte = $reponse
           . "\n\n----- Votre message du $date -----\n"
           . $message['MSG_MESSAGE'];

    return brevo_post('/smtp/email', [
        'sender'      => ['name' => $config['expediteur_nom'], 'email' => $config['expediteur_email']],
        'to'          => [['email' => $message['MSG_EMAIL'], 'name' => $message['MSG_NOM']]],
        'replyTo'     => ['email' => $boiteBoutique, 'name' => $config['expediteur_nom']],
        'subject'     => $objet,
        'htmlContent' => $html,
        'textContent' => $texte,
    ]);
}

// --- 2. Newsletter -----------------------------------------------------

// L'adresse est-elle deja abonnee (dans la liste newsletter, et pas
// desinscrite) ? Dans ce cas, Brevo refuse de renvoyer un email de
// confirmation : on prefere le dire clairement au visiteur.
function brevo_deja_abonne($email)
{
    $config = brevo_config();

    // rawurlencode : le "@" et un eventuel "+" doivent etre codes dans l'URL.
    $resultat = brevo_appel('/contacts/' . rawurlencode($email));

    // 404 : Brevo ne connait pas cette adresse, elle n'est donc pas abonnee.
    if ($resultat['code'] !== 200) {
        return false;
    }

    $contact = json_decode($resultat['corps'], true);

    return in_array((int) $config['liste_newsletter'], $contact['listIds'] ?? [], true)
        && empty($contact['emailBlacklisted']);
}

// Demande a Brevo d'envoyer l'email "confirmez votre inscription". La
// liste des abonnes est geree par Brevo (pas de table en base) : c'est
// aussi Brevo qui ajoute le lien de desinscription a chaque newsletter.
function brevo_inscrire_newsletter($email)
{
    $config = brevo_config();

    return brevo_post('/contacts/doubleOptinConfirmation', [
        'email'           => $email,
        'includeListIds'  => [(int) $config['liste_newsletter']],
        'templateId'      => (int) $config['modele_double_optin'],
        'redirectionUrl'  => $config['url_site'] . '/newsletter-confirmee.php',
    ]);
}
