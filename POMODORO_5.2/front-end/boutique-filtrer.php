<?php
require __DIR__ . '/../back-end/connexion-base-de-donnees.php'; // fournit $pdo
require __DIR__ . '/../back-end/classes/Produit.php';
require __DIR__ . '/../back-end/classes/Categorie.php';
require __DIR__ . '/../back-end/classes/Variante.php';
require __DIR__ . '/../back-end/classes/ImageProduit.php';
require __DIR__ . '/../back-end/classes/Avis.php';
require __DIR__ . '/../back-end/fonctions/couleurs.php';
require __DIR__ . '/../back-end/fonctions/filtres-boutique.php';

session_start();

$categories = Categorie::toutes($pdo);
$filtres = lire_filtres_catalogue($categories);
$page = page_catalogue_courante();
$parPage = 24;

$total = Produit::compterActifs($pdo, $filtres);
$produits = Produit::rechercherActifs($pdo, $filtres, $page, $parPage);

$produitIds = array_map(fn ($produit) => $produit->id, $produits);
$imagesGaleries = ImageProduit::galerieParCouleur($pdo, $produitIds);
$detailVariantes = Variante::disponibilitesParProduit($pdo, $produitIds);
$notesParProduit = Avis::resumePourProduits($pdo, $produitIds);

// Fragment HTML seul : pas de <html>/<head>, pour etre injecte tel quel par le JS.
header('Content-Type: text/html; charset=utf-8');
include __DIR__ . '/morceaux-de-page/grille-produits.php';
