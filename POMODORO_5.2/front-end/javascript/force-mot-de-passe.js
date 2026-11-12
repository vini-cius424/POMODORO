// =====================================================================
// POMODORO STORE - Barre de robustesse du mot de passe
//
// Pour chaque champ <input data-force-mdp="id-de-la-barre">, met a jour
// la barre pendant la frappe. C'est seulement une aide visuelle : les
// regles qui comptent vraiment sont reverifiees par le serveur
// (inscription.php).
//
// Niveaux :
//   0 "Trop faible" : ne respecte pas les regles du site (8 caracteres,
//                     une lettre, un chiffre) -> le serveur le refuserait ;
//   1 "Faible"      : respecte juste les regles ;
//   2 "Moyen"       : les regles + 1 bonus ;
//   3 "Robuste"     : les regles + au moins 2 bonus.
// Bonus : 12 caracteres ou plus, majuscules ET minuscules, un caractere
// special (tout ce qui n'est ni une lettre ni un chiffre).
// =====================================================================

(function () {
    'use strict';

    var NIVEAUX = [
        { texte: 'Trop faible', classe: 'force-mdp-niveau-0' },
        { texte: 'Faible',      classe: 'force-mdp-niveau-1' },
        { texte: 'Moyen',       classe: 'force-mdp-niveau-2' },
        { texte: 'Robuste',     classe: 'force-mdp-niveau-3' }
    ];

    function calculerNiveau(motDePasse) {
        // Memes regles minimales que le serveur.
        var respecteRegles = motDePasse.length >= 8
            && /[a-zA-Z]/.test(motDePasse)
            && /[0-9]/.test(motDePasse);

        if (!respecteRegles) return 0;

        var bonus = 0;
        if (motDePasse.length >= 12) bonus++;
        if (/[a-z]/.test(motDePasse) && /[A-Z]/.test(motDePasse)) bonus++;
        if (/[^a-zA-Z0-9]/.test(motDePasse)) bonus++;

        return Math.min(1 + bonus, 3);
    }

    document.querySelectorAll('input[data-force-mdp]').forEach(function (champ) {
        var barre = document.getElementById(champ.dataset.forceMdp);
        if (!barre) return;

        var texte = barre.querySelector('.force-mdp-texte');

        champ.addEventListener('input', function () {
            // Champ vide : on cache la barre plutot que d'afficher "Trop faible".
            barre.hidden = champ.value === '';
            if (barre.hidden) return;

            var niveau = NIVEAUX[calculerNiveau(champ.value)];

            NIVEAUX.forEach(function (n) { barre.classList.remove(n.classe); });
            barre.classList.add(niveau.classe);
            texte.textContent = 'Robustesse : ' + niveau.texte;
        });
    });
})();
