// =====================================================================
// POMODORO STORE - Onglet qui attend la confirmation de l'email
//
// Charge sur inscription.php et connexion.php quand un email de
// confirmation vient d'etre envoye. Le lien de l'email s'ouvre dans un
// NOUVEL onglet (confirmer-email.php) : celui-ci confirme le compte et
// connecte la session, puis envoie "confirme" sur un BroadcastChannel.
// Cet onglet-ci repond "recu" (le nouvel onglet sait alors qu'il peut
// se refermer) et passe sur la boutique, deja connecte : les onglets
// d'un meme navigateur partagent le cookie de session.
//
// Source : https://developer.mozilla.org/fr/docs/Web/API/BroadcastChannel
// =====================================================================

(function () {
    'use strict';

    // Navigateur trop ancien : rien a faire, le nouvel onglet ira seul
    // sur la boutique.
    if (!('BroadcastChannel' in window)) return;

    var canal = new BroadcastChannel('pomodoro-confirmation');

    canal.addEventListener('message', function (evenement) {
        if (evenement.data !== 'confirme') return;

        canal.postMessage('recu');
        window.location.href = 'boutique.php';
    });
})();
