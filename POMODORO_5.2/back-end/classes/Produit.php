<?php

class Produit
{
    // Doit rester synchronise avec la contrainte CK_PRO_TYPE du script SQL.
    const TYPES = [
        'TSHIRT', 'PULL', 'SWEAT', 'VESTE', 'PANTALON', 'SHORT',
        'CASQUETTE', 'BONNET', 'SAC', 'CHAUSSETTES',
    ];

    // Texte affiche pour un type quand le code seul ne suffit pas
    // ("TSHIRT" donnerait "Tshirt"). Les autres types s'affichent tels
    // quels, en minuscules avec une majuscule ("PULL" -> "Pull").
    const LIBELLES_TYPES = [
        'TSHIRT' => 'T-shirt',
    ];

    /** Le libelle d'un type pour l'affichage ("TSHIRT" -> "T-shirt"). */
    public static function libelleType($type)
    {
        return self::LIBELLES_TYPES[$type] ?? ucfirst(mb_strtolower($type));
    }

    public $id;
    public $reference;
    public $nom;
    public $description;
    public $prix;
    public $prixPromo;
    public $actif;
    public $type;
    public $categorieId;
    public $categorieNom;
    public $imagePrincipale;
    public $variantesEnRupture; // rempli seulement par listePourAdmin()
    public $categorieActive;    // rempli seulement par trouver()

    /**
     * Condition SQL commune a toutes les requetes de la boutique : le
     * produit doit etre range dans une categorie active. Desactiver une
     * categorie retire donc tous ses produits de la vente, sans toucher
     * a leur propre PRO_ACTIF. Suppose l'alias "p" pour POM_PRODUIT.
     */
    const CATEGORIE_ACTIVE = 'p.PRO_CAT_ID IN (SELECT CAT_ID FROM POM_CATEGORIE WHERE CAT_ACTIF = 1)';

    /** En vente sur la boutique : produit actif ET categorie active. */
    public function estEnVente()
    {
        return $this->actif && $this->categorieActive;
    }

    private function __construct($id, $reference, $nom, $description, $prix, $prixPromo, $actif, $type, $categorieId, $categorieNom = null)
    {
        $this->id = $id;
        $this->reference = $reference;
        $this->nom = $nom;
        $this->description = $description;
        $this->prix = $prix;
        $this->prixPromo = $prixPromo;
        $this->actif = $actif;
        $this->type = $type;
        $this->categorieId = $categorieId;
        $this->categorieNom = $categorieNom;
    }

    /** Le prix a afficher/facturer : le promo s'il est defini, sinon le prix normal. */
    public function prixEffectif()
    {
        return $this->prixPromo !== null ? (float) $this->prixPromo : (float) $this->prix;
    }

    private static function depuisLigne($ligne)
    {
        return new self(
            $ligne['PRO_ID'],
            $ligne['PRO_REFERENCE'],
            $ligne['PRO_NOM'],
            $ligne['PRO_DESCRIPTION'],
            $ligne['PRO_PRIX'],
            $ligne['PRO_PRIX_PROMO'] ?? null,
            $ligne['PRO_ACTIF'],
            $ligne['PRO_TYPE'],
            $ligne['PRO_CAT_ID'],
            $ligne['CAT_NOM'] ?? null
        );
    }

    /**
     * Liste pour l'admin, avec la vignette principale si elle existe.
     * $recherche (tache 18) : texte cherche dans le nom ou la reference.
     * Vide = tous les produits, comme avant.
     */
    public static function listePourAdmin(PDO $pdo, $recherche = '')
    {
        $sql = 'SELECT p.PRO_ID, p.PRO_REFERENCE, p.PRO_NOM, p.PRO_DESCRIPTION, p.PRO_PRIX, p.PRO_PRIX_PROMO,
                       p.PRO_ACTIF, p.PRO_TYPE, p.PRO_CAT_ID, c.CAT_NOM, img.IMG_CHEMIN,
                       (SELECT COUNT(*) FROM POM_VARIANTE v
                        WHERE v.VAR_PRO_ID = p.PRO_ID AND v.VAR_STOCK = 0
                          AND v.VAR_DATE_SUPPRESSION IS NULL) AS NB_RUPTURES
                FROM POM_PRODUIT p
                JOIN POM_CATEGORIE c ON c.CAT_ID = p.PRO_CAT_ID
                LEFT JOIN POM_IMAGEPRODUITS img ON img.IMG_PRO_ID = p.PRO_ID AND img.IMG_EST_PRINCIPAL = 1';
        // NB_RUPTURES : sous-requete (un SELECT dans le SELECT) qui compte,
        // pour chaque produit, ses variantes dont le stock vaut 0. Meme
        // definition que "Variantes en rupture" du tableau de bord.

        $parametres = [];
        $recherche = trim($recherche);
        if ($recherche !== '') {
            // LIKE '%pull%' : "contient pull", n'importe ou dans le texte.
            // Les % sont ajoutes en PHP ; le texte du visiteur passe par un
            // "?" de la requete preparee, jamais colle dans le SQL.
            $sql .= ' WHERE p.PRO_NOM LIKE ? OR p.PRO_REFERENCE LIKE ?';
            $parametres = ['%' . $recherche . '%', '%' . $recherche . '%'];
        }
        $sql .= ' ORDER BY p.PRO_NOM';

        $requete = $pdo->prepare($sql);
        $requete->execute($parametres);

        $produits = [];
        foreach ($requete->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
            $produit = self::depuisLigne($ligne);
            $produit->imagePrincipale = $ligne['IMG_CHEMIN'];
            $produit->variantesEnRupture = (int) $ligne['NB_RUPTURES'];
            $produits[] = $produit;
        }
        return $produits;
    }

    /** Nombre total de produits, et combien sont actifs (pour le tableau de bord). */
    public static function statistiques(PDO $pdo)
    {
        $ligne = $pdo->query(
            'SELECT COUNT(*) AS total, SUM(PRO_ACTIF) AS actifs FROM POM_PRODUIT'
        )->fetch(PDO::FETCH_ASSOC);

        return [
            'total' => (int) ($ligne['total'] ?? 0),
            'actifs' => (int) ($ligne['actifs'] ?? 0),
        ];
    }

    /**
     * Construit la clause WHERE commune a rechercherActifs() et
     * compterActifs(), pour ne jamais faire diverger le compte du resultat
     * reellement affiche. $filtres accepte les cles 'categorie', 'q'
     * (recherche texte), 'type', 'sous_categorie', 'taille' (tableau de
     * tailles), 'couleur', 'prix_min', 'prix_max', 'promo' et 'nouveautes'
     * (bool), toutes facultatives.
     */
    private static function construireClauseWhere(array $filtres)
    {
        $sql = ' WHERE p.PRO_ACTIF = 1 AND ' . self::CATEGORIE_ACTIVE;
        $parametres = [];

        if (!empty($filtres['categorie'])) {
            // Un vetement unisexe se porte aussi bien par un homme que par
            // une femme : les rayons Homme et Femme affichent donc aussi les
            // produits de la categorie Unisexe. Reperes par leur NOM, car
            // leur CAT_ID change d'une base a l'autre (locale, en ligne).
            $sql .= " AND (p.PRO_CAT_ID = ?
                        OR (? IN (SELECT CAT_ID FROM POM_CATEGORIE WHERE CAT_NOM IN ('Homme', 'Femme'))
                            AND p.PRO_CAT_ID IN (SELECT CAT_ID FROM POM_CATEGORIE WHERE CAT_NOM = 'Unisexe')))";
            $parametres[] = $filtres['categorie'];
            $parametres[] = $filtres['categorie'];
        }

        if (!empty($filtres['q'])) {
            // Espaces et tirets ignores des deux cotes : "t shirt" doit
            // retrouver "T-shirt", peu importe la forme tapee par le client.
            // Le type (PRO_TYPE, ex. "SAC") est aussi cherche : un mot comme
            // "sac" n'apparait pas forcement dans le nom ni la description.
            $sql .= " AND (REPLACE(REPLACE(p.PRO_NOM, '-', ''), ' ', '') LIKE ?
                        OR REPLACE(REPLACE(p.PRO_DESCRIPTION, '-', ''), ' ', '') LIKE ?
                        OR p.PRO_TYPE LIKE ?)";
            $recherche = '%' . str_replace([' ', '-'], '', $filtres['q']) . '%';
            $parametres[] = $recherche;
            $parametres[] = $recherche;
            $parametres[] = $recherche;
        }

        if (!empty($filtres['type'])) {
            $sql .= ' AND p.PRO_TYPE = ?';
            $parametres[] = $filtres['type'];
        }

        if (!empty($filtres['sous_categorie'])) {
            $sql .= ' AND p.PRO_SOUS_CATEGORIE = ?';
            $parametres[] = $filtres['sous_categorie'];
        }

        // Taille/couleur : au moins une variante en stock qui correspond,
        // via EXISTS pour ne pas dupliquer la ligne produit par variante.
        // Plusieurs tailles = OR entre elles (IN), pas une intersection.
        if (!empty($filtres['taille'])) {
            $marqueurs = implode(',', array_fill(0, count($filtres['taille']), '?'));
            $sql .= " AND EXISTS (SELECT 1 FROM POM_VARIANTE v WHERE v.VAR_PRO_ID = p.PRO_ID AND v.VAR_TAILLE IN ($marqueurs) AND v.VAR_STOCK > 0 AND v.VAR_DATE_SUPPRESSION IS NULL)";
            foreach ($filtres['taille'] as $taille) {
                $parametres[] = $taille;
            }
        }

        if (!empty($filtres['couleur'])) {
            $sql .= ' AND EXISTS (SELECT 1 FROM POM_VARIANTE v WHERE v.VAR_PRO_ID = p.PRO_ID AND v.VAR_COULEUR = ? AND v.VAR_STOCK > 0 AND v.VAR_DATE_SUPPRESSION IS NULL)';
            $parametres[] = $filtres['couleur'];
        }

        if (!empty($filtres['promo'])) {
            $sql .= ' AND p.PRO_PRIX_PROMO IS NOT NULL';
        }

        if (!empty($filtres['nouveautes'])) {
            $sql .= ' AND p.PRO_DATECREATION >= (NOW() - INTERVAL 30 DAY)';
        }

        if (($filtres['prix_min'] ?? '') !== '') {
            $sql .= ' AND COALESCE(p.PRO_PRIX_PROMO, p.PRO_PRIX) >= ?';
            $parametres[] = $filtres['prix_min'];
        }

        if (($filtres['prix_max'] ?? '') !== '') {
            $sql .= ' AND COALESCE(p.PRO_PRIX_PROMO, p.PRO_PRIX) <= ?';
            $parametres[] = $filtres['prix_max'];
        }

        return [$sql, $parametres];
    }

    /**
     * Les produits visibles sur la boutique publique, une page a la fois.
     * Voir construireClauseWhere() pour les cles de $filtres ; 'tri' accepte
     * 'prix_asc', 'prix_desc', 'nouveautes' ou '' (pertinence = nom).
     */
    public static function rechercherActifs(PDO $pdo, array $filtres = [], int $page = 1, int $parPage = 24)
    {
        [$sqlOu, $parametres] = self::construireClauseWhere($filtres);

        $sql = 'SELECT p.PRO_ID, p.PRO_REFERENCE, p.PRO_NOM, p.PRO_DESCRIPTION, p.PRO_PRIX, p.PRO_PRIX_PROMO,
                       p.PRO_ACTIF, p.PRO_TYPE, p.PRO_CAT_ID, c.CAT_NOM, img.IMG_CHEMIN
                FROM POM_PRODUIT p
                JOIN POM_CATEGORIE c ON c.CAT_ID = p.PRO_CAT_ID
                LEFT JOIN POM_IMAGEPRODUITS img ON img.IMG_PRO_ID = p.PRO_ID AND img.IMG_EST_PRINCIPAL = 1'
            . $sqlOu;

        $sql .= match ($filtres['tri'] ?? '') {
            'prix_asc' => ' ORDER BY COALESCE(p.PRO_PRIX_PROMO, p.PRO_PRIX) ASC',
            'prix_desc' => ' ORDER BY COALESCE(p.PRO_PRIX_PROMO, p.PRO_PRIX) DESC',
            'nouveautes' => ' ORDER BY p.PRO_DATECREATION DESC',
            default => ' ORDER BY p.PRO_NOM',
        };

        // LIMIT/OFFSET ne se preparent pas en placeholders sur tous les
        // drivers : ce sont des entiers casts en PHP, jamais une valeur
        // utilisateur brute, donc pas de risque d'injection ici.
        $page = max(1, $page);
        $parPage = max(1, $parPage);
        $sql .= ' LIMIT ' . $parPage . ' OFFSET ' . (($page - 1) * $parPage);

        $requete = $pdo->prepare($sql);
        $requete->execute($parametres);

        $produits = [];
        foreach ($requete->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
            $produit = self::depuisLigne($ligne);
            $produit->imagePrincipale = $ligne['IMG_CHEMIN'];
            $produits[] = $produit;
        }
        return $produits;
    }

    /** Nombre de produits actifs correspondant aux memes filtres que rechercherActifs(), pour la pagination. */
    public static function compterActifs(PDO $pdo, array $filtres = [])
    {
        [$sqlOu, $parametres] = self::construireClauseWhere($filtres);

        $requete = $pdo->prepare('SELECT COUNT(*) FROM POM_PRODUIT p' . $sqlOu);
        $requete->execute($parametres);

        return (int) $requete->fetchColumn();
    }

    /** Prix min/max reels du catalogue actif, pour borner le slider de prix. */
    public static function bornesPrix(PDO $pdo)
    {
        $ligne = $pdo->query(
            'SELECT MIN(COALESCE(PRO_PRIX_PROMO, PRO_PRIX)) AS mini, MAX(COALESCE(PRO_PRIX_PROMO, PRO_PRIX)) AS maxi
             FROM POM_PRODUIT p WHERE p.PRO_ACTIF = 1 AND ' . self::CATEGORIE_ACTIVE
        )->fetch(PDO::FETCH_ASSOC);

        return [
            'min' => $ligne['mini'] !== null ? (float) $ligne['mini'] : 0.0,
            'max' => $ligne['maxi'] !== null ? (float) $ligne['maxi'] : 0.0,
        ];
    }

    /** Valeurs distinctes de sous-categorie reellement utilisees (jamais de fausses options). */
    public static function sousCategoriesUtilisees(PDO $pdo)
    {
        $requete = $pdo->query(
            "SELECT DISTINCT PRO_SOUS_CATEGORIE FROM POM_PRODUIT p
             WHERE PRO_ACTIF = 1 AND " . self::CATEGORIE_ACTIVE . "
               AND PRO_SOUS_CATEGORIE IS NOT NULL AND PRO_SOUS_CATEGORIE <> ''
             ORDER BY PRO_SOUS_CATEGORIE"
        );

        return $requete->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function trouver(PDO $pdo, $id)
    {
        $requete = $pdo->prepare(
            'SELECT p.PRO_ID, p.PRO_REFERENCE, p.PRO_NOM, p.PRO_DESCRIPTION, p.PRO_PRIX, p.PRO_PRIX_PROMO,
                    p.PRO_ACTIF, p.PRO_TYPE, p.PRO_CAT_ID, c.CAT_NOM, c.CAT_ACTIF
             FROM POM_PRODUIT p
             JOIN POM_CATEGORIE c ON c.CAT_ID = p.PRO_CAT_ID
             WHERE p.PRO_ID = ?'
        );
        $requete->execute([$id]);
        $ligne = $requete->fetch(PDO::FETCH_ASSOC);

        if (!$ligne) {
            return null;
        }

        $produit = self::depuisLigne($ligne);
        $produit->categorieActive = (bool) $ligne['CAT_ACTIF'];
        return $produit;
    }

    /** Une reference doit rester unique (contrainte UQ_PRO_REF en base). */
    public static function referenceDejaUtilisee(PDO $pdo, $reference, $idAIgnorer = null)
    {
        if ($idAIgnorer !== null) {
            $requete = $pdo->prepare('SELECT PRO_ID FROM POM_PRODUIT WHERE PRO_REFERENCE = ? AND PRO_ID <> ?');
            $requete->execute([$reference, $idAIgnorer]);
        } else {
            $requete = $pdo->prepare('SELECT PRO_ID FROM POM_PRODUIT WHERE PRO_REFERENCE = ?');
            $requete->execute([$reference]);
        }

        return $requete->fetch() !== false;
    }

    public static function creer(PDO $pdo, $reference, $nom, $description, $prix, $prixPromo, $actif, $type, $categorieId, $adminId)
    {
        $requete = $pdo->prepare(
            'INSERT INTO POM_PRODUIT (PRO_REFERENCE, PRO_NOM, PRO_DESCRIPTION, PRO_PRIX, PRO_PRIX_PROMO, PRO_ACTIF, PRO_TYPE, PRO_CAT_ID, PRO_ADM_ID)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $requete->execute([
            $reference, $nom, $description !== '' ? $description : null,
            $prix, $prixPromo, $actif ? 1 : 0, $type, $categorieId, $adminId,
        ]);

        return $pdo->lastInsertId();
    }

    public static function modifier(PDO $pdo, $id, $reference, $nom, $description, $prix, $prixPromo, $actif, $type, $categorieId)
    {
        $requete = $pdo->prepare(
            'UPDATE POM_PRODUIT
             SET PRO_REFERENCE = ?, PRO_NOM = ?, PRO_DESCRIPTION = ?, PRO_PRIX = ?, PRO_PRIX_PROMO = ?,
                 PRO_ACTIF = ?, PRO_TYPE = ?, PRO_CAT_ID = ?
             WHERE PRO_ID = ?'
        );
        $requete->execute([
            $reference, $nom, $description !== '' ? $description : null,
            $prix, $prixPromo, $actif ? 1 : 0, $type, $categorieId, $id,
        ]);
    }

    /** Bascule PRO_ACTIF : un produit actif devient inactif, et inversement. */
    public static function basculerActif(PDO $pdo, $id)
    {
        $requete = $pdo->prepare(
            'UPDATE POM_PRODUIT SET PRO_ACTIF = NOT PRO_ACTIF WHERE PRO_ID = ?'
        );
        $requete->execute([$id]);
    }
}
