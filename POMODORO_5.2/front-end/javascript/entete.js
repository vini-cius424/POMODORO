(function () {
    'use strict';

    // -------------------------------------------------------------
    // Entete sticky : passe en mode "compact" des qu'on quitte le haut
    // de la page (fond semi-opaque + flou + ombre, voir le CSS).
    // -------------------------------------------------------------

    var entete = document.getElementById('entete');
    if (entete) {
        var basculerCompact = function () {
            entete.classList.toggle('entete-compacte', window.scrollY > 40);
        };
        basculerCompact();
        window.addEventListener('scroll', basculerCompact, { passive: true });
    }

    // -------------------------------------------------------------
    // Recherche repliable
    // -------------------------------------------------------------

    var rechercheBouton = document.getElementById('entete-recherche-ouvrir');
    var recherchePanel = document.getElementById('entete-recherche-panel');
    var rechercheFermer = document.getElementById('entete-recherche-fermer');
    var rechercheChamp = document.getElementById('entete-recherche-champ');

    function ouvrirRecherche() {
        recherchePanel.classList.add('entete-recherche-ouverte');
        rechercheBouton.setAttribute('aria-expanded', 'true');
        window.setTimeout(function () { rechercheChamp.focus(); }, 200);
    }

    function fermerRecherche() {
        recherchePanel.classList.remove('entete-recherche-ouverte');
        rechercheBouton.setAttribute('aria-expanded', 'false');
    }

    if (rechercheBouton && recherchePanel) {
        rechercheBouton.addEventListener('click', function () {
            var ouverte = recherchePanel.classList.contains('entete-recherche-ouverte');
            if (ouverte) fermerRecherche(); else ouvrirRecherche();
        });
    }
    if (rechercheFermer) rechercheFermer.addEventListener('click', fermerRecherche);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && recherchePanel && recherchePanel.classList.contains('entete-recherche-ouverte')) {
            fermerRecherche();
        }
    });

    // -------------------------------------------------------------
    // Panneau mobile plein ecran (categories, types, compte)
    // -------------------------------------------------------------

    var hamburger = document.getElementById('entete-hamburger');
    var panelMobile = document.getElementById('entete-panel-mobile');
    var panelVoile = document.getElementById('entete-panel-voile');
    var panelFermer = document.getElementById('entete-panel-fermer');

    function ouvrirPanel() {
        panelMobile.classList.add('entete-panel-ouvert');
        panelVoile.hidden = false;
        hamburger.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }

    function fermerPanel() {
        panelMobile.classList.remove('entete-panel-ouvert');
        panelVoile.hidden = true;
        hamburger.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    if (hamburger && panelMobile) hamburger.addEventListener('click', ouvrirPanel);
    if (panelFermer) panelFermer.addEventListener('click', fermerPanel);
    if (panelVoile) panelVoile.addEventListener('click', fermerPanel);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && panelMobile && panelMobile.classList.contains('entete-panel-ouvert')) {
            fermerPanel();
        }
    });
})();
