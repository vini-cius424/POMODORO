<?php
// =====================================================================
// POMODORO STORE - Classe Client
//
// Correspond a la table POM_CLIENT et a la classe Client du diagramme.
// La classe porte ses donnees ET ses requetes SQL (patron Active Record) :
// c'est le choix d'architecture retenu pour le projet.
//
// Regle de gestion 8 : le mot de passe est hache en PHP, jamais en SQL,
// et jamais stocke en clair - meme pendant les tests.
//
// Sources consultees :
//  - password_hash : https://www.php.net/manual/fr/function.password-hash.php
//  - requetes preparees PDO : https://www.php.net/manual/fr/pdo.prepare.php
// =====================================================================

class Client
{
    // Proprietes privees : on ne peut pas les modifier depuis l'exterieur,
    // seulement les lire avec les getters plus bas.
    private $id;
    private $nom;
    private $prenom;
    private $email;
    private $motDePasseHash;
    private $telephone;
    private $emailConfirme = false;

    // Le constructeur recoit le mot de passe EN CLAIR, tel que le visiteur
    // l'a tape, et le hache immediatement. L'objet ne contient donc jamais
    // le mot de passe lisible.
    public function __construct($nom, $prenom, $email, $motDePasse, $telephone = null)
    {
        $this->nom    = trim($nom);
        $this->prenom = trim($prenom);

        // strtolower : "Jean@x.ch" et "jean@x.ch" doivent etre le meme compte.
        $this->email = strtolower(trim($email));

        // Le telephone est facultatif : une chaine vide devient NULL en base.
        if ($telephone === null || trim($telephone) === "") {
            $this->telephone = null;
        } else {
            $this->telephone = trim($telephone);
        }

        // PASSWORD_DEFAULT laisse PHP choisir le meilleur algorithme.
        // Le resultat fait environ 60 caracteres et tient dans VARCHAR(255).
        $this->motDePasseHash = password_hash($motDePasse, PASSWORD_DEFAULT);
    }

    // --- Getters -----------------------------------------------------

    public function getId()
    {
        return $this->id;
    }

    public function getNom()
    {
        return $this->nom;
    }

    public function getPrenom()
    {
        return $this->prenom;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function getTelephone()
    {
        return $this->telephone;
    }

    public function aConfirmeSonEmail()
    {
        return $this->emailConfirme;
    }

    // --- Requetes ----------------------------------------------------

    // Methode statique : on n'a pas besoin de creer un objet Client pour
    // savoir si un email est deja pris. On l'appelle avec
    //     Client::emailDejaUtilise($pdo, "jean@exemple.ch")
    //
    // $idAIgnorer (facultatif) : sert a la modification du profil. Un
    // client qui garde son propre email ne doit pas etre bloque par
    // "email deja utilise" : on exclut donc sa propre ligne.
    // A l'inscription, on ne le donne pas : il vaut null.
    public static function emailDejaUtilise($pdo, $email, $idAIgnorer = null)
    {
        $sql = "SELECT CLI_ID FROM POM_CLIENT WHERE CLI_EMAIL = :email AND CLI_ID <> :ignorer";

        // prepare() + execute() : le mot de passe et l'email ne sont jamais
        // colles dans le texte de la requete. C'est ce qui empeche les
        // injections SQL.
        // Sans id a ignorer, on met 0 : aucun client n'a l'id 0, donc
        // personne n'est exclu.
        $requete = $pdo->prepare($sql);
        $requete->execute([
            ":email"   => strtolower(trim($email)),
            ":ignorer" => $idAIgnorer ?? 0
        ]);

        // fetch() renvoie false si aucune ligne n'a ete trouvee.
        return $requete->fetch() !== false;
    }

    // Enregistre le client en base et renvoie son nouvel identifiant.
    //
    // CLI_ID est en AUTO_INCREMENT et CLI_DATE_INSCRIPTION comme CLI_ACTIF
    // ont une valeur par defaut : on ne les fournit pas ici, la base s'en
    // charge.
    public function sInscrire($pdo)
    {
        $sql = "INSERT INTO POM_CLIENT
                    (CLI_NOM, CLI_PRENOM, CLI_EMAIL, CLI_MOT_DE_PASSE_HASH, CLI_TELEPHONE)
                VALUES
                    (:nom, :prenom, :email, :hash, :telephone)";

        $requete = $pdo->prepare($sql);
        $requete->execute([
            ":nom"       => $this->nom,
            ":prenom"    => $this->prenom,
            ":email"     => $this->email,
            ":hash"      => $this->motDePasseHash,
            ":telephone" => $this->telephone
        ]);

        // lastInsertId() donne l'identifiant que la base vient de generer.
        $this->id = $pdo->lastInsertId();

        return $this->id;
    }

    // Verifie l'email et le mot de passe pour la connexion. Renvoie
    // l'objet Client si c'est correct et que le compte est actif,
    // sinon null.
    public static function seConnecter($pdo, $email, $motDePasse)
    {
        $sql = "SELECT CLI_ID, CLI_NOM, CLI_PRENOM, CLI_EMAIL, CLI_MOT_DE_PASSE_HASH, CLI_TELEPHONE, CLI_ACTIF,
                       CLI_EMAIL_CONFIRME
                FROM POM_CLIENT WHERE CLI_EMAIL = :email";

        $requete = $pdo->prepare($sql);
        $requete->execute([":email" => strtolower(trim($email))]);
        $ligne = $requete->fetch();

        if (!$ligne || !$ligne["CLI_ACTIF"] || !password_verify($motDePasse, $ligne["CLI_MOT_DE_PASSE_HASH"])) {
            return null;
        }

        // On ne peut pas repasser par le constructeur normal : il hache
        // un mot de passe fourni en clair, alors qu'ici on a deja le
        // hash tel que stocke en base. On cree donc l'objet puis on
        // remplace ses proprietes directement - autorise ici puisqu'on
        // est dans une methode de la classe Client elle-meme.
        $client = new self($ligne["CLI_NOM"], $ligne["CLI_PRENOM"], $ligne["CLI_EMAIL"], "", $ligne["CLI_TELEPHONE"]);
        $client->id = $ligne["CLI_ID"];
        $client->motDePasseHash = $ligne["CLI_MOT_DE_PASSE_HASH"];
        $client->emailConfirme = (bool) $ligne["CLI_EMAIL_CONFIRME"];

        // Le mot de passe est bon, mais l'email peut ne pas etre confirme :
        // c'est la page de connexion qui decide quoi faire (aConfirmeSonEmail).
        return $client;
    }

    // --- Confirmation de l'adresse email -------------------------------

    // Cree un jeton de confirmation valable 24 heures et le renvoie EN
    // CLAIR, pour le mettre dans le lien de l'email. En base, on ne garde
    // que son empreinte SHA-256 : quelqu'un qui lirait la base ne pourrait
    // pas s'en servir pour confirmer le compte a la place du client.
    // Un nouveau jeton remplace le precedent (l'ancien lien ne marche plus).
    public static function nouveauJetonConfirmation($pdo, $id)
    {
        // random_bytes : 32 octets vraiment aleatoires, impossibles a deviner.
        // bin2hex les ecrit en 64 caracteres 0-9 a-f, utilisables dans une URL.
        $jeton = bin2hex(random_bytes(32));

        // L'expiration est calculee par la base (NOW()), comme la
        // verification dans confirmerEmail() : pas de decalage d'heure
        // possible entre PHP et MariaDB.
        $sql = "UPDATE POM_CLIENT
                SET CLI_JETON_CONFIRMATION = :empreinte,
                    CLI_JETON_EXPIRATION = DATE_ADD(NOW(), INTERVAL 24 HOUR)
                WHERE CLI_ID = :id";

        $requete = $pdo->prepare($sql);
        $requete->execute([
            ":empreinte" => hash('sha256', $jeton),
            ":id"        => $id
        ]);

        return $jeton;
    }

    // Recoit le jeton du lien. S'il correspond a un compte actif et n'a
    // pas expire, l'email est confirme et le jeton efface (il ne sert
    // qu'une fois). Renvoie l'objet Client confirme (pour le connecter
    // directement), ou null si le lien est invalide ou expire.
    public static function confirmerEmail($pdo, $jeton)
    {
        $empreinte = hash('sha256', $jeton);

        $requete = $pdo->prepare(
            "SELECT CLI_ID FROM POM_CLIENT
             WHERE CLI_JETON_CONFIRMATION = :empreinte AND CLI_JETON_EXPIRATION > NOW() AND CLI_ACTIF = 1"
        );
        $requete->execute([":empreinte" => $empreinte]);
        $id = $requete->fetchColumn();

        if ($id === false) {
            return null;
        }

        // Le jeton est remis dans le WHERE : si le meme lien est ouvert deux
        // fois en meme temps, une seule des deux requetes le consomme.
        $requete = $pdo->prepare(
            "UPDATE POM_CLIENT
             SET CLI_EMAIL_CONFIRME = 1, CLI_JETON_CONFIRMATION = NULL, CLI_JETON_EXPIRATION = NULL
             WHERE CLI_ID = :id AND CLI_JETON_CONFIRMATION = :empreinte"
        );
        $requete->execute([":id" => $id, ":empreinte" => $empreinte]);

        // rowCount() : nombre de lignes modifiees. 0 = jeton deja utilise.
        if ($requete->rowCount() !== 1) {
            return null;
        }

        return self::trouver($pdo, $id);
    }

    // --- Mot de passe oublie ------------------------------------------

    // Cree un jeton de reinitialisation valable 1 heure pour le compte
    // actif qui a cet email. Renvoie ['jeton', 'prenom', 'email'] (le
    // jeton EN CLAIR, pour le lien de l'email ; en base, seulement son
    // empreinte SHA-256), ou null si :
    //  - aucun compte actif n'a cet email ;
    //  - un lien a deja ete demande il y a moins de 2 minutes (le jeton
    //    precedent expire alors dans plus de 58 minutes) : pas de
    //    bombardement d'emails sur la boite de quelqu'un.
    public static function nouveauJetonMotDePasse($pdo, $email)
    {
        $requete = $pdo->prepare(
            "SELECT CLI_ID, CLI_PRENOM, CLI_EMAIL FROM POM_CLIENT WHERE CLI_EMAIL = :email AND CLI_ACTIF = 1"
        );
        $requete->execute([":email" => strtolower(trim($email))]);
        $ligne = $requete->fetch();

        if (!$ligne) {
            return null;
        }

        $jeton = bin2hex(random_bytes(32));

        $requete = $pdo->prepare(
            "UPDATE POM_CLIENT
             SET CLI_JETON_MDP = :empreinte,
                 CLI_JETON_MDP_EXPIRATION = DATE_ADD(NOW(), INTERVAL 1 HOUR)
             WHERE CLI_ID = :id
               AND (CLI_JETON_MDP_EXPIRATION IS NULL
                    OR CLI_JETON_MDP_EXPIRATION < DATE_ADD(NOW(), INTERVAL 58 MINUTE))"
        );
        $requete->execute([":empreinte" => hash('sha256', $jeton), ":id" => $ligne["CLI_ID"]]);

        if ($requete->rowCount() !== 1) {
            return null; // demande trop rapprochee
        }

        return ['jeton' => $jeton, 'prenom' => $ligne["CLI_PRENOM"], 'email' => $ligne["CLI_EMAIL"]];
    }

    // Le lien de reinitialisation est-il encore valable (jeton connu, pas
    // expire, compte actif) ? Sert a afficher ou non le formulaire.
    public static function jetonMotDePasseValide($pdo, $jeton)
    {
        $requete = $pdo->prepare(
            "SELECT CLI_ID FROM POM_CLIENT
             WHERE CLI_JETON_MDP = :empreinte AND CLI_JETON_MDP_EXPIRATION > NOW() AND CLI_ACTIF = 1"
        );
        $requete->execute([":empreinte" => hash('sha256', $jeton)]);

        return $requete->fetch() !== false;
    }

    // Remplace le mot de passe du compte qui correspond au jeton, puis
    // efface le jeton (le lien ne sert qu'une fois). Comme le client a
    // prouve qu'il recoit les emails de cette adresse, elle est aussi
    // consideree comme confirmee. Renvoie true si c'est fait.
    public static function reinitialiserMotDePasse($pdo, $jeton, $nouveauMotDePasse)
    {
        $requete = $pdo->prepare(
            "UPDATE POM_CLIENT
             SET CLI_MOT_DE_PASSE_HASH = :hash, CLI_EMAIL_CONFIRME = 1,
                 CLI_JETON_MDP = NULL, CLI_JETON_MDP_EXPIRATION = NULL
             WHERE CLI_JETON_MDP = :empreinte AND CLI_JETON_MDP_EXPIRATION > NOW() AND CLI_ACTIF = 1"
        );
        $requete->execute([
            ":hash"      => password_hash($nouveauMotDePasse, PASSWORD_DEFAULT),
            ":empreinte" => hash('sha256', $jeton)
        ]);

        return $requete->rowCount() === 1;
    }

    // Confirme l'email sans jeton : utilise quand Brevo n'est pas
    // configure sur le poste (aucun email ne pourrait etre envoye).
    public static function marquerEmailConfirme($pdo, $id)
    {
        $requete = $pdo->prepare("UPDATE POM_CLIENT SET CLI_EMAIL_CONFIRME = 1 WHERE CLI_ID = :id");
        $requete->execute([":id" => $id]);
    }

    // --- Gestion du profil (tache 14) ----------------------------------

    // Relit un client a partir de son identifiant (celui de la session).
    // Renvoie l'objet Client, ou null s'il n'existe pas ou a ete desactive.
    public static function trouver($pdo, $id)
    {
        $sql = "SELECT CLI_ID, CLI_NOM, CLI_PRENOM, CLI_EMAIL, CLI_MOT_DE_PASSE_HASH, CLI_TELEPHONE
                FROM POM_CLIENT WHERE CLI_ID = :id AND CLI_ACTIF = 1";

        $requete = $pdo->prepare($sql);
        $requete->execute([":id" => $id]);
        $ligne = $requete->fetch();

        if (!$ligne) {
            return null;
        }

        // Meme technique que seConnecter() : le hash vient de la base.
        $client = new self($ligne["CLI_NOM"], $ligne["CLI_PRENOM"], $ligne["CLI_EMAIL"], "", $ligne["CLI_TELEPHONE"]);
        $client->id = $ligne["CLI_ID"];
        $client->motDePasseHash = $ligne["CLI_MOT_DE_PASSE_HASH"];

        return $client;
    }

    // Enregistre les nouvelles coordonnees du client.
    // Les memes regles qu'a l'inscription s'appliquent : email en
    // minuscules, telephone vide -> NULL.
    public static function modifierCoordonnees($pdo, $id, $nom, $prenom, $email, $telephone)
    {
        if (trim($telephone) === "") {
            $telephone = null;
        } else {
            $telephone = trim($telephone);
        }

        $sql = "UPDATE POM_CLIENT
                SET CLI_NOM = :nom, CLI_PRENOM = :prenom, CLI_EMAIL = :email, CLI_TELEPHONE = :telephone
                WHERE CLI_ID = :id";

        $requete = $pdo->prepare($sql);
        $requete->execute([
            ":nom"       => trim($nom),
            ":prenom"    => trim($prenom),
            ":email"     => strtolower(trim($email)),
            ":telephone" => $telephone,
            ":id"        => $id
        ]);
    }

    // Verifie que le mot de passe saisi est bien celui du client.
    // Sert avant de changer le mot de passe ou de fermer le compte :
    // quelqu'un qui trouverait un ordinateur reste connecte ne peut
    // rien faire sans connaitre le mot de passe actuel.
    public function motDePasseCorrect($motDePasse)
    {
        return password_verify($motDePasse, $this->motDePasseHash);
    }

    // Remplace le mot de passe. Comme a l'inscription, seul le hash est
    // enregistre, jamais le mot de passe en clair.
    public static function changerMotDePasse($pdo, $id, $nouveauMotDePasse)
    {
        $sql = "UPDATE POM_CLIENT SET CLI_MOT_DE_PASSE_HASH = :hash WHERE CLI_ID = :id";

        $requete = $pdo->prepare($sql);
        $requete->execute([
            ":hash" => password_hash($nouveauMotDePasse, PASSWORD_DEFAULT),
            ":id"   => $id
        ]);
    }

    // Ferme le compte par suppression LOGIQUE : la ligne reste en base,
    // mais CLI_ACTIF passe a 0. seConnecter() et trouver() ignorent deja
    // les comptes inactifs : le client ne peut donc plus se connecter.
    // On garde la ligne pour ne pas casser les futures commandes qui
    // pointeront vers ce client (historique des ventes du mandant).
    public static function desactiver($pdo, $id)
    {
        $sql = "UPDATE POM_CLIENT SET CLI_ACTIF = 0 WHERE CLI_ID = :id";

        $requete = $pdo->prepare($sql);
        $requete->execute([":id" => $id]);
    }
}
