<?php

class Categorie
{
    public $id;
    public $nom;
    public $description;
    public $ordre;
    public $actif;

    private function __construct($id, $nom, $description, $ordre, $actif)
    {
        $this->id = $id;
        $this->nom = $nom;
        $this->description = $description;
        $this->ordre = $ordre;
        $this->actif = $actif;
    }

    private static function depuisLigne($ligne)
    {
        return new self($ligne['CAT_ID'], $ligne['CAT_NOM'], $ligne['CAT_DESCRIPTION'], $ligne['CAT_ORDRE'], $ligne['CAT_ACTIF']);
    }

    private static function liste(PDO $pdo, $sql)
    {
        $categories = [];
        foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
            $categories[] = self::depuisLigne($ligne);
        }
        return $categories;
    }

    /** Les categories actives seulement : menus et filtres de la boutique. */
    public static function toutes(PDO $pdo)
    {
        return self::liste($pdo,
            'SELECT CAT_ID, CAT_NOM, CAT_DESCRIPTION, CAT_ORDRE, CAT_ACTIF FROM POM_CATEGORIE
             WHERE CAT_ACTIF = 1 ORDER BY CAT_ORDRE, CAT_NOM'
        );
    }

    /** Toutes les categories, actives ou non : ecrans d'administration. */
    public static function toutesPourAdmin(PDO $pdo)
    {
        return self::liste($pdo,
            'SELECT CAT_ID, CAT_NOM, CAT_DESCRIPTION, CAT_ORDRE, CAT_ACTIF FROM POM_CATEGORIE
             ORDER BY CAT_ORDRE, CAT_NOM'
        );
    }

    public static function trouver(PDO $pdo, $id)
    {
        $requete = $pdo->prepare(
            'SELECT CAT_ID, CAT_NOM, CAT_DESCRIPTION, CAT_ORDRE, CAT_ACTIF FROM POM_CATEGORIE WHERE CAT_ID = ?'
        );
        $requete->execute([$id]);
        $ligne = $requete->fetch(PDO::FETCH_ASSOC);

        return $ligne ? self::depuisLigne($ligne) : null;
    }

    /** Un nom de categorie doit rester unique (contrainte UQ_CAT_NOM en base). */
    public static function nomDejaUtilise(PDO $pdo, $nom, $idAIgnorer = null)
    {
        if ($idAIgnorer !== null) {
            $requete = $pdo->prepare('SELECT CAT_ID FROM POM_CATEGORIE WHERE CAT_NOM = ? AND CAT_ID <> ?');
            $requete->execute([$nom, $idAIgnorer]);
        } else {
            $requete = $pdo->prepare('SELECT CAT_ID FROM POM_CATEGORIE WHERE CAT_NOM = ?');
            $requete->execute([$nom]);
        }

        return $requete->fetch() !== false;
    }

    public static function creer(PDO $pdo, $nom, $description, $ordre)
    {
        $requete = $pdo->prepare(
            'INSERT INTO POM_CATEGORIE (CAT_NOM, CAT_DESCRIPTION, CAT_ORDRE) VALUES (?, ?, ?)'
        );
        $requete->execute([$nom, $description !== '' ? $description : null, $ordre]);
    }

    public static function modifier(PDO $pdo, $id, $nom, $description, $ordre)
    {
        $requete = $pdo->prepare(
            'UPDATE POM_CATEGORIE SET CAT_NOM = ?, CAT_DESCRIPTION = ?, CAT_ORDRE = ? WHERE CAT_ID = ?'
        );
        $requete->execute([$nom, $description !== '' ? $description : null, $ordre, $id]);
    }

    /**
     * Bascule CAT_ACTIF : une categorie active devient inactive, et
     * inversement. Une categorie inactive disparait des menus de la
     * boutique, et ses produits ne sont plus vendus (voir Produit), sans
     * rien effacer : la reactiver remet tout en ligne.
     */
    public static function basculerActif(PDO $pdo, $id)
    {
        $requete = $pdo->prepare('UPDATE POM_CATEGORIE SET CAT_ACTIF = NOT CAT_ACTIF WHERE CAT_ID = ?');
        $requete->execute([$id]);
    }
}
