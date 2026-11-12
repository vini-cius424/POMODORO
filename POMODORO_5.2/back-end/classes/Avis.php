<?php
// =====================================================================
// POMODORO STORE - Classe Avis
//
// Correspond a la table POM_AVIS. Un avis est le couple (client,
// produit) : la contrainte UQ_AVI_CLI_PRO impose qu'un client ne note
// qu'une seule fois chaque produit.
//
// AVI_VALIDE : un avis depose depuis la boutique est publie IMMEDIATEMENT
// (creer() force la valeur a 1). La colonne garde son utilite dans l'autre
// sens : un administrateur peut RETIRER un avis deplace ou injurieux sans
// le supprimer, en le repassant a 0. C'est de la moderation a posteriori.
//
// La valeur par defaut de la colonne reste 0 cote base, pour qu'une ligne
// inseree directement en SQL n'apparaisse pas sans decision explicite.
//
// REGLE DE GESTION 8 - un avis ne devrait porter que sur un produit
// deja achete. Elle n'est pas applicable ici : le panier et la commande
// ne sont pas encore implantes, donc POM_LIGNE_COMMANDE est vide et
// aucun achat n'existe. Le controle est a ajouter en meme temps que la
// commande.
// =====================================================================

class Avis
{
    const NOTE_MIN = 1;
    const NOTE_MAX = 5;

    /**
     * Moyenne et nombre d'avis valides, pour un lot de produits (grille
     * catalogue). Cle du tableau retourne : PRO_ID. Un produit sans avis
     * n'a pas d'entree ici (a distinguer d'une moyenne de 0).
     */
    public static function resumePourProduits(PDO $pdo, array $produitIds)
    {
        if (!$produitIds) {
            return [];
        }

        $marqueurs = implode(',', array_fill(0, count($produitIds), '?'));
        $requete = $pdo->prepare(
            "SELECT AVI_PRO_ID, AVG(AVI_NOTE) AS moyenne, COUNT(*) AS nombre
             FROM POM_AVIS
             WHERE AVI_VALIDE = 1 AND AVI_DATE_SUPPRESSION IS NULL AND AVI_PRO_ID IN ($marqueurs)
             GROUP BY AVI_PRO_ID"
        );
        $requete->execute($produitIds);

        $resume = [];
        foreach ($requete->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
            $resume[(int) $ligne['AVI_PRO_ID']] = [
                'moyenne' => round((float) $ligne['moyenne'], 1),
                'nombre' => (int) $ligne['nombre'],
            ];
        }
        return $resume;
    }

    /**
     * Les avis publies d'un produit, du plus recent au plus ancien, avec
     * le prenom du client. AVI_CLI_ID est remonte pour que la fiche
     * produit puisse reconnaitre l'avis du visiteur connecte et l'annoncer
     * comme le sien.
     */
    public static function pourProduit(PDO $pdo, $produitId)
    {
        $requete = $pdo->prepare(
            'SELECT a.AVI_CLI_ID, a.AVI_NOTE, a.AVI_COMMENTAIRE, a.AVI_DATE, c.CLI_PRENOM
             FROM POM_AVIS a
             JOIN POM_CLIENT c ON c.CLI_ID = a.AVI_CLI_ID
             WHERE a.AVI_VALIDE = 1 AND a.AVI_DATE_SUPPRESSION IS NULL AND a.AVI_PRO_ID = ?
             ORDER BY a.AVI_DATE DESC'
        );
        $requete->execute([$produitId]);

        return $requete->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * L'avis qu'un client a deja depose sur un produit, VALIDE OU NON.
     * Sert a deux choses sur la fiche produit : empecher un second avis
     * (UQ_AVI_CLI_PRO le refuserait de toute facon) et montrer a l'auteur
     * son propre avis en attente, que les autres visiteurs ne voient pas.
     * Renvoie null si le client n'a pas encore note ce produit.
     */
    public static function pourClientEtProduit(PDO $pdo, $clientId, $produitId)
    {
        $requete = $pdo->prepare(
            'SELECT AVI_ID, AVI_NOTE, AVI_COMMENTAIRE, AVI_DATE, AVI_VALIDE
             FROM POM_AVIS
             WHERE AVI_CLI_ID = ? AND AVI_PRO_ID = ? AND AVI_DATE_SUPPRESSION IS NULL'
        );
        $requete->execute([$clientId, $produitId]);
        $ligne = $requete->fetch(PDO::FETCH_ASSOC);

        return $ligne !== false ? $ligne : null;
    }

    /**
     * Enregistre un nouvel avis, publie tout de suite : AVI_VALIDE est
     * fourni explicitement a 1, alors que la colonne vaut 0 par defaut.
     * AVI_DATE n'est pas fournie, la base pose la date courante.
     * Un commentaire vide devient NULL, comme partout ailleurs dans le
     * projet : en base, NULL veut dire "non renseigne".
     *
     * Si le client avait un avis sur ce produit qu'un administrateur a
     * supprime (suppression logique), la ligne existe encore et
     * UQ_AVI_CLI_PRO refuserait l'INSERT : ON DUPLICATE KEY UPDATE
     * reutilise alors cette ligne, remplie avec le nouvel avis.
     */
    public static function creer(PDO $pdo, $clientId, $produitId, $note, $commentaire)
    {
        $requete = $pdo->prepare(
            'INSERT INTO POM_AVIS (AVI_NOTE, AVI_COMMENTAIRE, AVI_VALIDE, AVI_CLI_ID, AVI_PRO_ID)
             VALUES (?, ?, 1, ?, ?)
             ON DUPLICATE KEY UPDATE AVI_NOTE = VALUES(AVI_NOTE), AVI_COMMENTAIRE = VALUES(AVI_COMMENTAIRE),
                                     AVI_VALIDE = 1, AVI_DATE = NOW(), AVI_DATE_SUPPRESSION = NULL'
        );
        $requete->execute([
            $note,
            trim($commentaire) !== '' ? trim($commentaire) : null,
            $clientId,
            $produitId,
        ]);

        return $pdo->lastInsertId();
    }

    /**
     * Tous les avis pour l'ecran de moderation, du plus recent au plus
     * ancien. Jointures pour afficher qui a note quoi.
     */
    public static function toutesPourAdmin(PDO $pdo)
    {
        $requete = $pdo->query(
            'SELECT a.AVI_ID, a.AVI_NOTE, a.AVI_COMMENTAIRE, a.AVI_DATE, a.AVI_VALIDE,
                    c.CLI_PRENOM, c.CLI_NOM, p.PRO_ID, p.PRO_NOM
             FROM POM_AVIS a
             JOIN POM_CLIENT c ON c.CLI_ID = a.AVI_CLI_ID
             JOIN POM_PRODUIT p ON p.PRO_ID = a.AVI_PRO_ID
             WHERE a.AVI_DATE_SUPPRESSION IS NULL
             ORDER BY a.AVI_DATE DESC'
        );

        return $requete->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Remet en ligne un avis qu'un administrateur avait retire. */
    public static function valider(PDO $pdo, $id)
    {
        $requete = $pdo->prepare('UPDATE POM_AVIS SET AVI_VALIDE = 1 WHERE AVI_ID = ?');
        $requete->execute([$id]);
    }

    /** Retire un avis de la boutique sans le supprimer. */
    public static function invalider(PDO $pdo, $id)
    {
        $requete = $pdo->prepare('UPDATE POM_AVIS SET AVI_VALIDE = 0 WHERE AVI_ID = ?');
        $requete->execute([$id]);
    }

    /** Suppression logique : l'avis reste en base avec sa date de suppression. */
    public static function supprimer(PDO $pdo, $id)
    {
        $requete = $pdo->prepare(
            'UPDATE POM_AVIS SET AVI_DATE_SUPPRESSION = NOW() WHERE AVI_ID = ? AND AVI_DATE_SUPPRESSION IS NULL'
        );
        $requete->execute([$id]);
    }
}
