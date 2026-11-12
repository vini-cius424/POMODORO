<?php

class Variante
{
    // Doivent rester synchronises avec CK_VAR_TAILLE / CK_VAR_COULEUR du script SQL.
    const TAILLES = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'UNIQUE'];
    const COULEURS = ['NOIR', 'BLANC', 'ROUGE', 'BLEU', 'VERT', 'BEIGE', 'GRIS', 'AUTRE'];

    // Marketing : la boutique n'affiche jamais le stock exact, sauf quand
    // il reste au plus ce nombre de pieces ("Plus que 2 articles !").
    const SEUIL_STOCK_FAIBLE = 3;

    public $id;
    public $sku;
    public $taille;
    public $couleur;
    public $stock;
    public $produitId;

    private function __construct($id, $sku, $taille, $couleur, $stock, $produitId)
    {
        $this->id = $id;
        $this->sku = $sku;
        $this->taille = $taille;
        $this->couleur = $couleur;
        $this->stock = $stock;
        $this->produitId = $produitId;
    }

    private static function depuisLigne($ligne)
    {
        return new self(
            $ligne['VAR_ID'], $ligne['VAR_SKU'], $ligne['VAR_TAILLE'],
            $ligne['VAR_COULEUR'], $ligne['VAR_STOCK'], $ligne['VAR_PRO_ID']
        );
    }

    /** Stock cumule et nombre de SKU en rupture, pour le tableau de bord. */
    public static function statistiquesStock(PDO $pdo)
    {
        $ligne = $pdo->query(
            'SELECT COALESCE(SUM(VAR_STOCK), 0) AS stock_total,
                    SUM(CASE WHEN VAR_STOCK = 0 THEN 1 ELSE 0 END) AS ruptures
             FROM POM_VARIANTE WHERE VAR_DATE_SUPPRESSION IS NULL'
        )->fetch(PDO::FETCH_ASSOC);

        return [
            'stockTotal' => (int) ($ligne['stock_total'] ?? 0),
            'ruptures' => (int) ($ligne['ruptures'] ?? 0),
        ];
    }

    /**
     * Pour un lot de produits (grille catalogue) : par produit, le stock
     * total et, par couleur en stock, les tailles disponibles.
     */
    public static function disponibilitesParProduit(PDO $pdo, array $produitIds)
    {
        $detail = [];
        foreach ($produitIds as $produitId) {
            $detail[$produitId] = ['stockTotal' => 0, 'couleurs' => []];
        }

        if (!$produitIds) {
            return $detail;
        }

        $marqueurs = implode(',', array_fill(0, count($produitIds), '?'));
        $requete = $pdo->prepare(
            "SELECT VAR_ID, VAR_PRO_ID, VAR_TAILLE, VAR_COULEUR, VAR_STOCK
             FROM POM_VARIANTE
             WHERE VAR_DATE_SUPPRESSION IS NULL AND VAR_PRO_ID IN ($marqueurs)"
        );
        $requete->execute($produitIds);

        foreach ($requete->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
            $produitId = $ligne['VAR_PRO_ID'];
            $detail[$produitId]['stockTotal'] += (int) $ligne['VAR_STOCK'];
            if ((int) $ligne['VAR_STOCK'] > 0) {
                $detail[$produitId]['couleurs'][$ligne['VAR_COULEUR']][$ligne['VAR_TAILLE']] = (int) $ligne['VAR_ID'];
            }
        }

        // Tailles ordonnees selon TAILLES (XS avant XXL) plutot que l'ordre de retour SQL.
        foreach ($detail as &$infosProduit) {
            foreach ($infosProduit['couleurs'] as $couleur => $tailles) {
                $ordonnees = [];
                foreach (self::TAILLES as $taille) {
                    if (isset($tailles[$taille])) {
                        $ordonnees[$taille] = $tailles[$taille];
                    }
                }
                $infosProduit['couleurs'][$couleur] = $ordonnees;
            }
        }
        unset($infosProduit);

        return $detail;
    }

    /**
     * Pour un lot de produits (grille catalogue) : les tailles et
     * couleurs en stock (VAR_STOCK > 0) de chacun, dans l'ordre des
     * constantes TAILLES/COULEURS. Cle du tableau retourne : PRO_ID.
     */
    public static function resumeParProduits(PDO $pdo, array $produitIds)
    {
        $resume = [];
        foreach ($produitIds as $produitId) {
            $resume[$produitId] = ['tailles' => [], 'couleurs' => []];
        }

        if (!$produitIds) {
            return $resume;
        }

        $marqueurs = implode(',', array_fill(0, count($produitIds), '?'));
        $requete = $pdo->prepare(
            "SELECT DISTINCT VAR_PRO_ID, VAR_TAILLE, VAR_COULEUR
             FROM POM_VARIANTE
             WHERE VAR_STOCK > 0 AND VAR_DATE_SUPPRESSION IS NULL AND VAR_PRO_ID IN ($marqueurs)"
        );
        $requete->execute($produitIds);

        $taillesParProduit = [];
        $couleursParProduit = [];
        foreach ($requete->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
            $taillesParProduit[$ligne['VAR_PRO_ID']][$ligne['VAR_TAILLE']] = true;
            $couleursParProduit[$ligne['VAR_PRO_ID']][$ligne['VAR_COULEUR']] = true;
        }

        foreach ($resume as $produitId => &$donnees) {
            $donnees['tailles'] = array_values(array_filter(
                self::TAILLES,
                fn ($taille) => isset($taillesParProduit[$produitId][$taille])
            ));
            $donnees['couleurs'] = array_values(array_filter(
                self::COULEURS,
                fn ($couleur) => isset($couleursParProduit[$produitId][$couleur])
            ));
        }

        return $resume;
    }

    public static function pourProduit(PDO $pdo, $produitId)
    {
        $requete = $pdo->prepare(
            'SELECT VAR_ID, VAR_SKU, VAR_TAILLE, VAR_COULEUR, VAR_STOCK, VAR_PRO_ID
             FROM POM_VARIANTE WHERE VAR_PRO_ID = ? AND VAR_DATE_SUPPRESSION IS NULL
             ORDER BY VAR_TAILLE, VAR_COULEUR'
        );
        $requete->execute([$produitId]);

        $variantes = [];
        foreach ($requete->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
            $variantes[] = self::depuisLigne($ligne);
        }
        return $variantes;
    }

    public static function trouver(PDO $pdo, $id)
    {
        $requete = $pdo->prepare(
            'SELECT VAR_ID, VAR_SKU, VAR_TAILLE, VAR_COULEUR, VAR_STOCK, VAR_PRO_ID
             FROM POM_VARIANTE WHERE VAR_ID = ? AND VAR_DATE_SUPPRESSION IS NULL'
        );
        $requete->execute([$id]);
        $ligne = $requete->fetch(PDO::FETCH_ASSOC);

        return $ligne ? self::depuisLigne($ligne) : null;
    }

    /** Une combinaison taille/couleur ne peut exister qu'une fois par produit (contrainte UQ_VAR_COMBI). */
    public static function combinaisonDejaUtilisee(PDO $pdo, $produitId, $taille, $couleur)
    {
        $requete = $pdo->prepare(
            'SELECT VAR_ID FROM POM_VARIANTE
             WHERE VAR_PRO_ID = ? AND VAR_TAILLE = ? AND VAR_COULEUR = ? AND VAR_DATE_SUPPRESSION IS NULL'
        );
        $requete->execute([$produitId, $taille, $couleur]);

        return $requete->fetch() !== false;
    }

    /**
     * Le SKU est genere a partir de la reference du produit et de la
     * combinaison taille/couleur : il reste unique de fait, puisque la
     * combinaison l'est deja par produit (UQ_VAR_COMBI) et que la
     * reference produit l'est elle-meme (UQ_PRO_REF). Pas besoin de le
     * demander a l'admin ni de le verifier separement.
     *
     * Si la meme combinaison a deja existe puis ete supprimee (suppression
     * logique), sa ligne est toujours la et UQ_VAR_COMBI refuserait
     * l'INSERT : ON DUPLICATE KEY UPDATE la fait alors revivre avec le
     * nouveau stock. Les commandes passees qui la referencent sont intactes.
     */
    public static function creer(PDO $pdo, $produitId, $referenceProduit, $taille, $couleur, $stock)
    {
        $sku = $referenceProduit . '-' . $taille . '-' . $couleur;

        $requete = $pdo->prepare(
            'INSERT INTO POM_VARIANTE (VAR_SKU, VAR_TAILLE, VAR_COULEUR, VAR_STOCK, VAR_PRO_ID)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE VAR_SKU = VALUES(VAR_SKU), VAR_STOCK = VALUES(VAR_STOCK),
                                     VAR_DATE_SUPPRESSION = NULL'
        );
        $requete->execute([$sku, $taille, $couleur, $stock, $produitId]);
    }

    // Seul le stock se modifie apres coup : taille/couleur figent le SKU,
    // pour changer l'un il faut supprimer la variante et en recreer une.
    public static function modifierStock(PDO $pdo, $id, $stock)
    {
        $requete = $pdo->prepare(
            'UPDATE POM_VARIANTE SET VAR_STOCK = ? WHERE VAR_ID = ? AND VAR_DATE_SUPPRESSION IS NULL'
        );
        $requete->execute([$stock, $id]);
    }

    /**
     * Suppression logique : la variante reste en base (les lignes de
     * commande la referencent toujours), elle disparait de la boutique,
     * de l'administration et des paniers.
     */
    public static function supprimer(PDO $pdo, $id)
    {
        $requete = $pdo->prepare(
            'UPDATE POM_VARIANTE SET VAR_DATE_SUPPRESSION = NOW() WHERE VAR_ID = ? AND VAR_DATE_SUPPRESSION IS NULL'
        );
        $requete->execute([$id]);
    }
}
