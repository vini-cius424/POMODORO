<?php
// Cree un compte administrateur. A lancer une seule fois en ligne de
// commande (jamais accessible depuis le navigateur) :
//   php back-end/creer-admin.php

if (php_sapi_name() !== 'cli') {
    exit("Ce script ne s'execute qu'en ligne de commande.\n");
}

require __DIR__ . '/connexion-base-de-donnees.php'; // fournit $pdo

function demanderSaisie($label)
{
    fwrite(STDOUT, $label);
    return trim(fgets(STDIN));
}

$nom = demanderSaisie('Nom : ');
$prenom = demanderSaisie('Prenom : ');
$email = demanderSaisie('Email : ');
$motDePasse = demanderSaisie('Mot de passe (visible dans ce terminal) : ');

if (strlen($motDePasse) < 8) {
    exit("Le mot de passe doit faire au moins 8 caracteres.\n");
}

// On ne stocke jamais le mot de passe tel quel, seulement son hash.
$hash = password_hash($motDePasse, PASSWORD_DEFAULT);

$requete = $pdo->prepare(
    'INSERT INTO POM_ADMINISTRATEUR (ADM_NOM, ADM_PRENOM, ADM_EMAIL, ADM_MOT_DE_PASSE_HASH)
     VALUES (?, ?, ?, ?)'
);
$requete->execute([$nom, $prenom, $email, $hash]);

echo "Compte admin cree pour $email.\n";
