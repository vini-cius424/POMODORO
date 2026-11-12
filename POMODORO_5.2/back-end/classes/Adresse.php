<?php
// =====================================================================
// POMODORO STORE - Classe Adresse
//
// Correspond a la table POM_ADRESSE. Un client peut enregistrer
// plusieurs adresses de livraison et en choisir une par defaut.
// Comme Commande et Categorie, la classe porte ses propres requetes
// SQL (patron Active Record) et travaille avec des tableaux associatifs
// plutot qu'avec des objets.
// =====================================================================

class Adresse
{
    // Toutes les adresses d'un client, l'adresse par defaut en premier.
    public static function listePourClient($pdo, $clientId)
    {
        $requete = $pdo->prepare(
            "SELECT ADR_ID, ADR_RUE, ADR_NPA, ADR_VILLE, ADR_PAYS, ADR_PAR_DEFAUT
             FROM POM_ADRESSE
             WHERE ADR_CLI_ID = :client AND ADR_DATE_SUPPRESSION IS NULL
             ORDER BY ADR_PAR_DEFAUT DESC, ADR_ID DESC"
        );
        $requete->execute([":client" => $clientId]);

        return $requete->fetchAll(PDO::FETCH_ASSOC);
    }

    // Une adresse du client, pour pre-remplir le formulaire de modification.
    // Le client n'obtient que SES adresses : un autre id dans l'URL ne
    // renvoie rien (null).
    public static function trouverPourClient($pdo, $id, $clientId)
    {
        $requete = $pdo->prepare(
            "SELECT ADR_ID, ADR_RUE, ADR_NPA, ADR_VILLE, ADR_PAYS, ADR_PAR_DEFAUT
             FROM POM_ADRESSE
             WHERE ADR_ID = :id AND ADR_CLI_ID = :client AND ADR_DATE_SUPPRESSION IS NULL"
        );
        $requete->execute([":id" => $id, ":client" => $clientId]);
        $adresse = $requete->fetch(PDO::FETCH_ASSOC);

        return $adresse !== false ? $adresse : null;
    }

    // Ajoute une adresse. La toute premiere adresse du client devient
    // automatiquement l'adresse par defaut : sans ca, un client qui
    // n'a jamais clique sur "Definir par defaut" n'en aurait aucune.
    public static function ajouter($pdo, $clientId, $rue, $npa, $ville, $pays)
    {
        $premiere = count(self::listePourClient($pdo, $clientId)) === 0;

        $requete = $pdo->prepare(
            "INSERT INTO POM_ADRESSE (ADR_RUE, ADR_NPA, ADR_VILLE, ADR_PAYS, ADR_PAR_DEFAUT, ADR_CLI_ID)
             VALUES (:rue, :npa, :ville, :pays, :defaut, :client)"
        );
        $requete->execute([
            ":rue"     => $rue,
            ":npa"     => $npa,
            ":ville"   => $ville,
            ":pays"    => $pays,
            ":defaut"  => $premiere ? 1 : 0,
            ":client"  => $clientId
        ]);
    }

    // Modifie une adresse du client. La verification d'appartenance est
    // dans le WHERE : impossible de modifier l'adresse d'un autre client
    // en changeant juste l'id dans le formulaire.
    //
    // Une commande pointe sur son adresse (COM_ADR_ID) au lieu d'en garder
    // une copie. Si l'adresse a deja servi a une commande, la modifier sur
    // place changerait donc l'adresse de livraison des commandes passees.
    // Dans ce cas, on ne touche pas a l'ancienne ligne : elle est archivee
    // (suppression logique, comme supprimer()) et la nouvelle version est
    // creee a cote, avec le meme drapeau "par defaut". Les commandes
    // passees gardent l'ancienne adresse, les suivantes utiliseront la
    // nouvelle. Les deux ecritures doivent reussir ensemble.
    public static function modifier($pdo, $id, $clientId, $rue, $npa, $ville, $pays)
    {
        $adresse = self::trouverPourClient($pdo, $id, $clientId);
        if ($adresse === null) {
            return;
        }

        $requete = $pdo->prepare("SELECT COUNT(*) FROM POM_COMMANDE WHERE COM_ADR_ID = :id");
        $requete->execute([":id" => $id]);
        $dejaUtilisee = (int) $requete->fetchColumn() > 0;

        if (!$dejaUtilisee) {
            // Aucune commande ne la lit : on peut la modifier sur place.
            $requete = $pdo->prepare(
                "UPDATE POM_ADRESSE
                 SET ADR_RUE = :rue, ADR_NPA = :npa, ADR_VILLE = :ville, ADR_PAYS = :pays
                 WHERE ADR_ID = :id AND ADR_CLI_ID = :client AND ADR_DATE_SUPPRESSION IS NULL"
            );
            $requete->execute([
                ":rue"     => $rue,
                ":npa"     => $npa,
                ":ville"   => $ville,
                ":pays"    => $pays,
                ":id"      => $id,
                ":client"  => $clientId
            ]);
            return;
        }

        $pdo->beginTransaction();

        try {
            // 1. L'ancienne version est archivee, telle quelle.
            self::supprimer($pdo, $id, $clientId);

            // 2. La nouvelle version prend sa place dans "Mes adresses".
            $requete = $pdo->prepare(
                "INSERT INTO POM_ADRESSE (ADR_RUE, ADR_NPA, ADR_VILLE, ADR_PAYS, ADR_PAR_DEFAUT, ADR_CLI_ID)
                 VALUES (:rue, :npa, :ville, :pays, :defaut, :client)"
            );
            $requete->execute([
                ":rue"     => $rue,
                ":npa"     => $npa,
                ":ville"   => $ville,
                ":pays"    => $pays,
                ":defaut"  => $adresse["ADR_PAR_DEFAUT"],
                ":client"  => $clientId
            ]);

            $pdo->commit();
        } catch (Exception $erreur) {
            $pdo->rollBack();
            throw $erreur;
        }
    }

    // Supprime une adresse du client (suppression logique) : la ligne
    // reste en base avec sa date de suppression, elle disparait seulement
    // de "Mes adresses". Les commandes deja passees qui pointent dessus
    // gardent donc leur adresse de livraison.
    // Elle perd aussi le drapeau "par defaut" : une adresse supprimee ne
    // doit plus etre proposee au moment de commander.
    public static function supprimer($pdo, $id, $clientId)
    {
        $requete = $pdo->prepare(
            "UPDATE POM_ADRESSE SET ADR_DATE_SUPPRESSION = NOW(), ADR_PAR_DEFAUT = 0
             WHERE ADR_ID = :id AND ADR_CLI_ID = :client AND ADR_DATE_SUPPRESSION IS NULL"
        );
        $requete->execute([":id" => $id, ":client" => $clientId]);
    }

    // Definit une adresse comme adresse par defaut : on retire d'abord
    // le drapeau a toutes les adresses du client, puis on le pose sur
    // celle-ci. Les deux ecritures doivent reussir ensemble.
    public static function definirParDefaut($pdo, $id, $clientId)
    {
        $pdo->beginTransaction();

        $requete = $pdo->prepare("UPDATE POM_ADRESSE SET ADR_PAR_DEFAUT = 0 WHERE ADR_CLI_ID = :client");
        $requete->execute([":client" => $clientId]);

        $requete = $pdo->prepare(
            "UPDATE POM_ADRESSE SET ADR_PAR_DEFAUT = 1
             WHERE ADR_ID = :id AND ADR_CLI_ID = :client AND ADR_DATE_SUPPRESSION IS NULL"
        );
        $requete->execute([":id" => $id, ":client" => $clientId]);

        $pdo->commit();
    }
}
