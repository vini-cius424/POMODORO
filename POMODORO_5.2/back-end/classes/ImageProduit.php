<?php

class ImageProduit
{
    public $id;
    public $chemin;
    public $estPrincipale;
    public $couleur;
    public $ordre;
    public $produitId;

    private function __construct($id, $chemin, $estPrincipale, $couleur, $ordre, $produitId)
    {
        $this->id = $id;
        $this->chemin = $chemin;
        $this->estPrincipale = $estPrincipale;
        $this->couleur = $couleur;
        $this->ordre = $ordre;
        $this->produitId = $produitId;
    }

    private static function depuisLigne($ligne)
    {
        return new self(
            $ligne['IMG_ID'],
            $ligne['IMG_CHEMIN'],
            $ligne['IMG_EST_PRINCIPAL'],
            $ligne['IMG_COULEUR'],
            $ligne['IMG_ORDRE'],
            $ligne['IMG_PRO_ID']
        );
    }

    public static function pourProduit(PDO $pdo, $produitId)
    {
        $requete = $pdo->prepare(
            'SELECT IMG_ID, IMG_CHEMIN, IMG_EST_PRINCIPAL, IMG_COULEUR, IMG_ORDRE, IMG_PRO_ID
             FROM POM_IMAGEPRODUITS WHERE IMG_PRO_ID = ? AND IMG_DATE_SUPPRESSION IS NULL
             ORDER BY IMG_ORDRE'
        );
        $requete->execute([$produitId]);

        $images = [];
        foreach ($requete->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
            $images[] = self::depuisLigne($ligne);
        }
        return $images;
    }

    public static function trouver(PDO $pdo, $id)
    {
        $requete = $pdo->prepare(
            'SELECT IMG_ID, IMG_CHEMIN, IMG_EST_PRINCIPAL, IMG_COULEUR, IMG_ORDRE, IMG_PRO_ID
             FROM POM_IMAGEPRODUITS WHERE IMG_ID = ? AND IMG_DATE_SUPPRESSION IS NULL'
        );
        $requete->execute([$id]);
        $ligne = $requete->fetch(PDO::FETCH_ASSOC);

        return $ligne ? self::depuisLigne($ligne) : null;
    }

    /**
     * Pour un lot de produits (grille catalogue) : l'image a montrer pour
     * chaque couple (produit, couleur), pour servir de vignette de
     * variante. Si plusieurs photos partagent la meme couleur, celle
     * marquee principale gagne ; sinon la premiere ajoutee (plus petit
     * IMG_ORDRE). Cle du tableau retourne : "<produitId>-<COULEUR>".
     */
    public static function vignetteParCouleur(PDO $pdo, array $produitIds)
    {
        if (!$produitIds) {
            return [];
        }

        $marqueurs = implode(',', array_fill(0, count($produitIds), '?'));
        $requete = $pdo->prepare(
            "SELECT img.IMG_ID, img.IMG_PRO_ID, img.IMG_COULEUR, img.IMG_CHEMIN, img.IMG_ORDRE, img.IMG_EST_PRINCIPAL
             FROM POM_IMAGEPRODUITS img
             INNER JOIN (
                 SELECT IMG_PRO_ID, IMG_COULEUR,
                        COALESCE(MIN(CASE WHEN IMG_EST_PRINCIPAL = 1 THEN IMG_ORDRE END), MIN(IMG_ORDRE)) AS ordre_a_montrer
                 FROM POM_IMAGEPRODUITS
                 WHERE IMG_COULEUR IS NOT NULL AND IMG_DATE_SUPPRESSION IS NULL AND IMG_PRO_ID IN ($marqueurs)
                 GROUP BY IMG_PRO_ID, IMG_COULEUR
             ) m ON m.IMG_PRO_ID = img.IMG_PRO_ID
                 AND m.IMG_COULEUR = img.IMG_COULEUR
                 AND m.ordre_a_montrer = img.IMG_ORDRE
             WHERE img.IMG_DATE_SUPPRESSION IS NULL"
        );
        $requete->execute($produitIds);

        $images = [];
        foreach ($requete->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
            $images[$ligne['IMG_PRO_ID'] . '-' . $ligne['IMG_COULEUR']] = [
                'id' => $ligne['IMG_ID'],
                'chemin' => $ligne['IMG_CHEMIN'],
                'ordre' => (int) $ligne['IMG_ORDRE'],
                'principale' => (bool) $ligne['IMG_EST_PRINCIPAL'],
            ];
        }
        return $images;
    }

    /**
     * Pour un lot de produits : TOUTES les photos de chaque couple
     * (produit, couleur), la principale en tete puis les autres dans leur
     * ordre d'ajout - pour un carrousel (grille catalogue), contrairement
     * a vignetteParCouleur() qui n'en garde qu'une seule. Cle du tableau
     * retourne : "<produitId>-<COULEUR>", valeur = liste de chemins.
     */
    public static function galerieParCouleur(PDO $pdo, array $produitIds)
    {
        if (!$produitIds) {
            return [];
        }

        $marqueurs = implode(',', array_fill(0, count($produitIds), '?'));
        $requete = $pdo->prepare(
            "SELECT IMG_PRO_ID, IMG_COULEUR, IMG_CHEMIN, IMG_ORDRE, IMG_EST_PRINCIPAL
             FROM POM_IMAGEPRODUITS
             WHERE IMG_COULEUR IS NOT NULL AND IMG_DATE_SUPPRESSION IS NULL AND IMG_PRO_ID IN ($marqueurs)
             ORDER BY IMG_ORDRE"
        );
        $requete->execute($produitIds);

        $galeries = [];
        foreach ($requete->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
            $galeries[$ligne['IMG_PRO_ID'] . '-' . $ligne['IMG_COULEUR']][] = [
                'chemin' => $ligne['IMG_CHEMIN'],
                'ordre' => (int) $ligne['IMG_ORDRE'],
                'principale' => (bool) $ligne['IMG_EST_PRINCIPAL'],
            ];
        }

        // La principale en tete (PHP 8 : usort est stable, le reste garde
        // donc l'ordre d'ajout deja impose par le ORDER BY ci-dessus).
        foreach ($galeries as &$images) {
            usort($images, fn ($a, $b) => $b['principale'] <=> $a['principale']);
        }
        unset($images);

        return $galeries;
    }

    /**
     * La toute premiere image d'un produit devient automatiquement la principale. Renvoie l'id cree.
     * MAX(IMG_ORDRE) compte aussi les photos supprimees : leur ligne occupe
     * toujours son rang (UQ_IMG_ORDRE). nb, lui, ne compte que les photos visibles.
     */
    public static function ajouter(PDO $pdo, $produitId, $chemin, $couleur = null)
    {
        $requete = $pdo->prepare(
            'SELECT COALESCE(MAX(IMG_ORDRE), -1) AS max_ordre,
                    COUNT(CASE WHEN IMG_DATE_SUPPRESSION IS NULL THEN 1 END) AS nb
             FROM POM_IMAGEPRODUITS WHERE IMG_PRO_ID = ?'
        );
        $requete->execute([$produitId]);
        $ligne = $requete->fetch(PDO::FETCH_ASSOC);

        $ordre = $ligne['max_ordre'] + 1;
        $estPrincipale = $ligne['nb'] == 0 ? 1 : 0;

        $insertion = $pdo->prepare(
            'INSERT INTO POM_IMAGEPRODUITS (IMG_CHEMIN, IMG_EST_PRINCIPAL, IMG_COULEUR, IMG_ORDRE, IMG_PRO_ID)
             VALUES (?, ?, ?, ?, ?)'
        );
        $insertion->execute([$chemin, $estPrincipale, $couleur !== '' ? $couleur : null, $ordre, $produitId]);

        return $pdo->lastInsertId();
    }

    /**
     * RG4 - une seule image principale par produit : impossible a verifier
     * avec un simple CHECK (il faudrait lire les autres lignes), donc
     * gere ici en retirant l'ancienne avant de poser la nouvelle.
     */
    public static function definirPrincipale(PDO $pdo, $produitId, $imageId)
    {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE POM_IMAGEPRODUITS SET IMG_EST_PRINCIPAL = 0 WHERE IMG_PRO_ID = ?')
            ->execute([$produitId]);
        $pdo->prepare('UPDATE POM_IMAGEPRODUITS SET IMG_EST_PRINCIPAL = 1
                       WHERE IMG_ID = ? AND IMG_PRO_ID = ? AND IMG_DATE_SUPPRESSION IS NULL')
            ->execute([$imageId, $produitId]);
        $pdo->commit();
    }

    /**
     * Echange la position d'une photo avec sa voisine (haut = plus tot,
     * bas = plus tard). On passe par une valeur temporaire hors plage
     * (UQ_IMG_ORDRE interdit deux photos au meme rang pour un produit,
     * meme un instant) plutot que d'echanger les deux UPDATE directement.
     */
    public static function deplacer(PDO $pdo, $produitId, $imageId, string $sens)
    {
        $images = self::pourProduit($pdo, $produitId); // deja triees par IMG_ORDRE

        $index = null;
        foreach ($images as $i => $image) {
            if ((int) $image->id === (int) $imageId) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            return;
        }

        $voisin = $sens === 'haut' ? $index - 1 : $index + 1;
        if ($voisin < 0 || $voisin >= count($images)) {
            return;
        }

        $a = $images[$index];
        $b = $images[$voisin];

        $pdo->beginTransaction();
        $pdo->prepare('UPDATE POM_IMAGEPRODUITS SET IMG_ORDRE = ? WHERE IMG_ID = ?')->execute([100000 + (int) $a->ordre, $a->id]);
        $pdo->prepare('UPDATE POM_IMAGEPRODUITS SET IMG_ORDRE = ? WHERE IMG_ID = ?')->execute([$a->ordre, $b->id]);
        $pdo->prepare('UPDATE POM_IMAGEPRODUITS SET IMG_ORDRE = ? WHERE IMG_ID = ?')->execute([$b->ordre, $a->id]);
        $pdo->commit();
    }

    /**
     * Suppression logique : la ligne et le fichier restent, la photo
     * disparait seulement du site. Elle perd aussi le drapeau "principale",
     * ce qui la sort d'office de toutes les jointures sur
     * IMG_EST_PRINCIPAL = 1 (vignettes du catalogue, panier...).
     */
    public static function supprimer(PDO $pdo, $id)
    {
        $requete = $pdo->prepare(
            'UPDATE POM_IMAGEPRODUITS SET IMG_DATE_SUPPRESSION = NOW(), IMG_EST_PRINCIPAL = 0
             WHERE IMG_ID = ? AND IMG_DATE_SUPPRESSION IS NULL'
        );
        $requete->execute([$id]);
    }
}
