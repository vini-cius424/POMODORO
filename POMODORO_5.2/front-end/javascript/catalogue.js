(function () {
    'use strict';

    var LIBELLES_COULEUR = { NOIR: 'Noir', BLANC: 'Blanc', ROUGE: 'Rouge', BLEU: 'Bleu', VERT: 'Vert', BEIGE: 'Beige', GRIS: 'Gris', AUTRE: 'Autres' };
    var LIBELLES_TRI = { prix_asc: 'Prix croissant', prix_desc: 'Prix décroissant', nouveautes: 'Nouveautés' };

    var formulaire = document.getElementById('formulaire-filtres');
    var resultats = document.getElementById('resultats-catalogue');
    var contenu = document.querySelector('.catalogue-contenu');
    if (!formulaire || !resultats || !contenu) return; // pas la page catalogue

    var champRecherche = document.getElementById('champ-recherche');
    var champTri = document.getElementById('champ-tri');
    var navCategories = document.getElementById('nav-categories');
    var champsTaille = formulaire.querySelector('[data-champ="taille"]');
    var champsCouleur = formulaire.querySelector('[data-champ="couleur"]');
    var chipsConteneur = document.getElementById('chips-filtres-actifs');

    var CATEGORIES = {};
    if (navCategories) {
        navCategories.querySelectorAll('a[data-categorie]').forEach(function (lien) {
            if (lien.dataset.categorie) CATEGORIES[lien.dataset.categorie] = lien.textContent.trim();
        });
    }
    // Noms des categories et des types tels qu'affiches dans l'entete,
    // pour les puces "filtres actifs" ("T-shirt" plutot que "Tshirt").
    var TYPES = {};
    document.querySelectorAll('.entete-pastilles a[data-entete-categorie]').forEach(function (lien) {
        if (lien.dataset.enteteCategorie) CATEGORIES[lien.dataset.enteteCategorie] = lien.textContent.trim();
    });
    document.querySelectorAll('.entete-types a[data-entete-type]').forEach(function (lien) {
        TYPES[lien.dataset.enteteType] = lien.textContent.trim();
    });

    var minuteur = null;
    function differerAppel(fn, delai) {
        return function () {
            clearTimeout(minuteur);
            minuteur = setTimeout(fn, delai);
        };
    }

    // ---------------------------------------------------------------
    // Construction des parametres a partir de l'etat actuel du formulaire
    // ---------------------------------------------------------------

    function parametresDuFormulaire() {
        var params = new URLSearchParams(new FormData(formulaire));
        // FormData saute les champs disabled/vides differemment de ce qu'on
        // veut pour l'URL : on nettoie les valeurs vides pour ne pas polluer.
        [...params.keys()].forEach(function (cle) {
            if (params.get(cle) === '') params.delete(cle);
        });
        return params;
    }

    function appliquerFiltres(options) {
        options = options || {};
        var params = parametresDuFormulaire();

        if (options.page) {
            params.set('page', options.page);
        } else {
            params.delete('page'); // tout changement de filtre revient a la page 1
        }

        chargerResultats(params, !options.sansHistorique);
    }

    function chargerResultats(params, ecrireHistorique) {
        resultats.classList.add('catalogue-chargement');

        fetch('boutique-filtrer.php?' + params.toString())
            .then(function (reponse) { return reponse.text(); })
            .then(function (html) {
                var temp = document.createElement('div');
                temp.innerHTML = html;
                var nouveau = temp.querySelector('#resultats-catalogue');
                if (!nouveau) return;
                resultats.replaceWith(nouveau);
                resultats = nouveau;
                initialiserCartesProduit();
                initialiserPagination();
                mettreAJourFiltresActifs();

                var compteMobile = document.getElementById('compte-mobile');
                if (compteMobile) compteMobile.textContent = resultats.dataset.total;

                if (ecrireHistorique) {
                    var url = params.toString() ? 'boutique.php?' + params.toString() : 'boutique.php';
                    history.pushState({ pomodoro: true }, '', url);
                }
            })
            .catch(function () {
                resultats.classList.remove('catalogue-chargement');
            })
            .finally(function () {
                resultats.classList.remove('catalogue-chargement');
            });
    }

    // ---------------------------------------------------------------
    // Puces "filtres actifs"
    // ---------------------------------------------------------------

    function mettreAJourFiltresActifs() {
        mettreAJourEntete();
        if (!chipsConteneur) return;
        var params = parametresDuFormulaire();
        var chips = [];

        function ajouterFiltreActif(champ, libelle) {
            chips.push({ champ: champ, libelle: libelle });
        }

        if (params.get('categorie')) ajouterFiltreActif('categorie', CATEGORIES[params.get('categorie')] || 'Catégorie');
        if (params.get('q')) ajouterFiltreActif('q', '« ' + params.get('q') + ' »');
        if (params.get('type')) ajouterFiltreActif('type', TYPES[params.get('type')] || params.get('type').charAt(0) + params.get('type').slice(1).toLowerCase());
        if (params.get('sous_categorie')) ajouterFiltreActif('sous_categorie', params.get('sous_categorie'));
        (params.get('taille') ? params.get('taille').split(',') : []).forEach(function (taille) {
            ajouterFiltreActif('taille:' + taille, 'Taille ' + taille);
        });
        if (params.get('couleur')) ajouterFiltreActif('couleur', LIBELLES_COULEUR[params.get('couleur')] || params.get('couleur'));
        if (params.get('promo')) ajouterFiltreActif('promo', 'Promotion');
        if (params.get('nouveautes')) ajouterFiltreActif('nouveautes', 'Nouveautés');
        if (sliderMin) {
            var min = Number(champMinPrix.value);
            var max = Number(champMaxPrix.value);
            // Puce affichee seulement si la plage a vraiment ete resserree,
            // pas juste parce que les champs numeriques ont une valeur.
            if (min > Number(sliderMin.min) || max < Number(sliderMax.max)) {
                ajouterFiltreActif('prix', min + '–' + max + ' CHF');
            }
        }

        if (!chips.length) {
            chipsConteneur.innerHTML = '';
            chipsConteneur.hidden = true;
            return;
        }

        chipsConteneur.hidden = false;
        chipsConteneur.innerHTML = '<span class="chips-titre">Filtres actifs :</span>' +
            chips.map(function (chip) {
                return '<button type="button" class="chip" data-effacer="' + chip.champ + '">' + chip.libelle + ' ×</button>';
            }).join('') +
            '<button type="button" class="chip chip-reinitialiser" id="chip-tout-effacer">Réinitialiser les filtres</button>';

        chipsConteneur.querySelectorAll('[data-effacer]').forEach(function (bouton) {
            bouton.addEventListener('click', function () { effacerChamp(bouton.dataset.effacer); });
        });
        var boutonTout = document.getElementById('chip-tout-effacer');
        if (boutonTout) boutonTout.addEventListener('click', reinitialiserFiltres);
    }

    function effacerChamp(champ) {
        if (champ === 'prix') {
            definirPlagePrix(Number(sliderMin.min), Number(sliderMax.max));
        } else if (champ.indexOf('taille:') === 0) {
            var valeurRetiree = champ.slice('taille:'.length);
            appliquerSelectionTailles(taillesSelectionnees().filter(function (t) { return t !== valeurRetiree; }));
        } else if (champ === 'couleur') {
            selectionnerCouleur(champsCouleur, '');
        } else if (champ === 'categorie') {
            formulaire.categorie.value = '';
            document.querySelectorAll('#nav-categories a').forEach(function (a) {
                a.classList.toggle('filtre-categorie-actif', a.dataset.categorie === '');
            });
        } else {
            var champEl = formulaire.elements[champ];
            if (!champEl) return;
            if (champEl.type === 'checkbox') champEl.checked = false;
            else champEl.value = '';
        }
        appliquerFiltres();
    }

    function reinitialiserFiltres() {
        // Pas de formulaire.reset() : ca reviendrait aux valeurs rendues au
        // chargement (qui refletent l'URL de depart), pas a un etat neutre.
        formulaire.categorie.value = '';
        document.querySelectorAll('#nav-categories a').forEach(function (a) {
            a.classList.toggle('filtre-categorie-actif', a.dataset.categorie === '');
        });
        if (champRecherche) champRecherche.value = '';
        formulaire.type.value = '';
        if (formulaire.sous_categorie) formulaire.sous_categorie.value = '';
        appliquerSelectionTailles([]);
        selectionnerCouleur(champsCouleur, '');
        formulaire.promo.checked = false;
        formulaire.nouveautes.checked = false;
        champTri.value = '';
        if (sliderMin) definirPlagePrix(Number(sliderMin.min), Number(sliderMax.max));
        appliquerFiltres();
    }

    // ---------------------------------------------------------------
    // Boutons couleur (un seul choix, re-clic pour deselectionner)
    // ---------------------------------------------------------------

    function selectionnerCouleur(conteneur, valeur) {
        if (!conteneur) return;
        conteneur.querySelectorAll('button').forEach(function (bouton) {
            var actif = bouton.dataset.valeur === valeur && valeur !== '';
            bouton.classList.toggle('couleur-swatch-selectionne', actif);
        });
        var champCache = conteneur.parentElement.querySelector('input[type="hidden"]');
        if (champCache) champCache.value = valeur;
    }

    // ---------------------------------------------------------------
    // Boutons taille (plusieurs choix possibles, chaque bouton bascule
    // independamment) : valeurs stockees "S,M" dans le champ cache.
    // ---------------------------------------------------------------

    function taillesSelectionnees() {
        if (!champsTaille) return [];
        var champCache = champsTaille.parentElement.querySelector('input[type="hidden"]');
        return champCache && champCache.value ? champCache.value.split(',') : [];
    }

    function appliquerSelectionTailles(tailles) {
        if (!champsTaille) return;
        champsTaille.querySelectorAll('button').forEach(function (bouton) {
            bouton.classList.toggle('filtre-bouton-taille-actif', tailles.indexOf(bouton.dataset.valeur) !== -1);
        });
        var champCache = champsTaille.parentElement.querySelector('input[type="hidden"]');
        if (champCache) champCache.value = tailles.join(',');
    }

    if (champsTaille) {
        champsTaille.addEventListener('click', function (e) {
            var bouton = e.target.closest('button');
            if (!bouton) return;
            var tailles = taillesSelectionnees();
            var index = tailles.indexOf(bouton.dataset.valeur);
            if (index === -1) tailles.push(bouton.dataset.valeur); else tailles.splice(index, 1);
            appliquerSelectionTailles(tailles);
            appliquerFiltres();
        });
    }

    if (champsCouleur) {
        champsCouleur.addEventListener('click', function (e) {
            var bouton = e.target.closest('button');
            if (!bouton) return;
            var champCache = champsCouleur.parentElement.querySelector('input[type="hidden"]');
            var nouvelleValeur = champCache.value === bouton.dataset.valeur ? '' : bouton.dataset.valeur;
            selectionnerCouleur(champsCouleur, nouvelleValeur);
            appliquerFiltres();
        });
    }

    // ---------------------------------------------------------------
    // Slider de prix (double curseur, synchronise avec les champs numeriques)
    // ---------------------------------------------------------------

    var sliderMin = document.getElementById('prix-range-min');
    var sliderMax = document.getElementById('prix-range-max');
    var champMinPrix = document.getElementById('prix-champ-min');
    var champMaxPrix = document.getElementById('prix-champ-max');
    var plage = document.getElementById('prix-slider-plage');

    function definirPlagePrix(min, max) {
        if (!sliderMin) return;
        sliderMin.value = min;
        sliderMax.value = max;
        champMinPrix.value = min;
        champMaxPrix.value = max;
        dessinerBarrePrix();
    }

    function dessinerBarrePrix() {
        if (!sliderMin) return;
        var bornesMin = Number(sliderMin.min), bornesMax = Number(sliderMin.max);
        var etendue = bornesMax - bornesMin || 1;
        var pourcentMin = ((Number(sliderMin.value) - bornesMin) / etendue) * 100;
        var pourcentMax = ((Number(sliderMax.value) - bornesMin) / etendue) * 100;
        plage.style.left = pourcentMin + '%';
        plage.style.right = (100 - pourcentMax) + '%';
    }

    var appliquerPrixDifferee = differerAppel(function () { appliquerFiltres(); }, 450);

    if (sliderMin) {
        dessinerBarrePrix();

        sliderMin.addEventListener('input', function () {
            if (Number(sliderMin.value) > Number(sliderMax.value)) sliderMin.value = sliderMax.value;
            champMinPrix.value = sliderMin.value;
            dessinerBarrePrix();
            appliquerPrixDifferee();
        });
        sliderMax.addEventListener('input', function () {
            if (Number(sliderMax.value) < Number(sliderMin.value)) sliderMax.value = sliderMin.value;
            champMaxPrix.value = sliderMax.value;
            dessinerBarrePrix();
            appliquerPrixDifferee();
        });
        champMinPrix.addEventListener('input', differerAppel(function () {
            var v = Math.max(Number(sliderMin.min), Math.min(Number(champMinPrix.value || 0), Number(sliderMax.value)));
            sliderMin.value = v;
            dessinerBarrePrix();
            appliquerFiltres();
        }, 450));
        champMaxPrix.addEventListener('input', differerAppel(function () {
            var v = Math.min(Number(sliderMax.max), Math.max(Number(champMaxPrix.value || 0), Number(sliderMin.value)));
            sliderMax.value = v;
            dessinerBarrePrix();
            appliquerFiltres();
        }, 450));
    }

    // ---------------------------------------------------------------
    // Reste du formulaire : select/checkbox/radio -> application immediate,
    // recherche texte -> appel differe
    // ---------------------------------------------------------------

    formulaire.querySelectorAll('select, input[type="checkbox"], input[type="radio"]').forEach(function (champ) {
        champ.addEventListener('change', function () { appliquerFiltres(); });
    });

    // champRecherche et champTri sont hors du <form> (relies par
    // form="formulaire-filtres" pour rester a cote de la grille) : la
    // boucle ci-dessus, qui ne parcourt que les descendants du formulaire,
    // ne les trouve pas. Ecouteurs poses explicitement.
    if (champRecherche) {
        champRecherche.addEventListener('input', differerAppel(function () { appliquerFiltres(); }, 400));
    }
    if (champTri) {
        champTri.addEventListener('change', function () { appliquerFiltres(); });
    }

    if (navCategories) {
        navCategories.addEventListener('click', function (e) {
            var lien = e.target.closest('a[data-categorie]');
            if (!lien) return;
            e.preventDefault();
            formulaire.categorie.value = lien.dataset.categorie;
            navCategories.querySelectorAll('a').forEach(function (a) {
                a.classList.toggle('filtre-categorie-actif', a === lien);
            });
            appliquerFiltres();
        });
    }

    // ---------------------------------------------------------------
    // Entete (types et categories) : sur la boutique, ses liens filtrent
    // sans recharger, a partir des filtres EN COURS. Leur href, calcule au
    // chargement de la page, garde les filtres de ce moment-la : il ferait
    // revenir un type retire entre-temps (puce x, panneau de filtres).
    // ---------------------------------------------------------------

    document.addEventListener('click', function (e) {
        var lien = e.target.closest('a[data-entete-type], a[data-entete-categorie]');
        if (!lien) return;
        e.preventDefault();

        if (lien.hasAttribute('data-entete-type')) {
            // Recliquer sur le type deja choisi le retire.
            var type = lien.dataset.enteteType;
            formulaire.type.value = formulaire.type.value === type ? '' : type;
        } else {
            formulaire.categorie.value = lien.dataset.enteteCategorie;
            // "Tout" (categorie vide) montre vraiment tout : il retire aussi le type.
            if (lien.dataset.enteteCategorie === '') formulaire.type.value = '';
        }

        // Lien du panneau mobile : on referme le panneau pour voir la grille.
        var panneauMobile = document.getElementById('entete-panel-mobile');
        var fermerPanneauMobile = document.getElementById('entete-panel-fermer');
        if (panneauMobile && fermerPanneauMobile && panneauMobile.classList.contains('entete-panel-ouvert')) {
            fermerPanneauMobile.click();
        }

        appliquerFiltres();
    });

    // Souligne le type et la categorie reellement appliques.
    function mettreAJourEntete() {
        var type = formulaire.type.value;
        var categorie = formulaire.categorie.value;
        document.querySelectorAll('.entete-types a[data-entete-type]').forEach(function (a) {
            a.classList.toggle('entete-type-actif', a.dataset.enteteType === type);
        });
        document.querySelectorAll('.entete-pastilles a[data-entete-categorie]').forEach(function (a) {
            a.classList.toggle('filtre-categorie-actif', a.dataset.enteteCategorie === categorie);
        });
    }

    var boutonReinitialiser = document.getElementById('reinitialiser-filtres');
    if (boutonReinitialiser) boutonReinitialiser.addEventListener('click', reinitialiserFiltres);

    // ---------------------------------------------------------------
    // Pagination (delegation : la grille est remplacee a chaque fetch)
    // ---------------------------------------------------------------

    function initialiserPagination() {
        resultats.querySelectorAll('.pagination-lien, .pagination-numero').forEach(function (lien) {
            lien.addEventListener('click', function (e) {
                e.preventDefault();
                var url = new URL(lien.href, window.location.href);
                var page = url.searchParams.get('page') || '1';
                appliquerFiltres({ page: page });
                contenu.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    }

    // ---------------------------------------------------------------
    // Cartes produit : couleur cliquable, change la photo affichee.
    // A rejouer apres chaque remplacement AJAX de la grille.
    // ---------------------------------------------------------------

    function initialiserCartesProduit() {
        resultats.querySelectorAll('.carte-produit').forEach(function (carte) {
            var script = carte.querySelector('.carte-produit-donnees');
            if (!script) return;

            var donnees = JSON.parse(script.textContent);
            var couleurActuelle = script.dataset.couleurDefaut;
            var indexImage = 0;
            var image = carte.querySelector('.carte-produit-image');
            var flecheGauche = carte.querySelector('.carte-produit-fleche-gauche');
            var flecheDroite = carte.querySelector('.carte-produit-fleche-droite');
            var puces = carte.querySelectorAll('.carte-produit-puce');

            function imagesCouleurActuelle() {
                return (donnees.images && donnees.images[couleurActuelle]) || [];
            }

            function rafraichirCarrousel() {
                var images = imagesCouleurActuelle();
                if (image && images[indexImage]) image.src = images[indexImage];
                var plusieurs = images.length > 1;
                if (flecheGauche) flecheGauche.hidden = !plusieurs;
                if (flecheDroite) flecheDroite.hidden = !plusieurs;
                puces.forEach(function (puce, i) {
                    puce.classList.toggle('carte-produit-puce-actif', i === indexImage);
                    puce.hidden = i >= images.length;
                });
            }

            function changerImage(sens) {
                var images = imagesCouleurActuelle();
                if (images.length < 2) return;
                indexImage = (indexImage + sens + images.length) % images.length;
                rafraichirCarrousel();
            }

            if (flecheGauche) {
                flecheGauche.addEventListener('click', function (evenement) {
                    evenement.preventDefault();
                    evenement.stopPropagation();
                    changerImage(-1);
                });
            }
            if (flecheDroite) {
                flecheDroite.addEventListener('click', function (evenement) {
                    evenement.preventDefault();
                    evenement.stopPropagation();
                    changerImage(1);
                });
            }

            // --- Ajout rapide au panier ---
            var formAjout = carte.querySelector('.carte-produit-ajout');
            var messageAjout = formAjout ? formAjout.querySelector('.carte-produit-message') : null;

            // Redessine la liste deroulante des tailles en stock de la couleur
            // choisie (valeur d'une option = identifiant de la variante).
            // Meme regles que carte-produit.php : option vide "Choisir une
            // taille" s'il y a plusieurs tailles, taille unique preselectionnee.
            function afficherTailles() {
                if (!formAjout) return;
                var liste = formAjout.querySelector('.carte-produit-select-taille');
                var tailles = (donnees.tailles && donnees.tailles[couleurActuelle]) || {};
                var noms = Object.keys(tailles);
                liste.innerHTML = '';
                if (noms.length > 1) {
                    liste.appendChild(new Option('Choisir une taille', ''));
                }
                noms.forEach(function (taille) {
                    liste.appendChild(new Option(taille === 'UNIQUE' ? 'Taille unique' : taille, tailles[taille]));
                });
            }

            // textContent (et non innerHTML) : le message est du texte, jamais
            // du HTML. Le lien "Voir le panier" est cree a part.
            function afficherMessage(texte, reussi) {
                messageAjout.hidden = false;
                messageAjout.textContent = texte;
                messageAjout.classList.toggle('carte-produit-message-ok', reussi);
                if (reussi) {
                    var lien = document.createElement('a');
                    lien.href = 'panier.php';
                    lien.textContent = 'Voir le panier →';
                    messageAjout.appendChild(document.createTextNode(' '));
                    messageAjout.appendChild(lien);
                }
            }

            if (formAjout) {
                formAjout.addEventListener('submit', function (evenement) {
                    evenement.preventDefault();
                    var bouton = formAjout.querySelector('button[type="submit"]');
                    bouton.disabled = true; // pas de double ajout

                    // "Accept: application/json" : panier-ajouter.php repond
                    // alors en JSON au lieu de rediriger vers la fiche produit.
                    fetch(formAjout.action, {
                        method: 'POST',
                        body: new FormData(formAjout),
                        headers: { 'Accept': 'application/json' }
                    })
                        .then(function (reponse) { return reponse.json(); })
                        .then(function (resultat) {
                            // Visiteur non connecte : le panier demande un compte.
                            if (resultat.connexion) {
                                window.location.href = 'connexion.php';
                                return;
                            }
                            afficherMessage(resultat.message, resultat.ok);
                            if (resultat.ok) mettreAJourCompteurPanier(resultat.articles);
                        })
                        .catch(function () {
                            afficherMessage("L'article n'a pas pu être ajouté. Réessayez.", false);
                        })
                        .then(function () {
                            bouton.disabled = false;
                        });
                });
            }

            carte.querySelectorAll('.couleur-swatch-cliquable').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    couleurActuelle = btn.dataset.couleur;
                    indexImage = 0;
                    carte.querySelectorAll('.couleur-swatch-cliquable').forEach(function (b) {
                        b.classList.toggle('couleur-swatch-selectionne', b === btn);
                    });
                    rafraichirCarrousel();
                    afficherTailles();
                    if (messageAjout) messageAjout.hidden = true;
                    // La fiche produit s'ouvrira sur la couleur choisie ici.
                    carte.querySelectorAll('.carte-produit-lien-image, .carte-produit-lien-titre').forEach(function (lien) {
                        var url = new URL(lien.href, window.location.href);
                        url.searchParams.set('couleur', couleurActuelle);
                        lien.href = url.pathname.split('/').pop() + url.search;
                    });
                });
            });

            rafraichirCarrousel();
        });
    }

    // Met a jour la pastille rouge du panier dans l'en-tete (nombre
    // d'articles), en la creant si le panier etait vide jusque-la.
    function mettreAJourCompteurPanier(nombre) {
        var lienPanier = document.querySelector('.entete-icone[href$="panier.php"]');
        if (!lienPanier) return;

        var compteur = lienPanier.querySelector('.entete-compteur');
        if (!compteur) {
            compteur = document.createElement('span');
            compteur.className = 'entete-compteur';
            lienPanier.appendChild(compteur);
        }
        compteur.textContent = nombre;
        lienPanier.setAttribute('aria-label', 'Mon panier : ' + nombre + ' article(s)');
    }

    // ---------------------------------------------------------------
    // Panneau de filtres : sidebar repliable sur desktop, panneau
    // coulissant sur mobile. Meme bouton "Filtrer" pour ouvrir/fermer
    // dans les deux cas (sidebar-filtres-cachee gere le desktop,
    // sidebar-filtres-ouverte gere la transition mobile - voir le CSS).
    // ---------------------------------------------------------------

    var sidebar = document.getElementById('sidebar-filtres');
    var voile = document.getElementById('voile-filtres');
    var ouvrirFiltres = document.getElementById('ouvrir-filtres');
    var fermerFiltres = document.getElementById('fermer-filtres');
    var afficherResultatsMobile = document.getElementById('afficher-resultats-mobile');

    // Le voile plein ecran et le blocage du scroll n'ont de sens que pour
    // le panneau coulissant mobile ; sur desktop, fermer/ouvrir la sidebar
    // ne doit pas empecher de faire defiler le reste de la page.
    function estMobile() {
        return window.innerWidth <= 900;
    }

    // Repliee par defaut : suit la classe sidebar-filtres-cachee deja
    // posee cote serveur (boutique.php), desktop comme mobile.
    var panneauOuvert = false;

    function ouvrirPanneau() {
        sidebar.classList.remove('sidebar-filtres-cachee');
        sidebar.classList.add('sidebar-filtres-ouverte');
        if (estMobile()) {
            if (voile) voile.hidden = false;
            document.body.style.overflow = 'hidden';
        }
        panneauOuvert = true;
    }
    function fermerPanneau() {
        sidebar.classList.add('sidebar-filtres-cachee');
        sidebar.classList.remove('sidebar-filtres-ouverte');
        if (voile) voile.hidden = true;
        document.body.style.overflow = '';
        panneauOuvert = false;
    }
    function basculerPanneau() {
        if (panneauOuvert) fermerPanneau(); else ouvrirPanneau();
    }

    if (ouvrirFiltres) ouvrirFiltres.addEventListener('click', basculerPanneau);
    if (fermerFiltres) fermerFiltres.addEventListener('click', fermerPanneau);
    if (voile) voile.addEventListener('click', fermerPanneau);
    if (afficherResultatsMobile) afficherResultatsMobile.addEventListener('click', fermerPanneau);

    // ---------------------------------------------------------------
    // Navigation retour/avant du navigateur
    // ---------------------------------------------------------------

    window.addEventListener('popstate', function () {
        var params = new URLSearchParams(window.location.search);

        formulaire.categorie.value = params.get('categorie') || '';
        document.querySelectorAll('#nav-categories a').forEach(function (a) {
            a.classList.toggle('filtre-categorie-actif', a.dataset.categorie === (params.get('categorie') || ''));
        });
        if (champRecherche) champRecherche.value = params.get('q') || '';
        formulaire.type.value = params.get('type') || '';
        if (formulaire.sous_categorie) formulaire.sous_categorie.value = params.get('sous_categorie') || '';
        appliquerSelectionTailles(params.get('taille') ? params.get('taille').split(',') : []);
        selectionnerCouleur(champsCouleur, params.get('couleur') || '');
        if (sliderMin) definirPlagePrix(
            params.get('prix_min') || sliderMin.min,
            params.get('prix_max') || sliderMin.max
        );
        formulaire.promo.checked = params.get('promo') === '1';
        formulaire.nouveautes.checked = params.get('nouveautes') === '1';
        champTri.value = params.get('tri') || '';

        chargerResultats(params, false);
    });

    initialiserCartesProduit();
    initialiserPagination();
    mettreAJourFiltresActifs();
})();
