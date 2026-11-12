-- =====================================================================
-- POMODORO STORE - Ajout de la collection "Condensed" (7 produits)
-- A executer UNE fois dans phpMyAdmin, dans la base du site.
-- AVANT : envoyer les 24 photos de documents/photos-collection-condensed/
-- dans le dossier photos-produits/ du site (a cote de boutique.php).
-- Tout est dans une transaction : si une ligne echoue, rien n'est
-- enregistre (sauf la mise a jour de CK_PRO_TYPE, faite avant).
-- =====================================================================

ALTER TABLE POM_PRODUIT DROP CONSTRAINT CK_PRO_TYPE;
ALTER TABLE POM_PRODUIT ADD CONSTRAINT CK_PRO_TYPE CHECK (PRO_TYPE IN ('TSHIRT', 'PULL', 'SWEAT', 'VESTE', 'PANTALON', 'SHORT', 'CASQUETTE', 'BONNET', 'SAC', 'CHAUSSETTES'));

START TRANSACTION;

SET @unisexe = (SELECT CAT_ID FROM POM_CATEGORIE WHERE CAT_NOM = 'Unisexe');


-- T-shirt Pomodoro Condensed
INSERT INTO POM_PRODUIT (PRO_REFERENCE, PRO_NOM, PRO_DESCRIPTION, PRO_PRIX, PRO_ACTIF, PRO_TYPE, PRO_CAT_ID)
VALUES ('TSH-CONDENSED', 'T-shirt Pomodoro Condensed', 'T-shirt en coton epais, grand imprime "Pomodoro Condensed Soup" sur la poitrine, clin d''oeil assume au pop art.', 39, 1, 'TSHIRT', @unisexe);
SET @produit = LAST_INSERT_ID();
INSERT INTO POM_VARIANTE (VAR_SKU, VAR_TAILLE, VAR_COULEUR, VAR_STOCK, VAR_PRO_ID) VALUES
    ('TSH-CONDENSED-S-NOIR', 'S', 'NOIR', 10, @produit),
    ('TSH-CONDENSED-M-NOIR', 'M', 'NOIR', 10, @produit),
    ('TSH-CONDENSED-L-NOIR', 'L', 'NOIR', 10, @produit),
    ('TSH-CONDENSED-XL-NOIR', 'XL', 'NOIR', 10, @produit),
    ('TSH-CONDENSED-S-GRIS', 'S', 'GRIS', 10, @produit),
    ('TSH-CONDENSED-M-GRIS', 'M', 'GRIS', 10, @produit),
    ('TSH-CONDENSED-L-GRIS', 'L', 'GRIS', 10, @produit),
    ('TSH-CONDENSED-XL-GRIS', 'XL', 'GRIS', 10, @produit),
    ('TSH-CONDENSED-S-BEIGE', 'S', 'BEIGE', 10, @produit),
    ('TSH-CONDENSED-M-BEIGE', 'M', 'BEIGE', 10, @produit),
    ('TSH-CONDENSED-L-BEIGE', 'L', 'BEIGE', 10, @produit),
    ('TSH-CONDENSED-XL-BEIGE', 'XL', 'BEIGE', 10, @produit),
    ('TSH-CONDENSED-S-ROUGE', 'S', 'ROUGE', 10, @produit),
    ('TSH-CONDENSED-M-ROUGE', 'M', 'ROUGE', 10, @produit),
    ('TSH-CONDENSED-L-ROUGE', 'L', 'ROUGE', 10, @produit),
    ('TSH-CONDENSED-XL-ROUGE', 'XL', 'ROUGE', 10, @produit);
INSERT INTO POM_IMAGEPRODUITS (IMG_CHEMIN, IMG_EST_PRINCIPAL, IMG_COULEUR, IMG_ORDRE, IMG_PRO_ID) VALUES
    ('photos-produits/tshirt-condensed-noir.jpg', 1, 'NOIR', 0, @produit),
    ('photos-produits/tshirt-condensed-gris.jpg', 0, 'GRIS', 1, @produit),
    ('photos-produits/tshirt-condensed-beige.jpg', 0, 'BEIGE', 2, @produit),
    ('photos-produits/tshirt-condensed-bordeaux.jpg', 0, 'ROUGE', 3, @produit);

-- Sweat Pomodoro Condensed
INSERT INTO POM_PRODUIT (PRO_REFERENCE, PRO_NOM, PRO_DESCRIPTION, PRO_PRIX, PRO_ACTIF, PRO_TYPE, PRO_CAT_ID)
VALUES ('SWT-CONDENSED', 'Sweat Pomodoro Condensed', 'Sweat col rond en molleton epais, grand imprime "Pomodoro Condensed Soup" sur la poitrine. Coupe droite, poignets et bas cotes.', 59, 1, 'SWEAT', @unisexe);
SET @produit = LAST_INSERT_ID();
INSERT INTO POM_VARIANTE (VAR_SKU, VAR_TAILLE, VAR_COULEUR, VAR_STOCK, VAR_PRO_ID) VALUES
    ('SWT-CONDENSED-S-BEIGE', 'S', 'BEIGE', 10, @produit),
    ('SWT-CONDENSED-M-BEIGE', 'M', 'BEIGE', 10, @produit),
    ('SWT-CONDENSED-L-BEIGE', 'L', 'BEIGE', 10, @produit),
    ('SWT-CONDENSED-XL-BEIGE', 'XL', 'BEIGE', 10, @produit),
    ('SWT-CONDENSED-S-VERT', 'S', 'VERT', 10, @produit),
    ('SWT-CONDENSED-M-VERT', 'M', 'VERT', 10, @produit),
    ('SWT-CONDENSED-L-VERT', 'L', 'VERT', 10, @produit),
    ('SWT-CONDENSED-XL-VERT', 'XL', 'VERT', 10, @produit),
    ('SWT-CONDENSED-S-NOIR', 'S', 'NOIR', 10, @produit),
    ('SWT-CONDENSED-M-NOIR', 'M', 'NOIR', 10, @produit),
    ('SWT-CONDENSED-L-NOIR', 'L', 'NOIR', 10, @produit),
    ('SWT-CONDENSED-XL-NOIR', 'XL', 'NOIR', 10, @produit),
    ('SWT-CONDENSED-S-GRIS', 'S', 'GRIS', 10, @produit),
    ('SWT-CONDENSED-M-GRIS', 'M', 'GRIS', 10, @produit),
    ('SWT-CONDENSED-L-GRIS', 'L', 'GRIS', 10, @produit),
    ('SWT-CONDENSED-XL-GRIS', 'XL', 'GRIS', 10, @produit);
INSERT INTO POM_IMAGEPRODUITS (IMG_CHEMIN, IMG_EST_PRINCIPAL, IMG_COULEUR, IMG_ORDRE, IMG_PRO_ID) VALUES
    ('photos-produits/sweat-condensed-beige.jpg', 1, 'BEIGE', 0, @produit),
    ('photos-produits/sweat-condensed-vert.jpg', 0, 'VERT', 1, @produit),
    ('photos-produits/sweat-condensed-noir.jpg', 0, 'NOIR', 2, @produit),
    ('photos-produits/sweat-condensed-gris.jpg', 0, 'GRIS', 3, @produit);

-- Hoodie Pomodoro Condensed
INSERT INTO POM_PRODUIT (PRO_REFERENCE, PRO_NOM, PRO_DESCRIPTION, PRO_PRIX, PRO_ACTIF, PRO_TYPE, PRO_CAT_ID)
VALUES ('HOO-CONDENSED', 'Hoodie Pomodoro Condensed', 'Sweat a capuche en molleton doux, poche kangourou et grand imprime "Pomodoro Condensed Soup" sur le devant.', 65, 1, 'SWEAT', @unisexe);
SET @produit = LAST_INSERT_ID();
INSERT INTO POM_VARIANTE (VAR_SKU, VAR_TAILLE, VAR_COULEUR, VAR_STOCK, VAR_PRO_ID) VALUES
    ('HOO-CONDENSED-S-BLEU', 'S', 'BLEU', 10, @produit),
    ('HOO-CONDENSED-M-BLEU', 'M', 'BLEU', 10, @produit),
    ('HOO-CONDENSED-L-BLEU', 'L', 'BLEU', 10, @produit),
    ('HOO-CONDENSED-XL-BLEU', 'XL', 'BLEU', 10, @produit);
INSERT INTO POM_IMAGEPRODUITS (IMG_CHEMIN, IMG_EST_PRINCIPAL, IMG_COULEUR, IMG_ORDRE, IMG_PRO_ID) VALUES
    ('photos-produits/hoodie-condensed-bleu.jpg', 1, 'BLEU', 0, @produit);

-- Short Pomodoro Condensed
INSERT INTO POM_PRODUIT (PRO_REFERENCE, PRO_NOM, PRO_DESCRIPTION, PRO_PRIX, PRO_ACTIF, PRO_TYPE, PRO_CAT_ID)
VALUES ('SHO-CONDENSED', 'Short Pomodoro Condensed', 'Short leger a cordon rouge, grande boite de soupe Pomodoro sur une jambe et petits motifs tomate et basilic sur l''autre.', 35, 1, 'SHORT', @unisexe);
SET @produit = LAST_INSERT_ID();
INSERT INTO POM_VARIANTE (VAR_SKU, VAR_TAILLE, VAR_COULEUR, VAR_STOCK, VAR_PRO_ID) VALUES
    ('SHO-CONDENSED-S-BEIGE', 'S', 'BEIGE', 10, @produit),
    ('SHO-CONDENSED-M-BEIGE', 'M', 'BEIGE', 10, @produit),
    ('SHO-CONDENSED-L-BEIGE', 'L', 'BEIGE', 10, @produit),
    ('SHO-CONDENSED-XL-BEIGE', 'XL', 'BEIGE', 10, @produit),
    ('SHO-CONDENSED-S-ROUGE', 'S', 'ROUGE', 10, @produit),
    ('SHO-CONDENSED-M-ROUGE', 'M', 'ROUGE', 10, @produit),
    ('SHO-CONDENSED-L-ROUGE', 'L', 'ROUGE', 10, @produit),
    ('SHO-CONDENSED-XL-ROUGE', 'XL', 'ROUGE', 10, @produit),
    ('SHO-CONDENSED-S-BLANC', 'S', 'BLANC', 10, @produit),
    ('SHO-CONDENSED-M-BLANC', 'M', 'BLANC', 10, @produit),
    ('SHO-CONDENSED-L-BLANC', 'L', 'BLANC', 10, @produit),
    ('SHO-CONDENSED-XL-BLANC', 'XL', 'BLANC', 10, @produit),
    ('SHO-CONDENSED-S-VERT', 'S', 'VERT', 10, @produit),
    ('SHO-CONDENSED-M-VERT', 'M', 'VERT', 10, @produit),
    ('SHO-CONDENSED-L-VERT', 'L', 'VERT', 10, @produit),
    ('SHO-CONDENSED-XL-VERT', 'XL', 'VERT', 10, @produit),
    ('SHO-CONDENSED-S-NOIR', 'S', 'NOIR', 10, @produit),
    ('SHO-CONDENSED-M-NOIR', 'M', 'NOIR', 10, @produit),
    ('SHO-CONDENSED-L-NOIR', 'L', 'NOIR', 10, @produit),
    ('SHO-CONDENSED-XL-NOIR', 'XL', 'NOIR', 10, @produit);
INSERT INTO POM_IMAGEPRODUITS (IMG_CHEMIN, IMG_EST_PRINCIPAL, IMG_COULEUR, IMG_ORDRE, IMG_PRO_ID) VALUES
    ('photos-produits/short-condensed-beige.jpg', 1, 'BEIGE', 0, @produit),
    ('photos-produits/short-condensed-rouge.jpg', 0, 'ROUGE', 1, @produit),
    ('photos-produits/short-condensed-blanc.jpg', 0, 'BLANC', 2, @produit),
    ('photos-produits/short-condensed-vert.jpg', 0, 'VERT', 3, @produit),
    ('photos-produits/short-condensed-noir.jpg', 0, 'NOIR', 4, @produit);

-- Jogging Pomodoro Condensed
INSERT INTO POM_PRODUIT (PRO_REFERENCE, PRO_NOM, PRO_DESCRIPTION, PRO_PRIX, PRO_ACTIF, PRO_TYPE, PRO_CAT_ID)
VALUES ('JOG-CONDENSED', 'Jogging Pomodoro Condensed', 'Pantalon de jogging en molleton, taille elastique a cordon, chevilles resserrees et petite boite de soupe Pomodoro sur la cuisse.', 55, 1, 'PANTALON', @unisexe);
SET @produit = LAST_INSERT_ID();
INSERT INTO POM_VARIANTE (VAR_SKU, VAR_TAILLE, VAR_COULEUR, VAR_STOCK, VAR_PRO_ID) VALUES
    ('JOG-CONDENSED-S-BLEU', 'S', 'BLEU', 10, @produit),
    ('JOG-CONDENSED-M-BLEU', 'M', 'BLEU', 10, @produit),
    ('JOG-CONDENSED-L-BLEU', 'L', 'BLEU', 10, @produit),
    ('JOG-CONDENSED-XL-BLEU', 'XL', 'BLEU', 10, @produit),
    ('JOG-CONDENSED-S-BEIGE', 'S', 'BEIGE', 10, @produit),
    ('JOG-CONDENSED-M-BEIGE', 'M', 'BEIGE', 10, @produit),
    ('JOG-CONDENSED-L-BEIGE', 'L', 'BEIGE', 10, @produit),
    ('JOG-CONDENSED-XL-BEIGE', 'XL', 'BEIGE', 10, @produit),
    ('JOG-CONDENSED-S-GRIS', 'S', 'GRIS', 10, @produit),
    ('JOG-CONDENSED-M-GRIS', 'M', 'GRIS', 10, @produit),
    ('JOG-CONDENSED-L-GRIS', 'L', 'GRIS', 10, @produit),
    ('JOG-CONDENSED-XL-GRIS', 'XL', 'GRIS', 10, @produit),
    ('JOG-CONDENSED-S-NOIR', 'S', 'NOIR', 10, @produit),
    ('JOG-CONDENSED-M-NOIR', 'M', 'NOIR', 10, @produit),
    ('JOG-CONDENSED-L-NOIR', 'L', 'NOIR', 10, @produit),
    ('JOG-CONDENSED-XL-NOIR', 'XL', 'NOIR', 10, @produit);
INSERT INTO POM_IMAGEPRODUITS (IMG_CHEMIN, IMG_EST_PRINCIPAL, IMG_COULEUR, IMG_ORDRE, IMG_PRO_ID) VALUES
    ('photos-produits/jogging-condensed-bleu.jpg', 1, 'BLEU', 0, @produit),
    ('photos-produits/jogging-condensed-beige.jpg', 0, 'BEIGE', 1, @produit),
    ('photos-produits/jogging-condensed-gris.jpg', 0, 'GRIS', 2, @produit),
    ('photos-produits/jogging-condensed-noir.jpg', 0, 'NOIR', 3, @produit);

-- Chaussettes Pomodoro Condensed
INSERT INTO POM_PRODUIT (PRO_REFERENCE, PRO_NOM, PRO_DESCRIPTION, PRO_PRIX, PRO_ACTIF, PRO_TYPE, PRO_CAT_ID)
VALUES ('CHA-CONDENSED', 'Chaussettes Pomodoro Condensed', 'Chaussettes de sport cotelees a bandes bordeaux, logo "Pomodoro Condensed" tisse sur la tige et sous le pied.', 15, 1, 'CHAUSSETTES', @unisexe);
SET @produit = LAST_INSERT_ID();
INSERT INTO POM_VARIANTE (VAR_SKU, VAR_TAILLE, VAR_COULEUR, VAR_STOCK, VAR_PRO_ID) VALUES
    ('CHA-CONDENSED-UNIQUE-BLANC', 'UNIQUE', 'BLANC', 10, @produit),
    ('CHA-CONDENSED-UNIQUE-NOIR', 'UNIQUE', 'NOIR', 10, @produit);
INSERT INTO POM_IMAGEPRODUITS (IMG_CHEMIN, IMG_EST_PRINCIPAL, IMG_COULEUR, IMG_ORDRE, IMG_PRO_ID) VALUES
    ('photos-produits/chaussettes-condensed-blanc.jpg', 1, 'BLANC', 0, @produit),
    ('photos-produits/chaussettes-condensed-noir.jpg', 0, 'NOIR', 1, @produit);

-- Ensemble Pomodoro
INSERT INTO POM_PRODUIT (PRO_REFERENCE, PRO_NOM, PRO_DESCRIPTION, PRO_PRIX, PRO_ACTIF, PRO_TYPE, PRO_CAT_ID)
VALUES ('ENS-POMODORO', 'Ensemble Pomodoro', 'Ensemble hoodie oversize et pantalon large en molleton epais, brode "Pomodoro" et sa petite tomate sur la poitrine.', 99, 1, 'SWEAT', @unisexe);
SET @produit = LAST_INSERT_ID();
INSERT INTO POM_VARIANTE (VAR_SKU, VAR_TAILLE, VAR_COULEUR, VAR_STOCK, VAR_PRO_ID) VALUES
    ('ENS-POMODORO-S-GRIS', 'S', 'GRIS', 10, @produit),
    ('ENS-POMODORO-M-GRIS', 'M', 'GRIS', 10, @produit),
    ('ENS-POMODORO-L-GRIS', 'L', 'GRIS', 10, @produit),
    ('ENS-POMODORO-XL-GRIS', 'XL', 'GRIS', 10, @produit),
    ('ENS-POMODORO-S-VERT', 'S', 'VERT', 10, @produit),
    ('ENS-POMODORO-M-VERT', 'M', 'VERT', 10, @produit),
    ('ENS-POMODORO-L-VERT', 'L', 'VERT', 10, @produit),
    ('ENS-POMODORO-XL-VERT', 'XL', 'VERT', 10, @produit),
    ('ENS-POMODORO-S-BEIGE', 'S', 'BEIGE', 10, @produit),
    ('ENS-POMODORO-M-BEIGE', 'M', 'BEIGE', 10, @produit),
    ('ENS-POMODORO-L-BEIGE', 'L', 'BEIGE', 10, @produit),
    ('ENS-POMODORO-XL-BEIGE', 'XL', 'BEIGE', 10, @produit),
    ('ENS-POMODORO-S-NOIR', 'S', 'NOIR', 10, @produit),
    ('ENS-POMODORO-M-NOIR', 'M', 'NOIR', 10, @produit),
    ('ENS-POMODORO-L-NOIR', 'L', 'NOIR', 10, @produit),
    ('ENS-POMODORO-XL-NOIR', 'XL', 'NOIR', 10, @produit);
INSERT INTO POM_IMAGEPRODUITS (IMG_CHEMIN, IMG_EST_PRINCIPAL, IMG_COULEUR, IMG_ORDRE, IMG_PRO_ID) VALUES
    ('photos-produits/ensemble-pomodoro-gris.jpg', 1, 'GRIS', 0, @produit),
    ('photos-produits/ensemble-pomodoro-vert.jpg', 0, 'VERT', 1, @produit),
    ('photos-produits/ensemble-pomodoro-beige.jpg', 0, 'BEIGE', 2, @produit),
    ('photos-produits/ensemble-pomodoro-noir.jpg', 0, 'NOIR', 3, @produit);

COMMIT;
