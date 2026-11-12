<?php
// =====================================================================
// POMODORO STORE - Classe Panier
//
// Correspond aux tables POM_PANIER et POM_LIGNE_PANIER.
// Comme les autres classes, elle porte ses propres requetes SQL
// (patron Active Record).
//
// Regles :
//  - un client a au maximum UN panier (contrainte UQ_PAN_CLI) ;
//  - un panier appartient toujours a un client : pas de panier visiteur ;
//  - une variante n'apparait qu'une fois dans un panier (cle composite
//    LPA_PAN_ID + LPA_VAR_ID) : si on la rajoute, c'est la quantite
//    qui augmente ;
//  - retirer un article ou vider le panier est une suppression
//    LOGIQUE : la ligne garde sa date de suppression (LPA_DATE_SUPPRESSION)
//    et n'est plus lue. Toutes les lectures filtrent donc "IS NULL".
// =====================================================================

class Panier
{
    // Un article n'est lu dans le panier que si son produit est encore en
    // vente : produit actif ET rayon actif (meme regle que la boutique,
    // Produit::CATEGORIE_ACTIVE). Un produit desactive par l'administrateur
    // apres l'ajout au panier disparait donc du panier, du total et de la
    // commande : il ne peut plus etre achete. Suppose l'alias "p".
    const PRODUIT_EN_VENTE = 'p.PRO_ACTIF = 1 AND p.PRO_CAT_ID IN (SELECT CAT_ID FROM POM_CATEGORIE WHERE CAT_ACTIF = 1)';

    // Renvoie l'identifiant du panier du client.
    // Si le client n'a pas encore de panier, il est cree a ce moment-la.
    public static function idPourClient($pdo, $clientId)
    {
        // 1. Le client a-t-il deja un panier ?
        $requete = $pdo->prepare("SELECT PAN_ID FROM POM_PANIER WHERE PAN_CLI_ID = :client");
        $requete->execute([":client" => $clientId]);
        $ligne = $requete->fetch();

        if ($ligne !== false) {
            return $ligne["PAN_ID"];
        }

        // 2. Non : on lui en cree un. Les deux dates se remplissent
        // toutes seules (valeur par defaut CURRENT_TIMESTAMP).
        $requete = $pdo->prepare("INSERT INTO POM_PANIER (PAN_CLI_ID) VALUES (:client)");
        $requete->execute([":client" => $clientId]);

        // lastInsertId() : l'identifiant que la base vient de generer.
        return $pdo->lastInsertId();
    }

    // Renvoie la quantite d'une variante deja presente dans le panier,
    // ou 0 si elle n'y est pas.
    // Sert a verifier le stock : si le panier contient deja 2 pieces et
    // qu'il en reste 3, on ne peut en ajouter qu'une seule de plus.
    public static function quantiteVariante($pdo, $panierId, $varianteId)
    {
        $requete = $pdo->prepare(
            "SELECT LPA_QUANTITE FROM POM_LIGNE_PANIER
             WHERE LPA_PAN_ID = :panier AND LPA_VAR_ID = :variante AND LPA_DATE_SUPPRESSION IS NULL"
        );
        $requete->execute([":panier" => $panierId, ":variante" => $varianteId]);
        $ligne = $requete->fetch();

        if ($ligne === false) {
            return 0;
        }

        // (int) : la base renvoie "2" sous forme de texte, on veut le nombre 2.
        return (int) $ligne["LPA_QUANTITE"];
    }

    // Ajoute une variante au panier.
    //  - deja presente : on augmente sa quantite ;
    //  - absente       : on cree une nouvelle ligne.
    // La verification du stock n'est PAS faite ici : c'est le role de la
    // page qui appelle cette methode (segment 2).
    public static function ajouterVariante($pdo, $panierId, $varianteId, $quantite)
    {
        $dejaPresente = self::quantiteVariante($pdo, $panierId, $varianteId);

        if ($dejaPresente > 0) {
            $requete = $pdo->prepare(
                "UPDATE POM_LIGNE_PANIER SET LPA_QUANTITE = :quantite
                 WHERE LPA_PAN_ID = :panier AND LPA_VAR_ID = :variante AND LPA_DATE_SUPPRESSION IS NULL"
            );
            $requete->execute([
                ":quantite" => $dejaPresente + $quantite,
                ":panier"   => $panierId,
                ":variante" => $varianteId
            ]);
        } else {
            // La variante a peut-etre deja ete dans ce panier, puis retiree :
            // sa ligne existe encore (suppression logique) et la cle
            // composite refuserait un second INSERT. ON DUPLICATE KEY UPDATE
            // reutilise alors cette ligne comme si elle etait neuve.
            $requete = $pdo->prepare(
                "INSERT INTO POM_LIGNE_PANIER (LPA_PAN_ID, LPA_VAR_ID, LPA_QUANTITE)
                 VALUES (:panier, :variante, :quantite)
                 ON DUPLICATE KEY UPDATE LPA_QUANTITE = VALUES(LPA_QUANTITE),
                                         LPA_DATE_AJOUT = NOW(), LPA_DATE_SUPPRESSION = NULL"
            );
            $requete->execute([
                ":panier"   => $panierId,
                ":variante" => $varianteId,
                ":quantite" => $quantite
            ]);
        }
    }

    // Renvoie toutes les lignes du panier du client, pretes a afficher.
    // Chaque ligne est un tableau avec : variante, produit, taille,
    // couleur, stock, prix unitaire, quantite, photo principale.
    //
    // Le prix unitaire est le prix promo s'il existe, sinon le prix
    // normal : COALESCE(a, b) prend a, ou b si a est vide (NULL).
    //
    // LEFT JOIN sur les photos : un produit sans photo principale reste
    // quand meme dans la liste (IMG_CHEMIN vaut alors NULL).
    public static function lignesPourClient($pdo, $clientId)
    {
        $requete = $pdo->prepare(
            "SELECT l.LPA_VAR_ID, l.LPA_QUANTITE,
                    v.VAR_TAILLE, v.VAR_COULEUR, v.VAR_STOCK,
                    p.PRO_ID, p.PRO_NOM,
                    COALESCE(p.PRO_PRIX_PROMO, p.PRO_PRIX) AS PRIX_UNITAIRE,
                    img.IMG_CHEMIN
             FROM POM_PANIER pa
             JOIN POM_LIGNE_PANIER l ON l.LPA_PAN_ID = pa.PAN_ID
             JOIN POM_VARIANTE v     ON v.VAR_ID = l.LPA_VAR_ID
             JOIN POM_PRODUIT p      ON p.PRO_ID = v.VAR_PRO_ID
             LEFT JOIN POM_IMAGEPRODUITS img ON img.IMG_PRO_ID = p.PRO_ID AND img.IMG_EST_PRINCIPAL = 1
             WHERE pa.PAN_CLI_ID = :client
               AND l.LPA_DATE_SUPPRESSION IS NULL
               AND v.VAR_DATE_SUPPRESSION IS NULL
               AND " . self::PRODUIT_EN_VENTE . "
             ORDER BY l.LPA_DATE_AJOUT"
        );
        $requete->execute([":client" => $clientId]);

        // fetchAll() : toutes les lignes d'un coup, dans un tableau.
        return $requete->fetchAll(PDO::FETCH_ASSOC);
    }

    // Remplace la quantite d'une ligne (ex. le client passe de 1 a 3).
    // Le controle du stock est fait par la page qui appelle (segment 3).
    public static function modifierQuantite($pdo, $panierId, $varianteId, $quantite)
    {
        $requete = $pdo->prepare(
            "UPDATE POM_LIGNE_PANIER SET LPA_QUANTITE = :quantite
             WHERE LPA_PAN_ID = :panier AND LPA_VAR_ID = :variante AND LPA_DATE_SUPPRESSION IS NULL"
        );
        $requete->execute([
            ":quantite" => $quantite,
            ":panier"   => $panierId,
            ":variante" => $varianteId
        ]);
    }

    // Retire une variante du panier (suppression logique).
    public static function supprimerLigne($pdo, $panierId, $varianteId)
    {
        $requete = $pdo->prepare(
            "UPDATE POM_LIGNE_PANIER SET LPA_DATE_SUPPRESSION = NOW()
             WHERE LPA_PAN_ID = :panier AND LPA_VAR_ID = :variante AND LPA_DATE_SUPPRESSION IS NULL"
        );
        $requete->execute([":panier" => $panierId, ":variante" => $varianteId]);
    }

    // Vide tout le panier (suppression logique de toutes ses lignes).
    // Appelee par Commande::passer() une fois la commande enregistree.
    public static function vider($pdo, $panierId)
    {
        $requete = $pdo->prepare(
            "UPDATE POM_LIGNE_PANIER SET LPA_DATE_SUPPRESSION = NOW()
             WHERE LPA_PAN_ID = :panier AND LPA_DATE_SUPPRESSION IS NULL"
        );
        $requete->execute([":panier" => $panierId]);
    }

    // Nombre total d'articles dans le panier du client, pour le compteur
    // de l'en-tete. 2 t-shirts + 1 pull = 3.
    // Pas besoin de creer le panier ici : sans panier, on renvoie 0.
    public static function nombreArticles($pdo, $clientId)
    {
        // SUM additionne les quantites de toutes les lignes.
        // COALESCE(..., 0) : si aucune ligne, SUM renvoie NULL -> on met 0.
        $requete = $pdo->prepare(
            "SELECT COALESCE(SUM(l.LPA_QUANTITE), 0) AS nombre
             FROM POM_PANIER pa
             JOIN POM_LIGNE_PANIER l ON l.LPA_PAN_ID = pa.PAN_ID
             JOIN POM_VARIANTE v     ON v.VAR_ID = l.LPA_VAR_ID
             JOIN POM_PRODUIT p      ON p.PRO_ID = v.VAR_PRO_ID
             WHERE pa.PAN_CLI_ID = :client
               AND l.LPA_DATE_SUPPRESSION IS NULL
               AND v.VAR_DATE_SUPPRESSION IS NULL
               AND " . self::PRODUIT_EN_VENTE
        );
        $requete->execute([":client" => $clientId]);

        return (int) $requete->fetch()["nombre"];
    }
}
