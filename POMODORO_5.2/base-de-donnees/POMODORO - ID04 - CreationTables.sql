-- =====================================================================
-- POMODORO STORE - Creation des tables (version complete)
-- SGBD : MySQL / MariaDB  (a executer dans phpMyAdmin, dans la base
-- "pomodoro")
--
-- Ce script contient TOUT le schema a jour, en un seul fichier.
--
-- ATTENTION : le script commence par supprimer les tables. Le rejouer
-- efface toutes les donnees (clients, produits, commandes...).
--
-- Convention de nommage des FK : prefixe de la table qui recoit
--   (ex. PRO_CAT_ID dans POM_PRODUIT pointe vers POM_CATEGORIE.CAT_ID)
-- Booleens : TINYINT(1) contraint a 0/1
-- Identifiants : AUTO_INCREMENT (les sequences n'existent pas en MySQL)
-- Ordre de creation : par niveaux de dependance
-- =====================================================================


/* =====================================================================
   REGLES DE GESTION NON IMPLANTABLES A LA CREATION DES TABLES

   1. RG1 - Ne commander qu'une variante en stock
      Contrainte inter-tables : valider POM_LIGNE_COMMANDE impose de lire
      VAR_STOCK dans POM_VARIANTE. Un CHECK ne consulte que sa propre table.

   2. RG2 - Decrementer le stock a la validation de la commande
      Comportement reactif : une contrainte bloque une action, elle ne
      declenche pas de mise a jour sur d'autres lignes.

   3. RG3 - COM_MONTANT_TOTAL = somme des lignes - COM_REMISE + frais de port
      Fonction d'agregation (SUM) : interdite dans un CHECK.

   4. RG4 - Une seule image principale par produit
      Verification multi-lignes : un CHECK ne peut pas verifier qu'aucune
      autre ligne ne porte deja IMG_EST_PRINCIPAL = 1 pour ce produit.

   5. RG5 - Un produit possede au moins une variante
      Cardinalite minimale 1..* cote enfant : le produit et sa premiere
      variante ne peuvent pas etre inseres dans la meme instruction.

   6. RG6 - LCO_PRIX_UNITAIRE fige PRO_PRIX au moment de l'achat
      Recopie reactive a l'insertion, puis independance definitive.

   7. RG7 - Generation du numero de commande (POM-000042)
      Construit a partir de COM_ID, qui n'existe qu'apres l'insertion :
      releve d'un trigger ou du PHP (Commande::passer).

   8. RG8 - Un avis ne peut porter que sur un produit deja achete
      Contrainte inter-tables : impose de lire POM_LIGNE_COMMANDE.

   CONCLUSION : ces regles relevent de TRIGGERS ou de la logique
   applicative PHP.
   ===================================================================== */


-- =====================================================================
-- SUPPRESSION (ordre inverse des dependances)
-- IF EXISTS evite l'erreur 1051 a la premiere execution et rend le
-- script rejouable autant de fois que necessaire.
-- =====================================================================
DROP TABLE IF EXISTS POM_LIGNE_COMMANDE;
DROP TABLE IF EXISTS POM_LIGNE_PANIER;
DROP TABLE IF EXISTS POM_COMMANDE;
DROP TABLE IF EXISTS POM_AVIS;
DROP TABLE IF EXISTS POM_IMAGEPRODUITS;
DROP TABLE IF EXISTS POM_VARIANTE;
DROP TABLE IF EXISTS POM_PANIER;
DROP TABLE IF EXISTS POM_ADRESSE;
DROP TABLE IF EXISTS POM_MESSAGE_CONTACT;
DROP TABLE IF EXISTS POM_PRODUIT;
DROP TABLE IF EXISTS POM_CLIENT;
DROP TABLE IF EXISTS POM_ADMINISTRATEUR;
DROP TABLE IF EXISTS POM_CATEGORIE;


-- =====================================================================
-- NIVEAU 1 : tables sans aucune cle etrangere
-- =====================================================================

CREATE TABLE POM_CATEGORIE (
    CAT_ID INT NOT NULL AUTO_INCREMENT,
    CAT_NOM VARCHAR(100) NOT NULL,
    CAT_DESCRIPTION VARCHAR(500) NULL,
    CAT_ORDRE INT NOT NULL DEFAULT 0,
    CAT_ACTIF TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT PK_CATEGORIE PRIMARY KEY (CAT_ID),
    CONSTRAINT UQ_CAT_NOM UNIQUE (CAT_NOM),
    CONSTRAINT CK_CAT_ORDRE CHECK (CAT_ORDRE >= 0),
    CONSTRAINT CK_CAT_ACTIF CHECK (CAT_ACTIF IN (0, 1))
);

-- Une categorie ne se supprime pas, elle se desactive (CAT_ACTIF = 0) :
-- elle disparait alors des menus et ses produits ne sont plus vendus.

CREATE TABLE POM_ADMINISTRATEUR (
    ADM_ID INT NOT NULL AUTO_INCREMENT,
    ADM_NOM VARCHAR(100) NOT NULL,
    ADM_PRENOM VARCHAR(100) NOT NULL,
    ADM_EMAIL VARCHAR(191) NOT NULL,
    ADM_MOT_DE_PASSE_HASH VARCHAR(255) NOT NULL,
    CONSTRAINT PK_ADMINISTRATEUR PRIMARY KEY (ADM_ID),
    CONSTRAINT UQ_ADM_EMAIL UNIQUE (ADM_EMAIL),
    CONSTRAINT CK_ADM_EMAIL CHECK (ADM_EMAIL LIKE '%@%.%')
);

CREATE TABLE POM_CLIENT (
    CLI_ID INT NOT NULL AUTO_INCREMENT,
    CLI_NOM VARCHAR(100) NOT NULL,
    CLI_PRENOM VARCHAR(100) NOT NULL,
    CLI_EMAIL VARCHAR(191) NOT NULL,
    CLI_MOT_DE_PASSE_HASH VARCHAR(255) NOT NULL,
    CLI_TELEPHONE VARCHAR(30) NULL,
    CLI_DATE_INSCRIPTION DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CLI_ACTIF TINYINT(1) NOT NULL DEFAULT 1,
    CLI_EMAIL_CONFIRME TINYINT(1) NOT NULL DEFAULT 0,
    CLI_JETON_CONFIRMATION CHAR(64) NULL,
    CLI_JETON_EXPIRATION DATETIME NULL,
    CLI_JETON_MDP CHAR(64) NULL,
    CLI_JETON_MDP_EXPIRATION DATETIME NULL,
    CONSTRAINT PK_CLIENT PRIMARY KEY (CLI_ID),
    CONSTRAINT UQ_CLI_EMAIL UNIQUE (CLI_EMAIL),
    CONSTRAINT UQ_CLI_JETON UNIQUE (CLI_JETON_CONFIRMATION),
    CONSTRAINT UQ_CLI_JETON_MDP UNIQUE (CLI_JETON_MDP),
    CONSTRAINT CK_CLI_ACTIF CHECK (CLI_ACTIF IN (0, 1)),
    CONSTRAINT CK_CLI_EMAIL_CONFIRME CHECK (CLI_EMAIL_CONFIRME IN (0, 1)),
    CONSTRAINT CK_CLI_EMAIL CHECK (CLI_EMAIL LIKE '%@%.%')
);

-- Confirmation de l'email (envoye par Brevo) : CLI_JETON_CONFIRMATION
-- contient l'empreinte SHA-256 du jeton du lien, jamais le jeton lui-meme.
-- UQ_CLI_JETON sert aussi d'index pour retrouver le compte a partir du
-- lien ; plusieurs NULL restent autorises.
-- Mot de passe oublie : meme principe avec CLI_JETON_MDP (empreinte du
-- jeton du lien, valable 1 heure). Jeton distinct de la confirmation :
-- un lien de confirmation ne permet jamais de changer le mot de passe.


-- =====================================================================
-- NIVEAU 2 : dependent du niveau 1
-- =====================================================================

CREATE TABLE POM_PRODUIT (
    PRO_ID INT NOT NULL AUTO_INCREMENT,
    PRO_REFERENCE VARCHAR(50) NOT NULL,
    PRO_NOM VARCHAR(150) NOT NULL,
    PRO_DESCRIPTION TEXT NULL,
    PRO_PRIX DECIMAL(8, 2) NOT NULL,
    PRO_PRIX_PROMO DECIMAL(8, 2) NULL,
    PRO_ACTIF TINYINT(1) NOT NULL DEFAULT 0,
    PRO_DATECREATION DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRO_TYPE VARCHAR(30) NOT NULL,
    PRO_MARQUE VARCHAR(100) NULL,
    PRO_SOUS_CATEGORIE VARCHAR(100) NULL,
    PRO_MATIERE VARCHAR(100) NULL,
    PRO_SAISON VARCHAR(30) NULL,
    PRO_CAT_ID INT NOT NULL,
    PRO_ADM_ID INT NULL,
    CONSTRAINT PK_PRODUIT PRIMARY KEY (PRO_ID),
    CONSTRAINT UQ_PRO_REF UNIQUE (PRO_REFERENCE),
    CONSTRAINT CK_PRO_ACTIF CHECK (PRO_ACTIF IN (0, 1)),
    CONSTRAINT CK_PRO_PRIX CHECK (PRO_PRIX >= 0),
    CONSTRAINT CK_PRO_PRIX_PROMO CHECK (PRO_PRIX_PROMO IS NULL OR PRO_PRIX_PROMO >= 0),
    CONSTRAINT CK_PRO_TYPE CHECK (PRO_TYPE IN ('TSHIRT', 'PULL', 'SWEAT', 'VESTE', 'PANTALON', 'SHORT', 'CASQUETTE', 'BONNET', 'SAC', 'CHAUSSETTES')),
    CONSTRAINT CK_PRO_SAISON CHECK (PRO_SAISON IS NULL OR PRO_SAISON IN ('PRINTEMPS_ETE', 'AUTOMNE_HIVER', 'TOUTE_SAISON')),
    CONSTRAINT FK_PRO_CAT FOREIGN KEY (PRO_CAT_ID) REFERENCES POM_CATEGORIE (CAT_ID),
    CONSTRAINT FK_PRO_ADM FOREIGN KEY (PRO_ADM_ID) REFERENCES POM_ADMINISTRATEUR (ADM_ID) ON DELETE SET NULL
);

-- PRO_ACTIF vaut 0 par defaut : un produit nait invisible et doit etre
-- publie explicitement. Un produit ne se supprime pas, il se desactive.
-- PRO_CAT_ID = le rayon (Homme, Femme, Enfant), PRO_TYPE = la nature du
-- vetement. Un t-shirt existe dans plusieurs rayons, un rayon contient
-- plusieurs types : aucun des deux ne se deduit de l'autre.
-- PRO_ADM_ID est nullable : supprimer un administrateur ne doit pas
-- entrainer la perte de son catalogue.

CREATE TABLE POM_ADRESSE (
    ADR_ID INT NOT NULL AUTO_INCREMENT,
    ADR_LIBELLE VARCHAR(100) NULL,
    ADR_RUE VARCHAR(200) NOT NULL,
    ADR_NPA VARCHAR(10) NOT NULL,
    ADR_VILLE VARCHAR(100) NOT NULL,
    ADR_PAYS VARCHAR(100) NOT NULL DEFAULT 'Suisse',
    ADR_PAR_DEFAUT TINYINT(1) NOT NULL DEFAULT 0,
    ADR_CLI_ID INT NOT NULL,
    ADR_DATE_SUPPRESSION DATETIME NULL,
    CONSTRAINT PK_ADRESSE PRIMARY KEY (ADR_ID),
    CONSTRAINT CK_ADR_PAR_DEFAUT CHECK (ADR_PAR_DEFAUT IN (0, 1)),
    CONSTRAINT FK_ADR_CLI FOREIGN KEY (ADR_CLI_ID) REFERENCES POM_CLIENT (CLI_ID)
);

-- Pas de cascade : une adresse referencee par une commande passee ne
-- doit pas disparaitre avec le compte client.
-- ADR_DATE_SUPPRESSION : suppression logique (NULL = adresse visible).
-- Une adresse supprimee reste lisible par les commandes qui l'utilisent.

CREATE TABLE POM_PANIER (
    PAN_ID INT NOT NULL AUTO_INCREMENT,
    PAN_DATE_CREATION DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PAN_DATE_MAJ DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PAN_CLI_ID INT NOT NULL,
    CONSTRAINT PK_PANIER PRIMARY KEY (PAN_ID),
    CONSTRAINT UQ_PAN_CLI UNIQUE (PAN_CLI_ID),
    CONSTRAINT FK_PAN_CLI FOREIGN KEY (PAN_CLI_ID) REFERENCES POM_CLIENT (CLI_ID) ON DELETE CASCADE
);

-- UQ_PAN_CLI traduit la cardinalite 0..1 : un seul panier par client.
-- PAN_DATE_MAJ se met a jour toute seule grace a ON UPDATE.

CREATE TABLE POM_MESSAGE_CONTACT (
    MSG_ID INT NOT NULL AUTO_INCREMENT,
    MSG_NOM VARCHAR(150) NOT NULL,
    MSG_EMAIL VARCHAR(191) NOT NULL,
    MSG_SUJET VARCHAR(20) NOT NULL,
    MSG_NUMERO_COMMANDE VARCHAR(50) NULL,
    MSG_MESSAGE TEXT NOT NULL,
    MSG_DATE DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    MSG_STATUT VARCHAR(20) NOT NULL DEFAULT 'NOUVEAU',
    MSG_DATE_MAJ DATETIME NULL,
    MSG_REPONSE TEXT NULL,
    MSG_DATE_REPONSE DATETIME NULL,
    MSG_CLI_ID INT NULL,
    CONSTRAINT PK_MESSAGE_CONTACT PRIMARY KEY (MSG_ID),
    CONSTRAINT CK_MSG_SUJET CHECK (MSG_SUJET IN ('COMMANDE', 'PRODUIT', 'LIVRAISON', 'RETOUR', 'AUTRE')),
    CONSTRAINT CK_MSG_STATUT CHECK (MSG_STATUT IN ('NOUVEAU', 'LU', 'TRAITE')),
    CONSTRAINT CK_MSG_EMAIL CHECK (MSG_EMAIL LIKE '%@%.%'),
    CONSTRAINT FK_MSG_CLI FOREIGN KEY (MSG_CLI_ID) REFERENCES POM_CLIENT (CLI_ID) ON DELETE SET NULL
);

-- Formulaire de contact du site (besoin BM-05). Un visiteur sans compte
-- peut ecrire : MSG_NOM et MSG_EMAIL sont donc recopies dans le message,
-- et MSG_CLI_ID n'est rempli que si l'auteur etait connecte.
-- ON DELETE SET NULL : le message survit a la suppression du compte.
-- MSG_STATUT : NOUVEAU a l'arrivee, LU des que l'administrateur l'ouvre,
-- TRAITE quand il y a repondu. Un message ne se supprime pas.
-- MSG_DATE_MAJ : date du dernier changement de statut.
-- MSG_REPONSE / MSG_DATE_REPONSE : derniere reponse envoyee au client
-- depuis l'administration (par email, via Brevo). NULL tant qu'il n'y en
-- a pas eu.


-- =====================================================================
-- NIVEAU 3 : dependent du niveau 2
-- =====================================================================

CREATE TABLE POM_VARIANTE (
    VAR_ID INT NOT NULL AUTO_INCREMENT,
    VAR_SKU VARCHAR(50) NOT NULL,
    VAR_TAILLE VARCHAR(10) NOT NULL,
    VAR_COULEUR VARCHAR(50) NOT NULL,
    VAR_STOCK INT NOT NULL DEFAULT 0,
    VAR_SEUIL_ALERTE INT NOT NULL DEFAULT 5,
    VAR_PRO_ID INT NOT NULL,
    VAR_DATE_SUPPRESSION DATETIME NULL,
    CONSTRAINT PK_VARIANTE PRIMARY KEY (VAR_ID),
    CONSTRAINT UQ_VAR_SKU UNIQUE (VAR_SKU),
    CONSTRAINT UQ_VAR_COMBI UNIQUE (VAR_PRO_ID, VAR_TAILLE, VAR_COULEUR),
    CONSTRAINT CK_VAR_STOCK CHECK (VAR_STOCK >= 0),
    CONSTRAINT CK_VAR_SEUIL CHECK (VAR_SEUIL_ALERTE >= 0),
    CONSTRAINT CK_VAR_TAILLE CHECK (VAR_TAILLE IN ('XS', 'S', 'M', 'L', 'XL', 'XXL', 'UNIQUE')),
    CONSTRAINT CK_VAR_COULEUR CHECK (VAR_COULEUR IN ('NOIR', 'BLANC', 'ROUGE', 'BLEU', 'VERT', 'BEIGE', 'GRIS', 'AUTRE')),
    CONSTRAINT FK_VAR_PRO FOREIGN KEY (VAR_PRO_ID) REFERENCES POM_PRODUIT (PRO_ID)
);

-- UQ_VAR_COMBI est la contrainte centrale du modele : une combinaison
-- taille/couleur est unique pour un produit. Sans elle, deux stocks
-- concurrents pourraient exister pour le meme article physique.
-- VAR_DATE_SUPPRESSION : suppression logique. Recreer une combinaison
-- supprimee fait revivre son ancienne ligne (UQ_VAR_COMBI l'impose).

CREATE TABLE POM_IMAGEPRODUITS (
    IMG_ID INT NOT NULL AUTO_INCREMENT,
    IMG_CHEMIN VARCHAR(255) NOT NULL,
    IMG_EST_PRINCIPAL TINYINT(1) NOT NULL DEFAULT 0,
    IMG_COULEUR VARCHAR(50) NULL,
    IMG_ORDRE INT NOT NULL DEFAULT 0,
    IMG_PRO_ID INT NOT NULL,
    IMG_DATE_SUPPRESSION DATETIME NULL,
    CONSTRAINT PK_IMAGEPRODUITS PRIMARY KEY (IMG_ID),
    CONSTRAINT CK_IMG_PRINCIPAL CHECK (IMG_EST_PRINCIPAL IN (0, 1)),
    CONSTRAINT CK_IMG_ORDRE CHECK (IMG_ORDRE >= 0),
    CONSTRAINT CK_IMG_COULEUR CHECK (IMG_COULEUR IS NULL OR IMG_COULEUR IN ('NOIR', 'BLANC', 'ROUGE', 'BLEU', 'VERT', 'BEIGE', 'GRIS', 'AUTRE')),
    CONSTRAINT UQ_IMG_ORDRE UNIQUE (IMG_PRO_ID, IMG_ORDRE),
    CONSTRAINT FK_IMG_PRO FOREIGN KEY (IMG_PRO_ID) REFERENCES POM_PRODUIT (PRO_ID) ON DELETE CASCADE
);

-- Les images n'ont pas d'existence propre : elles suivent le produit.
-- IMG_COULEUR est nullable : une photo generale (packshot, look) ne
-- represente pas forcement une couleur precise. Quand elle est renseignee,
-- elle doit rester dans les memes valeurs que VAR_COULEUR (CK_IMG_COULEUR).
-- IMG_DATE_SUPPRESSION : suppression logique. Une photo supprimee perd
-- aussi IMG_EST_PRINCIPAL ; son fichier reste sur le disque.

CREATE TABLE POM_AVIS (
    AVI_ID INT NOT NULL AUTO_INCREMENT,
    AVI_NOTE TINYINT NOT NULL,
    AVI_COMMENTAIRE TEXT NULL,
    AVI_DATE DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    AVI_VALIDE TINYINT(1) NOT NULL DEFAULT 0,
    AVI_CLI_ID INT NOT NULL,
    AVI_PRO_ID INT NOT NULL,
    AVI_DATE_SUPPRESSION DATETIME NULL,
    CONSTRAINT PK_AVIS PRIMARY KEY (AVI_ID),
    CONSTRAINT UQ_AVI_CLI_PRO UNIQUE (AVI_CLI_ID, AVI_PRO_ID),
    CONSTRAINT CK_AVI_NOTE CHECK (AVI_NOTE BETWEEN 1 AND 5),
    CONSTRAINT CK_AVI_VALIDE CHECK (AVI_VALIDE IN (0, 1)),
    CONSTRAINT FK_AVI_CLI FOREIGN KEY (AVI_CLI_ID) REFERENCES POM_CLIENT (CLI_ID) ON DELETE CASCADE,
    CONSTRAINT FK_AVI_PRO FOREIGN KEY (AVI_PRO_ID) REFERENCES POM_PRODUIT (PRO_ID) ON DELETE CASCADE
);

-- AVI_VALIDE vaut 0 par defaut cote base : une ligne inseree a la main
-- n'apparait pas sans decision explicite. Le site, lui, publie l'avis
-- tout de suite (1) ; l'administrateur peut ensuite le masquer (0).
-- UQ_AVI_CLI_PRO : un client ne note qu'une fois chaque produit, ce qui
-- traduit le fait que POM_AVIS decrit le couple (client, produit).
-- AVI_DATE_SUPPRESSION : suppression logique par l'administrateur.

CREATE TABLE POM_COMMANDE (
    COM_ID INT NOT NULL AUTO_INCREMENT,
    COM_NUMERO VARCHAR(50) NOT NULL,
    COM_DATE DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    COM_STATUT VARCHAR(30) NOT NULL DEFAULT 'EN_ATTENTE',
    COM_DATE_MAJ DATETIME NULL,
    COM_MONTANT_TOTAL DECIMAL(10, 2) NOT NULL DEFAULT 0,
    COM_MODE_LIVRAISON VARCHAR(30) NOT NULL,
    COM_FRAIS_PORT DECIMAL(8, 2) NOT NULL DEFAULT 0,
    COM_CODE_PROMO VARCHAR(20) NULL,
    COM_REMISE DECIMAL(10, 2) NOT NULL DEFAULT 0,
    COM_PAIEMENT_ID VARCHAR(255) NULL,
    COM_CLI_ID INT NOT NULL,
    COM_ADR_ID INT NOT NULL,
    CONSTRAINT PK_COMMANDE PRIMARY KEY (COM_ID),
    CONSTRAINT UQ_COM_NUMERO UNIQUE (COM_NUMERO),
    CONSTRAINT CK_COM_STATUT CHECK (COM_STATUT IN ('EN_ATTENTE', 'VALIDEE', 'EN_PREPARATION', 'EXPEDIEE', 'LIVREE', 'ANNULEE')),
    CONSTRAINT CK_COM_LIVRAISON CHECK (COM_MODE_LIVRAISON IN ('STANDARD', 'EXPRESS', 'RETRAIT_MAGASIN')),
    CONSTRAINT CK_COM_MONTANT CHECK (COM_MONTANT_TOTAL >= 0),
    CONSTRAINT CK_COM_PORT CHECK (COM_FRAIS_PORT >= 0),
    CONSTRAINT CK_COM_REMISE CHECK (COM_REMISE >= 0),
    CONSTRAINT CK_COM_RETRAIT CHECK (COM_MODE_LIVRAISON <> 'RETRAIT_MAGASIN' OR COM_FRAIS_PORT = 0),
    CONSTRAINT FK_COM_CLI FOREIGN KEY (COM_CLI_ID) REFERENCES POM_CLIENT (CLI_ID),
    CONSTRAINT FK_COM_ADR FOREIGN KEY (COM_ADR_ID) REFERENCES POM_ADRESSE (ADR_ID)
);

-- Pas de cascade : une commande est une piece comptable, elle survit a
-- la suppression du compte client. Elle ne se supprime pas non plus :
-- on l'annule (COM_STATUT = 'ANNULEE').
-- CK_COM_RETRAIT : le retrait en magasin ne peut pas etre facture.
-- COM_DATE_MAJ : date du dernier changement de statut. NULL tant que le
-- statut n'a jamais ete modifie par l'administrateur.
-- COM_CODE_PROMO / COM_REMISE : code saisi a la commande (POMO20, -20 %
-- sur la premiere commande) et montant deduit. NULL / 0 sans code.
-- COM_PAIEMENT_ID : numero de la session de paiement Stripe (cs_test_...).
-- NULL tant que le client n'est pas passe par la page de paiement.


-- =====================================================================
-- NIVEAU 4 : classes d'association (cle primaire composite)
-- =====================================================================

CREATE TABLE POM_LIGNE_PANIER (
    LPA_PAN_ID INT NOT NULL,
    LPA_VAR_ID INT NOT NULL,
    LPA_QUANTITE INT NOT NULL DEFAULT 1,
    LPA_DATE_AJOUT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    LPA_DATE_SUPPRESSION DATETIME NULL,
    CONSTRAINT PK_LIGNE_PANIER PRIMARY KEY (LPA_PAN_ID, LPA_VAR_ID),
    CONSTRAINT CK_LPA_QUANTITE CHECK (LPA_QUANTITE > 0),
    CONSTRAINT FK_LPA_PAN FOREIGN KEY (LPA_PAN_ID) REFERENCES POM_PANIER (PAN_ID) ON DELETE CASCADE,
    CONSTRAINT FK_LPA_VAR FOREIGN KEY (LPA_VAR_ID) REFERENCES POM_VARIANTE (VAR_ID)
);

-- La cle composite interdit deux lignes pour la meme variante : ajouter
-- un article deja present incremente la quantite.
-- Pas de cascade sur la variante : retirer un article du catalogue ne
-- doit pas vider silencieusement les paniers en cours.
-- LPA_DATE_SUPPRESSION : article retire ou panier vide apres commande.
-- Remettre l'article au panier fait revivre la ligne (cle composite).

CREATE TABLE POM_LIGNE_COMMANDE (
    LCO_COM_ID INT NOT NULL,
    LCO_VAR_ID INT NOT NULL,
    LCO_QUANTITE INT NOT NULL,
    LCO_PRIX_UNITAIRE DECIMAL(8, 2) NOT NULL,
    LCO_SOUS_TOTAL DECIMAL(12, 2) GENERATED ALWAYS AS (LCO_QUANTITE * LCO_PRIX_UNITAIRE) VIRTUAL,
    CONSTRAINT PK_LIGNE_COMMANDE PRIMARY KEY (LCO_COM_ID, LCO_VAR_ID),
    CONSTRAINT CK_LCO_QUANTITE CHECK (LCO_QUANTITE > 0),
    CONSTRAINT CK_LCO_PRIX CHECK (LCO_PRIX_UNITAIRE >= 0),
    CONSTRAINT FK_LCO_COM FOREIGN KEY (LCO_COM_ID) REFERENCES POM_COMMANDE (COM_ID) ON DELETE CASCADE,
    CONSTRAINT FK_LCO_VAR FOREIGN KEY (LCO_VAR_ID) REFERENCES POM_VARIANTE (VAR_ID)
);

-- LCO_PRIX_UNITAIRE fige le prix du jour de l'achat : une hausse
-- ulterieure du tarif ne doit pas reecrire les commandes passees.
-- LCO_SOUS_TOTAL est une colonne generee, calculee a la lecture et
-- jamais stockee : elle ne peut pas diverger de ses deux facteurs.


/* =====================================================================
   AUCUN INDEX N'EST CREE ICI

   MySQL cree automatiquement un index derriere chaque PRIMARY KEY,
   chaque UNIQUE et chaque FOREIGN KEY. L'onglet Structure de phpMyAdmin
   en affichera donc, mais ce sont ceux des contraintes ci-dessus, pas
   des ajouts de performance. Ils ne peuvent pas etre supprimes sans
   supprimer les contraintes elles-memes.
   ===================================================================== */


/* =====================================================================
   DIFFERENCES AVEC LA VERSION ORACLE

   - VARCHAR2 -> VARCHAR, NUMBER -> INT / DECIMAL, DATE -> DATETIME
   - SYSDATE -> CURRENT_TIMESTAMP
   - Sequences -> AUTO_INCREMENT : ne pas fournir les identifiants a
     l'insertion, MySQL les genere
   - VARCHAR(191) pour les deux emails : en utf8mb4 un caractere pese
     jusqu'a 4 octets, et VARCHAR(255) depassait la limite de 767 octets
     d'un index UNIQUE (erreur 1709)
   - CK_PAN_DATES supprimee : PAN_DATE_MAJ se met a jour automatiquement
     via ON UPDATE CURRENT_TIMESTAMP
   - COMMIT retire : phpMyAdmin valide chaque instruction

   PREREQUIS : les contraintes CHECK ne sont reellement appliquees qu'a
   partir de MySQL 8.0.16 ou MariaDB 10.2.1. En dessous, elles sont
   acceptees puis ignorees sans aucun message. Verifier avec :
       SELECT VERSION();
   ===================================================================== */


/* =====================================================================
   CHOIX DE CONCEPTION A CONNAITRE POUR LA SOUTENANCE

   1. COM_PAN_ID a ete supprimee. UQ_PAN_CLI (un panier par client) et
      UQ_COM_PAN (un panier par commande) se contredisaient : la
      deuxieme commande d'un client echouait, puisqu'elle aurait pointe
      vers le meme panier reutilise.

   2. PRO_TYPE et PRO_CAT_ID sont deux axes distincts. La categorie
      designe le rayon, le type designe la nature du vetement. Si un
      type devait porter ses propres donnees (guide des tailles, poids
      moyen, taux de TVA), il faudrait une table POM_TYPE et le cas
      d'utilisation "Gerer les types" au backlog.

   3. POM_ABONNE_NEWSLETTER reste absente : les abonnes a la newsletter
      sont geres directement par Brevo (liste et double confirmation).
      POM_MESSAGE_CONTACT porte le formulaire de contact (BM-05).

   4. Cascades : uniquement sur les donnees sans existence propre
      (images, lignes de panier, lignes de commande, avis, panier).
      Jamais sur les commandes, adresses et variantes, qui portent de
      l'historique.

   5. Aucune suppression physique depuis le site (aucun DELETE) :
      - produits et categories : desactivation (PRO_ACTIF, CAT_ACTIF).
        Une categorie inactive retire ses produits de la boutique ;
      - commandes : annulation (COM_STATUT = 'ANNULEE'), le stock est
        rendu ;
      - variantes, images, avis, adresses, lignes de panier : colonne
        XXX_DATE_SUPPRESSION. NULL = ligne vivante, une date = ligne
        supprimee. Le site filtre "IS NULL" a la lecture, la base garde
        tout l'historique.
      Les cascades ci-dessus ne servent donc plus qu'a un nettoyage fait
      a la main dans phpMyAdmin.
      Contrepartie : les contraintes UNIQUE portent aussi sur les lignes
      supprimees. Le PHP en tient compte en faisant revivre l'ancienne
      ligne (INSERT ... ON DUPLICATE KEY UPDATE) plutot qu'en inserant.
   ===================================================================== */
