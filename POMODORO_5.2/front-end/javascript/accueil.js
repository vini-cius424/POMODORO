(function () {
    'use strict';

    var reduitMouvement = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // -------------------------------------------------------------
    // Nav : transparente sur la video, pleine des qu'on quitte le hero
    // -------------------------------------------------------------

    var nav = document.getElementById('accueil-nav');
    var hero = document.querySelector('.accueil-hero');

    if (nav && hero && 'IntersectionObserver' in window) {
        var observateurNav = new IntersectionObserver(function (entrees) {
            entrees.forEach(function (entree) {
                nav.classList.toggle('accueil-nav-solide', !entree.isIntersecting);
            });
        }, { threshold: .1 });
        observateurNav.observe(hero);
    }

    // -------------------------------------------------------------
    // Revelation au scroll : chaque section .accueil-reveal ne se
    // devoile qu'une fois, la premiere fois qu'elle entre dans l'ecran.
    // -------------------------------------------------------------

    var sectionsRevelees = document.querySelectorAll('.accueil-reveal');

    if (!sectionsRevelees.length) return;

    if (reduitMouvement || !('IntersectionObserver' in window)) {
        sectionsRevelees.forEach(function (section) { section.classList.add('en-vue'); });
        return;
    }

    var observateur = new IntersectionObserver(function (entrees, obs) {
        entrees.forEach(function (entree) {
            if (entree.isIntersecting) {
                entree.target.classList.add('en-vue');
                obs.unobserve(entree.target);
            }
        });
    }, { threshold: .2, rootMargin: '0px 0px -80px 0px' });

    sectionsRevelees.forEach(function (section) { observateur.observe(section); });

    // Filet de securite : si l'observateur ne se declenche jamais pour une
    // raison ou une autre, le contenu ne doit pas rester invisible.
    setTimeout(function () {
        sectionsRevelees.forEach(function (section) { section.classList.add('en-vue'); });
    }, 4000);

    // -------------------------------------------------------------
    // Defilement doux vers la premiere section au clic sur "Decouvrir"
    // -------------------------------------------------------------

    var indice = document.querySelector('.accueil-scroll-cue');
    if (indice) {
        indice.addEventListener('click', function (e) {
            var cible = document.querySelector(indice.getAttribute('href'));
            if (!cible) return;
            e.preventDefault();
            cible.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }
})();
