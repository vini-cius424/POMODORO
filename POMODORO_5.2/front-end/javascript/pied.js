(function () {
    'use strict';

    // -------------------------------------------------------------
    // Revelation au scroll : chaque colonne/bloc .pied-reveal ne se
    // devoile qu'une fois, la premiere fois qu'il entre dans l'ecran.
    // -------------------------------------------------------------

    var reduitMouvement = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var blocsRevelees = document.querySelectorAll('.pied-reveal');

    if (blocsRevelees.length) {
        if (reduitMouvement || !('IntersectionObserver' in window)) {
            blocsRevelees.forEach(function (bloc) { bloc.classList.add('pied-en-vue'); });
        } else {
            var observateur = new IntersectionObserver(function (entrees, obs) {
                entrees.forEach(function (entree) {
                    if (entree.isIntersecting) {
                        entree.target.classList.add('pied-en-vue');
                        obs.unobserve(entree.target);
                    }
                });
            }, { threshold: .15 });
            blocsRevelees.forEach(function (bloc) { observateur.observe(bloc); });

            // Filet de securite si l'observateur ne se declenche jamais.
            window.setTimeout(function () {
                blocsRevelees.forEach(function (bloc) { bloc.classList.add('pied-en-vue'); });
            }, 4000);
        }
    }

    // -------------------------------------------------------------
    // Accordeon (actif uniquement sur mobile, voir le CSS : sur
    // desktop .pied-accordeon-contenu est toujours visible quel que
    // soit l'etat de la classe).
    // -------------------------------------------------------------

    document.querySelectorAll('.pied-accordeon-bouton').forEach(function (bouton) {
        bouton.addEventListener('click', function () {
            var colonne = bouton.closest('.pied-colonne');
            if (colonne) colonne.classList.toggle('pied-accordeon-ouvert');
        });
    });

    // -------------------------------------------------------------
    // Newsletter : l'email est envoye en arriere-plan (fetch) a
    // actions/newsletter-inscrire.php, qui passe par Brevo et repond
    // en JSON {ok, message}. La page ne se recharge pas.
    // -------------------------------------------------------------

    var formulaire = document.getElementById('pied-newsletter-form');

    if (formulaire) {
        var message = formulaire.querySelector('.pied-newsletter-message');
        var boutonEnvoi = formulaire.querySelector('button[type="submit"]');

        formulaire.addEventListener('submit', function (e) {
            e.preventDefault();

            // Bouton bloque pendant l'envoi : pas de double inscription.
            boutonEnvoi.disabled = true;
            message.hidden = false;
            message.textContent = 'Envoi en cours...';

            fetch(formulaire.action, { method: 'POST', body: new FormData(formulaire) })
                .then(function (reponse) { return reponse.json(); })
                .then(function (resultat) {
                    // textContent (et non innerHTML) : le message est
                    // affiche comme du texte, jamais interprete en HTML.
                    message.textContent = resultat.message;
                    if (resultat.ok) formulaire.reset();
                })
                .catch(function () {
                    message.textContent = "L'inscription n'a pas pu être envoyée. Réessayez plus tard.";
                })
                .then(function () {
                    boutonEnvoi.disabled = false;
                });
        });
    }

    // -------------------------------------------------------------
    // Pop-up "-20 % sur votre premiere commande". Le PHP (pied.php) ne
    // l'ecrit dans la page que pour un visiteur NON connecte ; ici on
    // decide seulement QUAND l'ouvrir : 4 secondes apres l'arrivee, a
    // chaque nouvelle visite.
    //
    // sessionStorage : memoire du navigateur limitee a l'onglet. Une fois
    // ferme, le pop-up ne revient plus pendant la visite (changement de
    // page, rechargement) ; il revient dans un nouvel onglet ou apres
    // avoir rouvert le navigateur. try/catch : le stockage peut etre
    // refuse, le pop-up reviendrait alors a chaque page.
    // -------------------------------------------------------------

    var popup = document.getElementById('popup-bienvenue');
    var CLE_POPUP = 'pomodoro-popup-bienvenue';

    function popupDejaFerme() {
        try {
            return window.sessionStorage.getItem(CLE_POPUP) === 'ferme';
        } catch (e) {
            return false;
        }
    }

    function memoriserFermeture() {
        try {
            window.sessionStorage.setItem(CLE_POPUP, 'ferme');
        } catch (e) {
            // Rien a faire, voir plus haut.
        }
    }

    // typeof showModal : un navigateur trop ancien ne connait pas <dialog>.
    if (popup && typeof popup.showModal === 'function' && !popupDejaFerme()) {
        window.setTimeout(function () {
            if (!popup.open) popup.showModal();
        }, 4000);

        // On memorise AU MOMENT du geste, pas sur l'evenement "close" du
        // <dialog> : celui-ci arrive en differe, et pas du tout si
        // l'onglet passe en arriere-plan.
        function fermerPopup() {
            memoriserFermeture();
            popup.close();
        }

        // Croix et "Plus tard".
        popup.querySelectorAll('[data-popup-fermer]').forEach(function (bouton) {
            bouton.addEventListener('click', fermerPopup);
        });

        // "Creer mon compte" / "J'ai deja un compte" : le visiteur part vers
        // l'inscription ou la connexion, inutile de lui remontrer l'offre.
        popup.querySelectorAll('a').forEach(function (lien) {
            lien.addEventListener('click', memoriserFermeture);
        });

        // Clic sur le fond sombre autour de la fenetre : la cible est le
        // <dialog> lui-meme (le contenu est dans .popup-bienvenue-contenu).
        popup.addEventListener('click', function (e) {
            if (e.target === popup) fermerPopup();
        });

        // Touche Echap : "cancel" est declenche tout de suite, juste avant
        // que le navigateur ne ferme la fenetre lui-meme.
        popup.addEventListener('cancel', memoriserFermeture);
    }
})();
