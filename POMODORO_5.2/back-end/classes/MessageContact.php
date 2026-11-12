<?php
// =====================================================================
// POMODORO STORE - Classe MessageContact
//
// Correspond a la table POM_MESSAGE_CONTACT : les messages envoyes par
// le formulaire de contact du site (front-end/contact.php) et lus dans
// l'administration (administration/messages.php).
// Comme Commande ou Adresse, la classe porte ses propres requetes SQL
// (patron Active Record) et travaille avec des tableaux associatifs.
//
// Cycle de vie d'un message (MSG_STATUT) :
//   NOUVEAU -> LU      automatiquement, quand l'administrateur l'ouvre ;
//   LU      -> TRAITE  quand il y a repondu (bouton "Marquer comme traite") ;
//   TRAITE  -> LU      s'il faut y revenir ("Remettre a traiter").
// Un message ne se supprime jamais : il reste dans l'historique.
// =====================================================================

class MessageContact
{
    // Sujets proposes dans le formulaire (contrainte CK_MSG_SUJET).
    // Doit rester synchronise avec le script SQL.
    const SUJETS = [
        'COMMANDE'  => 'Ma commande',
        'PRODUIT'   => 'Un produit',
        'LIVRAISON' => 'La livraison',
        'RETOUR'    => 'Un retour ou un échange',
        'AUTRE'     => 'Autre question',
    ];

    // Statuts (contrainte CK_MSG_STATUT) et libelle affiche.
    const STATUTS = [
        'NOUVEAU' => 'Nouveau',
        'LU'      => 'Lu',
        'TRAITE'  => 'Traité',
    ];

    // Longueurs acceptees, verifiees par contact.php avant l'insertion.
    const LONGUEUR_MESSAGE_MIN = 10;
    const LONGUEUR_MESSAGE_MAX = 5000;

    // Enregistre un nouveau message (statut NOUVEAU, date du jour : les
    // valeurs par defaut de la table). $clientId vaut null pour un
    // visiteur sans compte ; $numeroCommande vaut '' s'il n'est pas donne.
    // Renvoie l'identifiant du message cree (pour le lien de la notification).
    public static function creer($pdo, $nom, $email, $sujet, $numeroCommande, $message, $clientId)
    {
        $requete = $pdo->prepare(
            "INSERT INTO POM_MESSAGE_CONTACT (MSG_NOM, MSG_EMAIL, MSG_SUJET, MSG_NUMERO_COMMANDE, MSG_MESSAGE, MSG_CLI_ID)
             VALUES (:nom, :email, :sujet, :numero, :message, :client)"
        );
        $requete->execute([
            ":nom"     => $nom,
            ":email"   => $email,
            ":sujet"   => $sujet,
            ":numero"  => $numeroCommande !== '' ? $numeroCommande : null,
            ":message" => $message,
            ":client"  => $clientId
        ]);

        return (int) $pdo->lastInsertId();
    }

    // Tous les messages, du plus recent au plus ancien, pour la liste de
    // l'administration. $statut (facultatif) : ne garder que ce statut ;
    // un statut inconnu est ignore (liste blanche, comme les commandes).
    public static function listePourAdmin($pdo, $statut = null)
    {
        $sql = "SELECT MSG_ID, MSG_NOM, MSG_EMAIL, MSG_SUJET, MSG_MESSAGE, MSG_DATE, MSG_STATUT
                FROM POM_MESSAGE_CONTACT";
        $parametres = [];

        if ($statut !== null && array_key_exists($statut, self::STATUTS)) {
            $sql .= " WHERE MSG_STATUT = :statut";
            $parametres[":statut"] = $statut;
        }

        $sql .= " ORDER BY MSG_DATE DESC, MSG_ID DESC";

        $requete = $pdo->prepare($sql);
        $requete->execute($parametres);

        return $requete->fetchAll(PDO::FETCH_ASSOC);
    }

    // Nombre de messages par statut, pour les onglets de filtre :
    // ['NOUVEAU' => 2, 'LU' => 0, 'TRAITE' => 5]. Un statut sans message vaut 0.
    public static function nombreParStatut($pdo)
    {
        $nombres = array_fill_keys(array_keys(self::STATUTS), 0);

        $lignes = $pdo->query(
            "SELECT MSG_STATUT, COUNT(*) AS NOMBRE FROM POM_MESSAGE_CONTACT GROUP BY MSG_STATUT"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($lignes as $ligne) {
            $nombres[$ligne["MSG_STATUT"]] = (int) $ligne["NOMBRE"];
        }

        return $nombres;
    }

    // Nombre de messages pas encore ouverts : la pastille du menu admin.
    public static function nombreNouveaux($pdo)
    {
        return (int) $pdo->query(
            "SELECT COUNT(*) FROM POM_MESSAGE_CONTACT WHERE MSG_STATUT = 'NOUVEAU'"
        )->fetchColumn();
    }

    // Un message complet pour la page de detail, ou null s'il n'existe pas.
    // Si l'auteur etait connecte, on remonte aussi son compte client.
    public static function trouver($pdo, $id)
    {
        $requete = $pdo->prepare(
            "SELECT m.*, c.CLI_PRENOM, c.CLI_NOM, c.CLI_TELEPHONE
             FROM POM_MESSAGE_CONTACT m
             LEFT JOIN POM_CLIENT c ON c.CLI_ID = m.MSG_CLI_ID
             WHERE m.MSG_ID = :id"
        );
        $requete->execute([":id" => $id]);
        $message = $requete->fetch(PDO::FETCH_ASSOC);

        return $message !== false ? $message : null;
    }

    // Ouvrir un message nouveau le passe a LU. Un message deja lu ou
    // traite garde son statut : la condition est dans le WHERE.
    public static function marquerLu($pdo, $id)
    {
        $requete = $pdo->prepare(
            "UPDATE POM_MESSAGE_CONTACT SET MSG_STATUT = 'LU', MSG_DATE_MAJ = NOW()
             WHERE MSG_ID = :id AND MSG_STATUT = 'NOUVEAU'"
        );
        $requete->execute([":id" => $id]);
    }

    // Garde la reponse envoyee au client (la derniere) et classe le
    // message comme traite : y avoir repondu, c'est l'avoir traite.
    public static function enregistrerReponse($pdo, $id, $reponse)
    {
        $requete = $pdo->prepare(
            "UPDATE POM_MESSAGE_CONTACT
             SET MSG_REPONSE = :reponse, MSG_DATE_REPONSE = NOW(),
                 MSG_STATUT = 'TRAITE', MSG_DATE_MAJ = NOW()
             WHERE MSG_ID = :id"
        );
        $requete->execute([":reponse" => $reponse, ":id" => $id]);
    }

    // Change le statut (bouton de la page de detail). Renvoie false si le
    // statut est inconnu ou si le message n'existe pas.
    public static function changerStatut($pdo, $id, $statut)
    {
        if (!array_key_exists($statut, self::STATUTS)) {
            return false;
        }

        $requete = $pdo->prepare(
            "UPDATE POM_MESSAGE_CONTACT SET MSG_STATUT = :statut, MSG_DATE_MAJ = NOW() WHERE MSG_ID = :id"
        );
        $requete->execute([":statut" => $statut, ":id" => $id]);

        return $requete->rowCount() === 1;
    }
}
