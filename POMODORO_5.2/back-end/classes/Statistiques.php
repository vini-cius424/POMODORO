<?php
// =====================================================================
// POMODORO STORE - Classe Statistiques
//
// Les chiffres de la page administration/statistiques.php. Contrairement
// aux autres classes, elle ne correspond a aucune table : elle LIT
// plusieurs tables (commandes, lignes, produits, clients) pour en tirer
// des indicateurs. Elle n'ecrit jamais rien.
//
// Regle commune : seule une commande PAYEE a rapporte de l'argent
// (Statistiques::PAYEE). Une commande ANNULEE, ou encore EN_ATTENTE (pas
// encore payee : paiement Stripe en cours ou abandonne, ou pas encore
// validee par la boutique), est exclue de tous les montants et de toutes
// les ventes. L'annulee ne compte que dans le taux d'annulation.
//
// $depuis : date de debut de la periode ('2026-09-01 00:00:00'), ou
// null pour "depuis l'ouverture de la boutique".
// =====================================================================

require_once __DIR__ . '/Commande.php';
require_once __DIR__ . '/Variante.php';

class Statistiques
{
    // Statuts d'une commande payee : validee (paiement confirme) et tout ce
    // qui suit. Suppose l'alias "c" pour POM_COMMANDE.
    const PAYEE = "c.COM_STATUT IN ('VALIDEE', 'EN_PREPARATION', 'EXPEDIEE', 'LIVREE')";

    // Periodes proposees par les onglets de la page : cle d'URL => libelle
    // et nombre de jours (null = tout l'historique).
    const PERIODES = [
        '7j'   => ['libelle' => '7 derniers jours',  'jours' => 7],
        '30j'  => ['libelle' => '30 derniers jours', 'jours' => 30],
        '12m'  => ['libelle' => '12 derniers mois',  'jours' => 365],
        'tout' => ['libelle' => 'Depuis l\'ouverture', 'jours' => null],
    ];

    // Date de debut d'une periode, au format SQL, ou null pour "tout".
    // Aujourd'hui compte comme un des jours : "7 derniers jours" = aujourd'hui
    // + les 6 jours d'avant, d'ou le "- 1" (sinon on en compterait 8).
    public static function debutPeriode($cle)
    {
        $jours = self::PERIODES[$cle]['jours'] ?? null;
        return $jours === null ? null : date('Y-m-d 00:00:00', strtotime('-' . ($jours - 1) . ' days'));
    }

    // Morceau de WHERE commun : la periode, sur la date de commande.
    // Renvoie [sql, parametres] pour l'ajouter a une requete preparee.
    private static function filtrePeriode($depuis, $colonne = 'c.COM_DATE')
    {
        return $depuis === null ? ['', []] : [" AND $colonne >= ?", [$depuis]];
    }

    /**
     * Les indicateurs principaux de la periode :
     *  - chiffreAffaires : total encaisse TTC (remises deduites, frais de port compris) ;
     *  - commandes       : nombre de commandes payees ;
     *  - panierMoyen     : chiffre d'affaires / commandes ;
     *  - articles        : nombre de pieces vendues ;
     *  - remises         : total des reductions accordees (code POMO20) ;
     *  - tva             : TVA contenue dans le chiffre d'affaires ;
     *  - annulees / tauxAnnulation : commandes annulees et leur part (en %) ;
     *  - enAttente / montantEnAttente : commandes pas encore payees et leur
     *    montant, exclues du chiffre d'affaires mais affichees a part.
     */
    public static function resume(PDO $pdo, $depuis)
    {
        [$sqlPeriode, $parametres] = self::filtrePeriode($depuis);

        $requete = $pdo->prepare(
            "SELECT
                 COUNT(CASE WHEN " . self::PAYEE . " THEN 1 END) AS commandes,
                 COUNT(CASE WHEN c.COM_STATUT = 'ANNULEE' THEN 1 END)  AS annulees,
                 COUNT(CASE WHEN c.COM_STATUT = 'EN_ATTENTE' THEN 1 END) AS en_attente,
                 COALESCE(SUM(CASE WHEN c.COM_STATUT = 'EN_ATTENTE' THEN c.COM_MONTANT_TOTAL END), 0) AS montant_en_attente,
                 COALESCE(SUM(CASE WHEN " . self::PAYEE . " THEN c.COM_MONTANT_TOTAL END), 0) AS chiffre,
                 COALESCE(SUM(CASE WHEN " . self::PAYEE . " THEN c.COM_REMISE END), 0)        AS remises
             FROM POM_COMMANDE c
             WHERE 1 = 1 $sqlPeriode"
        );
        $requete->execute($parametres);
        $ligne = $requete->fetch(PDO::FETCH_ASSOC);

        $requete = $pdo->prepare(
            "SELECT COALESCE(SUM(l.LCO_QUANTITE), 0)
             FROM POM_LIGNE_COMMANDE l
             JOIN POM_COMMANDE c ON c.COM_ID = l.LCO_COM_ID
             WHERE " . self::PAYEE . " $sqlPeriode"
        );
        $requete->execute($parametres);
        $articles = (int) $requete->fetchColumn();

        $commandes = (int) $ligne['commandes'];
        $annulees = (int) $ligne['annulees'];
        $chiffre = (float) $ligne['chiffre'];

        return [
            'chiffreAffaires' => $chiffre,
            'commandes'       => $commandes,
            'panierMoyen'     => $commandes > 0 ? round($chiffre / $commandes, 2) : 0.0,
            'articles'        => $articles,
            'remises'         => (float) $ligne['remises'],
            'tva'             => Commande::tvaComprise($chiffre),
            'annulees'        => $annulees,
            // Part des commandes passees qui ont ete annulees, en %.
            'tauxAnnulation'  => ($commandes + $annulees) > 0 ? round($annulees * 100 / ($commandes + $annulees), 1) : 0.0,
            'enAttente'        => (int) $ligne['en_attente'],
            'montantEnAttente' => (float) $ligne['montant_en_attente'],
        ];
    }

    /**
     * Commandes qui attendent une action de la boutique (en attente,
     * validees ou en preparation), toutes periodes confondues : c'est la
     * file de travail du jour, pas un chiffre historique.
     */
    public static function commandesATraiter(PDO $pdo)
    {
        return (int) $pdo->query(
            "SELECT COUNT(*) FROM POM_COMMANDE WHERE COM_STATUT IN ('EN_ATTENTE', 'VALIDEE', 'EN_PREPARATION')"
        )->fetchColumn();
    }

    /**
     * Chiffre d'affaires de chacun des 12 derniers mois, le mois en cours
     * compris. Un mois sans vente vaut 0 (il apparait quand meme : un trou
     * dans le graphique est une information).
     * Renvoie une liste de ['mois' => '2026-09', 'libelle' => 'sept. 2026',
     * 'chiffre' => float, 'commandes' => int], du plus ancien au plus recent.
     */
    public static function chiffreParMois(PDO $pdo)
    {
        $premierMois = date('Y-m-01 00:00:00', strtotime('first day of -11 months'));

        // DATE_FORMAT(..., '%Y-%m') regroupe toutes les commandes d'un meme mois.
        $requete = $pdo->prepare(
            "SELECT DATE_FORMAT(c.COM_DATE, '%Y-%m') AS mois,
                    SUM(c.COM_MONTANT_TOTAL) AS chiffre, COUNT(*) AS commandes
             FROM POM_COMMANDE c
             WHERE " . self::PAYEE . " AND c.COM_DATE >= ?
             GROUP BY mois"
        );
        $requete->execute([$premierMois]);
        $parMois = [];
        foreach ($requete->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
            $parMois[$ligne['mois']] = $ligne;
        }

        $nomsMois = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin',
                     'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
        $resultat = [];
        for ($i = 11; $i >= 0; $i--) {
            $horodatage = strtotime("first day of -$i months");
            $cle = date('Y-m', $horodatage);
            $resultat[] = [
                'mois'      => $cle,
                'libelle'   => $nomsMois[(int) date('n', $horodatage) - 1] . ' ' . date('Y', $horodatage),
                'chiffre'   => (float) ($parMois[$cle]['chiffre'] ?? 0),
                'commandes' => (int) ($parMois[$cle]['commandes'] ?? 0),
            ];
        }
        return $resultat;
    }

    /**
     * Les produits les plus vendus de la periode, en nombre de pieces.
     * "ventes" = prix payes x quantites (LCO_SOUS_TOTAL, prix fige a
     * l'achat), avant la remise de commande et sans frais de port.
     */
    public static function meilleursProduits(PDO $pdo, $depuis, $limite = 5)
    {
        [$sqlPeriode, $parametres] = self::filtrePeriode($depuis);

        $requete = $pdo->prepare(
            "SELECT p.PRO_ID, p.PRO_NOM, SUM(l.LCO_QUANTITE) AS quantite, SUM(l.LCO_SOUS_TOTAL) AS ventes
             FROM POM_LIGNE_COMMANDE l
             JOIN POM_COMMANDE c ON c.COM_ID = l.LCO_COM_ID
             JOIN POM_VARIANTE v ON v.VAR_ID = l.LCO_VAR_ID
             JOIN POM_PRODUIT p  ON p.PRO_ID = v.VAR_PRO_ID
             WHERE " . self::PAYEE . " $sqlPeriode
             GROUP BY p.PRO_ID, p.PRO_NOM
             ORDER BY quantite DESC, ventes DESC
             LIMIT " . (int) $limite
        );
        $requete->execute($parametres);

        return $requete->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Ventes de la periode par categorie (rayon), de la plus forte a la plus faible. */
    public static function ventesParCategorie(PDO $pdo, $depuis)
    {
        [$sqlPeriode, $parametres] = self::filtrePeriode($depuis);

        $requete = $pdo->prepare(
            "SELECT cat.CAT_NOM, SUM(l.LCO_QUANTITE) AS quantite, SUM(l.LCO_SOUS_TOTAL) AS ventes
             FROM POM_LIGNE_COMMANDE l
             JOIN POM_COMMANDE c    ON c.COM_ID = l.LCO_COM_ID
             JOIN POM_VARIANTE v    ON v.VAR_ID = l.LCO_VAR_ID
             JOIN POM_PRODUIT p     ON p.PRO_ID = v.VAR_PRO_ID
             JOIN POM_CATEGORIE cat ON cat.CAT_ID = p.PRO_CAT_ID
             WHERE " . self::PAYEE . " $sqlPeriode
             GROUP BY cat.CAT_ID, cat.CAT_NOM
             ORDER BY ventes DESC"
        );
        $requete->execute($parametres);

        return $requete->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Pieces vendues par taille sur la periode : sert a savoir quelles
     * tailles commander en plus grande quantite au fournisseur.
     * Dans l'ordre des tailles (XS -> XXL, puis taille unique).
     */
    public static function ventesParTaille(PDO $pdo, $depuis)
    {
        [$sqlPeriode, $parametres] = self::filtrePeriode($depuis);

        $requete = $pdo->prepare(
            "SELECT v.VAR_TAILLE, SUM(l.LCO_QUANTITE) AS quantite
             FROM POM_LIGNE_COMMANDE l
             JOIN POM_COMMANDE c ON c.COM_ID = l.LCO_COM_ID
             JOIN POM_VARIANTE v ON v.VAR_ID = l.LCO_VAR_ID
             WHERE " . self::PAYEE . " $sqlPeriode
             GROUP BY v.VAR_TAILLE"
        );
        $requete->execute($parametres);
        $parTaille = $requete->fetchAll(PDO::FETCH_KEY_PAIR);

        $resultat = [];
        foreach (Variante::TAILLES as $taille) {
            if (isset($parTaille[$taille])) {
                $resultat[$taille] = (int) $parTaille[$taille];
            }
        }
        return $resultat;
    }

    /**
     * Les clients :
     *  - nouveaux   : comptes crees pendant la periode ;
     *  - acheteurs  : clients differents qui ont commande pendant la periode ;
     *  - fideles    : parmi eux, ceux qui ont deja commande au moins 2 fois
     *                 (toutes periodes) ;
     *  - tauxFidelite : part des acheteurs qui sont des clients fideles, en %.
     */
    public static function clients(PDO $pdo, $depuis)
    {
        if ($depuis === null) {
            $nouveaux = (int) $pdo->query('SELECT COUNT(*) FROM POM_CLIENT')->fetchColumn();
        } else {
            $requete = $pdo->prepare('SELECT COUNT(*) FROM POM_CLIENT WHERE CLI_DATE_INSCRIPTION >= ?');
            $requete->execute([$depuis]);
            $nouveaux = (int) $requete->fetchColumn();
        }

        [$sqlPeriode, $parametres] = self::filtrePeriode($depuis);

        // Sous-requete : nombre total de commandes (payees) de chaque
        // client qui a commande pendant la periode.
        $requete = $pdo->prepare(
            "SELECT COUNT(*) AS acheteurs, COUNT(CASE WHEN total >= 2 THEN 1 END) AS fideles
             FROM (
                 SELECT c.COM_CLI_ID,
                        (SELECT COUNT(*) FROM POM_COMMANDE t
                         WHERE t.COM_CLI_ID = c.COM_CLI_ID
                           AND t.COM_STATUT IN ('VALIDEE', 'EN_PREPARATION', 'EXPEDIEE', 'LIVREE')) AS total
                 FROM POM_COMMANDE c
                 WHERE " . self::PAYEE . " $sqlPeriode
                 GROUP BY c.COM_CLI_ID
             ) AS acheteurs_periode"
        );
        $requete->execute($parametres);
        $ligne = $requete->fetch(PDO::FETCH_ASSOC);

        $acheteurs = (int) $ligne['acheteurs'];
        $fideles = (int) $ligne['fideles'];

        return [
            'nouveaux'     => $nouveaux,
            'acheteurs'    => $acheteurs,
            'fideles'      => $fideles,
            'tauxFidelite' => $acheteurs > 0 ? round($fideles * 100 / $acheteurs, 1) : 0.0,
        ];
    }

    /**
     * Variantes a reapprovisionner : produits en vente (produit actif ET
     * rayon actif, comme dans la boutique) dont une taille /
     * couleur est epuisee ou presque (au plus Variante::SEUIL_STOCK_FAIBLE
     * pieces), les plus urgentes d'abord.
     */
    public static function stocksAReapprovisionner(PDO $pdo)
    {
        $requete = $pdo->prepare(
            'SELECT p.PRO_ID, p.PRO_NOM, v.VAR_TAILLE, v.VAR_COULEUR, v.VAR_STOCK
             FROM POM_VARIANTE v
             JOIN POM_PRODUIT p ON p.PRO_ID = v.VAR_PRO_ID
             WHERE v.VAR_DATE_SUPPRESSION IS NULL AND p.PRO_ACTIF = 1
               AND p.PRO_CAT_ID IN (SELECT CAT_ID FROM POM_CATEGORIE WHERE CAT_ACTIF = 1)
               AND v.VAR_STOCK <= ?
             ORDER BY v.VAR_STOCK, p.PRO_NOM'
        );
        $requete->execute([Variante::SEUIL_STOCK_FAIBLE]);

        return $requete->fetchAll(PDO::FETCH_ASSOC);
    }
}
