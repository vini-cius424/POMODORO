<?php

class Administrateur
{
    public $id;
    public $nom;
    public $prenom;
    public $email;

    private function __construct($id, $nom, $prenom, $email)
    {
        $this->id = $id;
        $this->nom = $nom;
        $this->prenom = $prenom;
        $this->email = $email;
    }

    /**
     * Vérifie l'email et le mot de passe, retourne l'administrateur si
     * c'est correct, sinon null.
     */
    public static function seConnecter(PDO $pdo, $email, $motDePasse)
    {
        $requete = $pdo->prepare(
            'SELECT ADM_ID, ADM_NOM, ADM_PRENOM, ADM_EMAIL, ADM_MOT_DE_PASSE_HASH
             FROM POM_ADMINISTRATEUR WHERE ADM_EMAIL = ?'
        );
        $requete->execute([$email]);
        $ligne = $requete->fetch(PDO::FETCH_ASSOC);

        // password_verify() compare le mot de passe saisi au hash stocké
        // en base ; le mot de passe en clair n'est jamais comparé ni
        // stocké tel quel.
        if (!$ligne || !password_verify($motDePasse, $ligne['ADM_MOT_DE_PASSE_HASH'])) {
            return null;
        }

        return new self($ligne['ADM_ID'], $ligne['ADM_NOM'], $ligne['ADM_PRENOM'], $ligne['ADM_EMAIL']);
    }
}
