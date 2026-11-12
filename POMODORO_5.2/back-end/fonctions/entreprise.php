<?php
// =====================================================================
// POMODORO STORE - Informations legales de l'entreprise
//
// Utilisees par les pages Mentions legales, Conditions generales et
// Politique de confidentialite (front-end/pages-infos/). Elles sont
// ecrites UNE seule fois ici : changer l'adresse ou le numero IDE met
// les trois pages a jour.
//
// Les valeurs entre crochets sont a completer avec les vraies donnees
// de l'entreprise avant une mise en ligne reelle : la loi contre la
// concurrence deloyale (LCD, art. 3 al. 1 let. s) impose a un site de
// vente en ligne d'indiquer clairement son identite et une adresse de
// contact, courrier electronique compris.
// =====================================================================

function entreprise()
{
    return [
        'raison_sociale' => 'Pomodoro Store',
        'forme'          => '[forme juridique, ex. entreprise individuelle ou Sàrl]',
        'responsable'    => '[nom de la personne responsable]',
        'adresse'        => '[rue et numéro]',
        'npa_ville'      => '[NPA et ville]',
        'pays'           => 'Suisse',
        'email'          => 'pomodorostore.ch@gmail.com',
        'ide'            => '[numéro IDE, format CHE-123.456.789]',
        // Numero TVA = numero IDE suivi de "TVA", seulement si
        // l'entreprise est assujettie (chiffre d'affaires >= 100'000 CHF).
        'tva'            => '[numéro TVA, ex. CHE-123.456.789 TVA, si assujettie]',
        'hebergeur'      => '[nom et adresse de l\'hébergeur du site]',
        // Retour volontaire (pas d'obligation legale en Suisse), en jours
        // apres la reception. Affiche par les CGV et la page Retours.
        'delai_retour_jours' => 30,
        // Date de la derniere mise a jour des textes legaux.
        'mise_a_jour'    => '01.10.2026',
    ];
}
