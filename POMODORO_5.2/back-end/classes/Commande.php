<?php
// =====================================================================
// POMODORO STORE - Classe Commande
//
// Correspond aux tables POM_COMMANDE et POM_LIGNE_COMMANDE.
// Comme les autres classes, elle porte ses propres requetes SQL
// (patron Active Record).
//
// Passer une commande = plusieurs ecritures qui doivent reussir
// ENSEMBLE ou pas du tout :
//   1. enregistrer l'adresse de livraison ;
//   2. creer la commande ;
//   3. copier chaque ligne du panier dans la commande ;
//   4. baisser le stock de chaque variante ;
//   5. vider le panier.
// On utilise donc une TRANSACTION (possible depuis le passage en InnoDB).
//
// Sources consultees :
//  - transactions PDO : https://www.php.net/manual/fr/pdo.transactions.php
//  - rowCount : https://www.php.net/manual/fr/pdostatement.rowcount.php
// =====================================================================

require_once __DIR__ . '/Panier.php';

class Commande
{
    // Les statuts possibles (contrainte CK_COM_STATUT du script SQL) et
    // le texte affiche pour chacun. Doit rester synchronise avec la base.
    const STATUTS = [
        'EN_ATTENTE'     => 'En attente',
        'VALIDEE'        => 'Validée',
        'EN_PREPARATION' => 'En préparation',
        'EXPEDIEE'       => 'Expédiée',
        'LIVREE'         => 'Livrée',
        'ANNULEE'        => 'Annulée'
    ];

    // Code promo de bienvenue (pop-up du site) : -20 % sur les articles,
    // frais de port non compris, valable une seule fois, sur la premiere
    // commande d'un compte client. Un seul code : il est ecrit ici plutot
    // que dans une table, qui n'aurait qu'une ligne.
    const CODE_PROMO_BIENVENUE = 'POMO20';
    const TAUX_PROMO_BIENVENUE = 0.20;

    // Verifie un code promo pour ce client. Renvoie '' si le code est
    // valable, sinon le message a afficher au client.
    public static function erreurCodePromo($pdo, $clientId, $code)
    {
        if (strtoupper(trim($code)) !== self::CODE_PROMO_BIENVENUE) {
            return "Ce code promo n'existe pas.";
        }

        if (self::nombrePourClient($pdo, $clientId) > 0) {
            return "Le code " . self::CODE_PROMO_BIENVENUE . " est réservé à votre première commande.";
        }

        return '';
    }

    // TVA suisse, taux normal (vetements) depuis le 01.01.2024.
    // Les prix du site sont TTC (obligatoire pour les particuliers,
    // ordonnance sur l'indication des prix) : la TVA est deja DANS le
    // prix, on ne l'ajoute pas, on l'affiche seulement.
    const TAUX_TVA = 0.081;

    // Part de TVA contenue dans un montant TTC, arrondie au centime.
    // 35.00 CHF TTC = 32.38 HT + 2.62 de TVA : 35 x 0.081 / 1.081.
    // Appliquee au total de la commande (frais de port compris : ils
    // suivent le taux de la marchandise, remise deja deduite).
    public static function tvaComprise($montantTtc)
    {
        return round($montantTtc * self::TAUX_TVA / (1 + self::TAUX_TVA), 2);
    }

    // Texte du taux pour l'affichage : "8.1 %".
    public static function libelleTauxTva()
    {
        return (self::TAUX_TVA * 100) . ' %';
    }

    // Montant de la remise, arrondi au centime.
    public static function remisePromo($totalArticles)
    {
        return round($totalArticles * self::TAUX_PROMO_BIENVENUE, 2);
    }

    // Frais de port en CHF selon le mode de livraison.
    // Le retrait en magasin est toujours gratuit (contrainte CK_COM_RETRAIT).
    public static function fraisDePort($mode)
    {
        if ($mode === "STANDARD") {
            return 7.00;
        }
        if ($mode === "EXPRESS") {
            return 15.00;
        }
        return 0.00; // RETRAIT_MAGASIN
    }

    // Enregistre la commande du client a partir de son panier.
    // Renvoie le numero de commande (ex. "POM-000012"), ou null si
    // quelque chose a echoue (panier vide, stock insuffisant...).
    // Dans ce cas, RIEN n'a ete ecrit en base grace au rollBack().
    //
    // L'adresse de livraison :
    //  - $adresseId renseigne : une adresse deja enregistree du client
    //    (Mes adresses). $rue, $npa et $ville sont alors ignores ;
    //  - $adresseId = null : une nouvelle adresse, saisie au moment de
    //    commander. Elle est ajoutee aux adresses du client.
    //
    // $codePromo : '' si le client n'en a pas saisi. Il est reverifie ici
    // (et pas seulement dans le formulaire) : un code non valable annule
    // toute la commande plutot que d'accorder une remise indue.
    public static function passer($pdo, $clientId, $adresseId, $rue, $npa, $ville, $mode, $codePromo = '')
    {
        $lignes = Panier::lignesPourClient($pdo, $clientId);

        if (count($lignes) === 0) {
            return null;
        }

        // Total des articles : prix unitaire x quantite, ligne par ligne.
        $totalArticles = 0;
        foreach ($lignes as $ligne) {
            $totalArticles += $ligne["PRIX_UNITAIRE"] * $ligne["LPA_QUANTITE"];
        }
        $frais = self::fraisDePort($mode);

        $codePromo = strtoupper(trim($codePromo));
        $remise = 0;
        if ($codePromo !== '') {
            if (self::erreurCodePromo($pdo, $clientId, $codePromo) !== '') {
                return null;
            }
            $remise = self::remisePromo($totalArticles);
        }

        // beginTransaction() : a partir d'ici, les ecritures sont
        // "en attente". Elles ne deviennent definitives qu'au commit().
        $pdo->beginTransaction();

        try {
            // 1. L'adresse de livraison.
            if ($adresseId !== null) {
                // Adresse existante : elle doit appartenir a CE client.
                // Sinon, on pourrait livrer a l'adresse d'un autre en
                // changeant juste le numero dans le formulaire.
                $requete = $pdo->prepare(
                    "SELECT ADR_ID FROM POM_ADRESSE
                     WHERE ADR_ID = :id AND ADR_CLI_ID = :client AND ADR_DATE_SUPPRESSION IS NULL"
                );
                $requete->execute([":id" => $adresseId, ":client" => $clientId]);

                if ($requete->fetch() === false) {
                    throw new Exception("Adresse inconnue");
                }
            } else {
                // Nouvelle adresse. Comme dans Adresse::ajouter() : si le
                // client n'en avait encore aucune, elle devient son adresse
                // par defaut.
                $requete = $pdo->prepare("SELECT COUNT(*) FROM POM_ADRESSE WHERE ADR_CLI_ID = :client AND ADR_DATE_SUPPRESSION IS NULL");
                $requete->execute([":client" => $clientId]);
                $premiere = (int) $requete->fetchColumn() === 0;

                $requete = $pdo->prepare(
                    "INSERT INTO POM_ADRESSE (ADR_RUE, ADR_NPA, ADR_VILLE, ADR_PAR_DEFAUT, ADR_CLI_ID)
                     VALUES (:rue, :npa, :ville, :defaut, :client)"
                );
                $requete->execute([
                    ":rue"    => trim($rue),
                    ":npa"    => trim($npa),
                    ":ville"  => trim($ville),
                    ":defaut" => $premiere ? 1 : 0,
                    ":client" => $clientId
                ]);
                $adresseId = $pdo->lastInsertId();
            }

            // 2. La commande.
            // COM_NUMERO est obligatoire et unique, mais on veut le former
            // avec l'identifiant que la base n'a pas encore genere.
            // On met donc un numero provisoire unique (uniqid), puis on
            // le remplace juste apres par le vrai.
            // Total paye = articles - remise + frais de port (RG3).
            // Le code et la remise sont gardes dans la commande : ils
            // restent visibles dans l'historique et cote administration.
            $requete = $pdo->prepare(
                "INSERT INTO POM_COMMANDE
                     (COM_NUMERO, COM_MONTANT_TOTAL, COM_MODE_LIVRAISON, COM_FRAIS_PORT,
                      COM_CODE_PROMO, COM_REMISE, COM_CLI_ID, COM_ADR_ID)
                 VALUES (:numero, :total, :mode, :frais, :code, :remise, :client, :adresse)"
            );
            $requete->execute([
                ":numero"  => uniqid(),
                ":total"   => $totalArticles - $remise + $frais,
                ":mode"    => $mode,
                ":frais"   => $frais,
                ":code"    => $codePromo !== '' ? $codePromo : null,
                ":remise"  => $remise,
                ":client"  => $clientId,
                ":adresse" => $adresseId
            ]);
            $commandeId = $pdo->lastInsertId();

            // sprintf("%06d", 12) donne "000012" : 6 chiffres, completes par des 0.
            $numero = "POM-" . sprintf("%06d", $commandeId);
            $requete = $pdo->prepare("UPDATE POM_COMMANDE SET COM_NUMERO = :numero WHERE COM_ID = :id");
            $requete->execute([":numero" => $numero, ":id" => $commandeId]);

            // 3 et 4. Chaque ligne du panier.
            foreach ($lignes as $ligne) {
                // On baisse le stock SEULEMENT s'il en reste assez :
                // la condition VAR_STOCK >= :quantite est dans le WHERE.
                $requete = $pdo->prepare(
                    "UPDATE POM_VARIANTE SET VAR_STOCK = VAR_STOCK - :quantite
                     WHERE VAR_ID = :variante AND VAR_STOCK >= :quantite2"
                );
                $requete->execute([
                    ":quantite"  => $ligne["LPA_QUANTITE"],
                    ":quantite2" => $ligne["LPA_QUANTITE"],
                    ":variante"  => $ligne["LPA_VAR_ID"]
                ]);

                // rowCount() : nombre de lignes modifiees par l'UPDATE.
                // 0 = le WHERE n'a rien trouve = stock insuffisant.
                // throw lance une erreur qui saute directement au catch.
                if ($requete->rowCount() === 0) {
                    throw new Exception("Stock insuffisant");
                }

                // Le prix est copie dans la ligne : il reste fige meme si
                // le prix du produit change plus tard.
                $requete = $pdo->prepare(
                    "INSERT INTO POM_LIGNE_COMMANDE (LCO_COM_ID, LCO_VAR_ID, LCO_QUANTITE, LCO_PRIX_UNITAIRE)
                     VALUES (:commande, :variante, :quantite, :prix)"
                );
                $requete->execute([
                    ":commande" => $commandeId,
                    ":variante" => $ligne["LPA_VAR_ID"],
                    ":quantite" => $ligne["LPA_QUANTITE"],
                    ":prix"     => $ligne["PRIX_UNITAIRE"]
                ]);
            }

            // 5. On vide le panier (suppression logique des lignes ; on
            // garde le panier lui-meme, vide).
            Panier::vider($pdo, Panier::idPourClient($pdo, $clientId));

            // Tout s'est bien passe : on rend les ecritures definitives.
            $pdo->commit();

            return $numero;
        } catch (Exception $erreur) {
            // Une etape a echoue : rollBack() annule TOUTES les ecritures
            // faites depuis beginTransaction(). La base revient exactement
            // comme avant : pas d'adresse, pas de commande, stock intact.
            $pdo->rollBack();

            return null;
        }
    }

    // Relit une commande pour la page de confirmation.
    // Le client n'obtient que SES commandes : un autre numero dans
    // l'adresse de la page ne renverra rien (null).
    public static function trouverPourClient($pdo, $numero, $clientId)
    {
        $requete = $pdo->prepare(
            "SELECT c.COM_ID, c.COM_NUMERO, c.COM_DATE, c.COM_STATUT, c.COM_DATE_MAJ, c.COM_MONTANT_TOTAL,
                    c.COM_MODE_LIVRAISON, c.COM_FRAIS_PORT, c.COM_CODE_PROMO, c.COM_REMISE,
                    c.COM_PAIEMENT_ID, a.ADR_RUE, a.ADR_NPA, a.ADR_VILLE
             FROM POM_COMMANDE c
             JOIN POM_ADRESSE a ON a.ADR_ID = c.COM_ADR_ID
             WHERE c.COM_NUMERO = :numero AND c.COM_CLI_ID = :client"
        );
        $requete->execute([":numero" => $numero, ":client" => $clientId]);
        $commande = $requete->fetch(PDO::FETCH_ASSOC);

        if ($commande === false) {
            return null;
        }

        return $commande;
    }

    // Toutes les commandes d'un client, de la plus recente a la plus
    // ancienne, pour la page "Mes commandes" de son espace personnel.
    public static function listePourClient($pdo, $clientId)
    {
        $requete = $pdo->prepare(
            "SELECT c.COM_ID, c.COM_NUMERO, c.COM_DATE, c.COM_STATUT, c.COM_DATE_MAJ, c.COM_MONTANT_TOTAL,
                    (SELECT SUM(l.LCO_QUANTITE) FROM POM_LIGNE_COMMANDE l
                     WHERE l.LCO_COM_ID = c.COM_ID) AS NB_ARTICLES
             FROM POM_COMMANDE c
             WHERE c.COM_CLI_ID = :client
             ORDER BY c.COM_DATE DESC, c.COM_ID DESC"
        );
        $requete->execute([":client" => $clientId]);

        return $requete->fetchAll(PDO::FETCH_ASSOC);
    }

    // Nombre de commandes d'un client, sans compter les annulees : un
    // client dont la seule commande a ete annulee n'a en realite jamais
    // rien achete. 0 = "nouveau client" (pop-up -20 % de bienvenue).
    public static function nombrePourClient($pdo, $clientId)
    {
        $requete = $pdo->prepare(
            "SELECT COUNT(*) FROM POM_COMMANDE WHERE COM_CLI_ID = :client AND COM_STATUT <> 'ANNULEE'"
        );
        $requete->execute([":client" => $clientId]);

        return (int) $requete->fetchColumn();
    }

    // --- Espace administration (tache 22) ------------------------------

    // Tris proposes dans la liste de l'administration : cle d'URL => libelle.
    const TRIS_ADMIN = [
        'recent' => "Plus récentes d'abord",
        'ancien' => "Plus anciennes d'abord"
    ];

    // Morceau de WHERE pour la recherche de l'administration : le texte
    // saisi est cherche dans le numero de commande, le nom du client
    // (prenom nom ou nom prenom) et son email. Suppose les alias "c"
    // (commande) et "cl" (client).
    // Renvoie [sql, parametres] ; ['', []] si la recherche est vide.
    private static function filtreRecherche($recherche)
    {
        $recherche = trim($recherche);
        if ($recherche === '') {
            return ['', []];
        }

        // Dans un LIKE, % et _ sont des jokers : addcslashes les "echappe"
        // (les precede d'un antislash) pour qu'un % tape par l'admin soit
        // cherche tel quel.
        $motif = '%' . addcslashes($recherche, '%_\\') . '%';

        return [
            " AND (c.COM_NUMERO LIKE ?
                   OR CONCAT(cl.CLI_PRENOM, ' ', cl.CLI_NOM) LIKE ?
                   OR CONCAT(cl.CLI_NOM, ' ', cl.CLI_PRENOM) LIKE ?
                   OR cl.CLI_EMAIL LIKE ?)",
            [$motif, $motif, $motif, $motif]
        ];
    }

    // Toutes les commandes, avec le nom du client et le nombre d'articles.
    // $statut (facultatif) : ne garder que les commandes de ce statut.
    // Un statut inconnu est ignore : on affiche alors toutes les commandes.
    // $recherche (facultatif) : voir filtreRecherche().
    // $tri : une cle de TRIS_ADMIN ; inconnue = les plus recentes d'abord.
    public static function listePourAdmin($pdo, $statut = null, $recherche = '', $tri = 'recent')
    {
        // Sous-requete : pour chaque commande, on additionne les quantites
        // de ses lignes (2 t-shirts + 1 pull = 3 articles).
        // WHERE 1 = 1 : toujours vrai, il permet d'ajouter ensuite chaque
        // filtre avec un simple " AND ...".
        $sql = "SELECT c.COM_ID, c.COM_NUMERO, c.COM_DATE, c.COM_STATUT, c.COM_MONTANT_TOTAL,
                       c.COM_MODE_LIVRAISON, cl.CLI_PRENOM, cl.CLI_NOM,
                       (SELECT SUM(l.LCO_QUANTITE) FROM POM_LIGNE_COMMANDE l
                        WHERE l.LCO_COM_ID = c.COM_ID) AS NB_ARTICLES
                FROM POM_COMMANDE c
                JOIN POM_CLIENT cl ON cl.CLI_ID = c.COM_CLI_ID
                WHERE 1 = 1";
        $parametres = [];

        // array_key_exists : le statut recu fait-il partie de la liste ?
        // Liste blanche : seul un statut connu est ajoute a la requete.
        if ($statut !== null && array_key_exists($statut, self::STATUTS)) {
            $sql .= " AND c.COM_STATUT = ?";
            $parametres[] = $statut;
        }

        [$sqlRecherche, $parametresRecherche] = self::filtreRecherche($recherche);
        $sql .= $sqlRecherche;
        $parametres = array_merge($parametres, $parametresRecherche);

        // Le sens du tri ne peut pas etre un parametre "?" : il est choisi
        // ici parmi deux textes fixes, jamais copie depuis l'URL.
        $sens = $tri === 'ancien' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY c.COM_DATE $sens, c.COM_ID $sens";

        $requete = $pdo->prepare($sql);
        $requete->execute($parametres);

        return $requete->fetchAll(PDO::FETCH_ASSOC);
    }

    // Une commande complete pour la page de detail de l'admin :
    // commande + client + adresse de livraison.
    // Contrairement a trouverPourClient(), aucune restriction de client :
    // l'administrateur voit toutes les commandes.
    // Renvoie null si l'identifiant n'existe pas.
    public static function trouverPourAdmin($pdo, $id)
    {
        $requete = $pdo->prepare(
            "SELECT c.COM_ID, c.COM_NUMERO, c.COM_DATE, c.COM_STATUT, c.COM_MONTANT_TOTAL,
                    c.COM_MODE_LIVRAISON, c.COM_FRAIS_PORT, c.COM_DATE_MAJ, c.COM_CODE_PROMO, c.COM_REMISE, c.COM_PAIEMENT_ID,
                    cl.CLI_PRENOM, cl.CLI_NOM, cl.CLI_EMAIL, cl.CLI_TELEPHONE,
                    a.ADR_RUE, a.ADR_NPA, a.ADR_VILLE, a.ADR_PAYS
             FROM POM_COMMANDE c
             JOIN POM_CLIENT cl ON cl.CLI_ID = c.COM_CLI_ID
             JOIN POM_ADRESSE a ON a.ADR_ID = c.COM_ADR_ID
             WHERE c.COM_ID = :id"
        );
        $requete->execute([":id" => $id]);
        $commande = $requete->fetch(PDO::FETCH_ASSOC);

        if ($commande === false) {
            return null;
        }

        return $commande;
    }

    // Nombre de commandes par statut, pour les onglets de filtre :
    // ['EN_ATTENTE' => 3, 'EXPEDIEE' => 1, ...]. Un statut sans commande
    // vaut 0.
    // $recherche : la meme que la liste, pour que les nombres des onglets
    // correspondent a ce qui s'affiche quand on clique dessus.
    public static function nombreParStatut($pdo, $recherche = '')
    {
        // On part de 0 pour chaque statut connu...
        $nombres = [];
        foreach (self::STATUTS as $code => $libelle) {
            $nombres[$code] = 0;
        }

        // ... puis GROUP BY compte les commandes de chaque statut present.
        [$sqlRecherche, $parametres] = self::filtreRecherche($recherche);
        $requete = $pdo->prepare(
            "SELECT c.COM_STATUT, COUNT(*) AS NOMBRE
             FROM POM_COMMANDE c
             JOIN POM_CLIENT cl ON cl.CLI_ID = c.COM_CLI_ID
             WHERE 1 = 1 $sqlRecherche
             GROUP BY c.COM_STATUT"
        );
        $requete->execute($parametres);
        $lignes = $requete->fetchAll(PDO::FETCH_ASSOC);

        foreach ($lignes as $ligne) {
            $nombres[$ligne["COM_STATUT"]] = (int) $ligne["NOMBRE"];
        }

        return $nombres;
    }

    // --- Changer le statut / annuler (tache 23) ------------------------

    // Statuts dans lesquels une commande peut encore etre annulee :
    // une fois expediee, ce serait un retour, pas une annulation.
    const STATUTS_ANNULABLES = ['EN_ATTENTE', 'VALIDEE', 'EN_PREPARATION'];

    // Ordre de vie d'une commande (ANNULEE a part : elle passe par
    // annuler()). Une commande ne fait qu'avancer dans cette liste : la
    // faire revenir en arriere fausserait le stock. Exemple : une commande
    // EXPEDIEE remise EN_PREPARATION redeviendrait annulable, et
    // l'annulation remettrait en stock des articles deja partis.
    const ORDRE_STATUTS = ['EN_ATTENTE', 'VALIDEE', 'EN_PREPARATION', 'EXPEDIEE', 'LIVREE'];

    // Statuts vers lesquels une commande peut encore passer : ceux qui
    // viennent APRES son statut actuel. Sert a remplir la liste deroulante
    // de l'administration. Commande annulee ou livree : aucun.
    public static function statutsSuivants($statutActuel)
    {
        $position = array_search($statutActuel, self::ORDRE_STATUTS, true);
        if ($position === false) {
            return [];
        }

        // array_slice : la fin de la liste, a partir du statut suivant.
        return array_slice(self::ORDRE_STATUTS, $position + 1);
    }

    // Change le statut d'une commande et note la date du changement.
    // Renvoie true si c'est fait, false si c'est refuse :
    //  - statut inconnu, ou ANNULEE (l'annulation passe par annuler(),
    //    qui remet aussi le stock) ;
    //  - retour en arriere, ou meme statut (ORDRE_STATUTS) ;
    //  - commande deja annulee : elle ne peut plus changer.
    public static function changerStatut($pdo, $id, $nouveauStatut)
    {
        $position = array_search($nouveauStatut, self::ORDRE_STATUTS, true);
        if ($position === false || $position === 0) {
            // Statut inconnu, ANNULEE, ou EN_ATTENTE (le tout premier
            // statut : on ne peut jamais y revenir).
            return false;
        }

        // Les statuts depuis lesquels on a le droit d'arriver ici : tous
        // ceux qui sont AVANT le nouveau. La condition est dans le WHERE :
        // une commande annulee, ou deja plus avancee, n'est pas modifiee.
        $precedents = array_slice(self::ORDRE_STATUTS, 0, $position);
        // Un "?" par statut precedent : IN (?, ?, ?).
        $marqueurs = implode(', ', array_fill(0, count($precedents), '?'));

        // NOW() : la date et l'heure actuelles, donnees par MariaDB.
        $requete = $pdo->prepare(
            "UPDATE POM_COMMANDE SET COM_STATUT = ?, COM_DATE_MAJ = NOW()
             WHERE COM_ID = ? AND COM_STATUT IN ($marqueurs)"
        );
        $requete->execute(array_merge([$nouveauStatut, $id], $precedents));

        // rowCount() : 0 = aucune ligne modifiee (id inconnu, commande
        // annulee, ou statut actuel deja egal ou plus avance).
        return $requete->rowCount() === 1;
    }

    // Annule une commande et remet ses articles en stock.
    // Possible seulement avant l'expedition (STATUTS_ANNULABLES).
    // Renvoie true si c'est fait, false sinon.
    //
    // Deux ecritures qui doivent reussir ensemble : le statut ET le
    // stock. D'ou une transaction, comme dans passer().
    public static function annuler($pdo, $id)
    {
        $pdo->beginTransaction();

        try {
            // 1. Le statut, seulement si la commande est encore annulable.
            $requete = $pdo->prepare(
                "UPDATE POM_COMMANDE SET COM_STATUT = 'ANNULEE', COM_DATE_MAJ = NOW()
                 WHERE COM_ID = :id AND COM_STATUT IN (:s1, :s2, :s3)"
            );
            $requete->execute([
                ":id" => $id,
                ":s1" => self::STATUTS_ANNULABLES[0],
                ":s2" => self::STATUTS_ANNULABLES[1],
                ":s3" => self::STATUTS_ANNULABLES[2]
            ]);

            // 0 ligne modifiee : id inconnu, ou commande deja expediee,
            // livree ou annulee. On ne touche surtout pas au stock.
            if ($requete->rowCount() === 0) {
                throw new Exception("Commande non annulable");
            }

            // 2. Le stock : chaque article commande est rendu.
            $requete = $pdo->prepare(
                "SELECT LCO_VAR_ID, LCO_QUANTITE FROM POM_LIGNE_COMMANDE WHERE LCO_COM_ID = :commande"
            );
            $requete->execute([":commande" => $id]);
            $lignes = $requete->fetchAll(PDO::FETCH_ASSOC);

            foreach ($lignes as $ligne) {
                $requete = $pdo->prepare(
                    "UPDATE POM_VARIANTE SET VAR_STOCK = VAR_STOCK + :quantite WHERE VAR_ID = :variante"
                );
                $requete->execute([
                    ":quantite" => $ligne["LCO_QUANTITE"],
                    ":variante" => $ligne["LCO_VAR_ID"]
                ]);
            }

            $pdo->commit();
            return true;
        } catch (Exception $erreur) {
            $pdo->rollBack();
            return false;
        }
    }

    // --- Paiement Stripe ------------------------------------------------

    // Garde le numero de la session de paiement Stripe dans la commande.
    // Il servira, au retour du client, a retrouver CETTE commande.
    public static function enregistrerSessionPaiement($pdo, $commandeId, $sessionId)
    {
        $requete = $pdo->prepare("UPDATE POM_COMMANDE SET COM_PAIEMENT_ID = :session WHERE COM_ID = :id");
        $requete->execute([":session" => $sessionId, ":id" => $commandeId]);
    }

    // Retrouve la commande liee a une session de paiement Stripe.
    // Renvoie null si aucune commande ne porte ce numero de session.
    public static function trouverParSessionPaiement($pdo, $sessionId)
    {
        $requete = $pdo->prepare(
            "SELECT COM_ID, COM_NUMERO, COM_STATUT, COM_MONTANT_TOTAL, COM_CLI_ID
             FROM POM_COMMANDE WHERE COM_PAIEMENT_ID = :session"
        );
        $requete->execute([":session" => $sessionId]);
        $commande = $requete->fetch(PDO::FETCH_ASSOC);

        return $commande !== false ? $commande : null;
    }

    // Remet les articles d'une commande dans le panier du client.
    // Sert quand le client abandonne le paiement : la commande est
    // annulee, mais il retrouve son panier comme avant pour reessayer.
    public static function remettreAuPanier($pdo, $commandeId, $clientId)
    {
        $requete = $pdo->prepare(
            "SELECT LCO_VAR_ID, LCO_QUANTITE FROM POM_LIGNE_COMMANDE WHERE LCO_COM_ID = :commande"
        );
        $requete->execute([":commande" => $commandeId]);

        $panierId = Panier::idPourClient($pdo, $clientId);
        foreach ($requete->fetchAll(PDO::FETCH_ASSOC) as $ligne) {
            Panier::ajouterVariante($pdo, $panierId, $ligne["LCO_VAR_ID"], (int) $ligne["LCO_QUANTITE"]);
        }
    }

    // Les articles d'une commande, pour le recapitulatif.
    // LCO_SOUS_TOTAL est une colonne generee : la base la calcule seule.
    public static function lignes($pdo, $commandeId)
    {
        $requete = $pdo->prepare(
            "SELECT l.LCO_QUANTITE, l.LCO_PRIX_UNITAIRE, l.LCO_SOUS_TOTAL,
                    v.VAR_TAILLE, v.VAR_COULEUR, p.PRO_NOM
             FROM POM_LIGNE_COMMANDE l
             JOIN POM_VARIANTE v ON v.VAR_ID = l.LCO_VAR_ID
             JOIN POM_PRODUIT p  ON p.PRO_ID = v.VAR_PRO_ID
             WHERE l.LCO_COM_ID = :commande"
        );
        $requete->execute([":commande" => $commandeId]);

        return $requete->fetchAll(PDO::FETCH_ASSOC);
    }
}
