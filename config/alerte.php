<?php

/*
|--------------------------------------------------------------------------
| Paramètres du calcul d'alerte PAR90
|--------------------------------------------------------------------------
|
| Cahier des charges, section 5 « Paramètres en configuration ».
| Aucune de ces valeurs n'est écrite ailleurs dans le code. Elles initialisent
| le premier jeu de la table `parametres`, d'où le calcul les lit ensuite.
| Ce fichier ne doit contenir que des valeurs littérales : les tests du
| moteur le lisent directement, sans démarrer Laravel.
|
*/

return [

    // Limite de niveau du PAR90, en % : au-delà, statut « Hors limite ».
    'limite_par90' => 5,

    // Seuil de hausse du PAR90 sur trois mois, en points : à partir de là, « Alerte ».
    'seuil_hausse_3m' => 0.5,

    // Centile des bonds passés qui sert de seuil au signal avancé (entier, 1 à 100).
    'centile_bond' => 70,

    // Nombre minimum de bonds passés pour calculer un seuil.
    'minimum_bonds' => 12,

    // Horizon en mois : décalage de la hausse sur trois mois et de la vérification à t+3.
    'horizon_verification' => 3,

    // Contrôle d'import : écart toléré entre par90_mt et par90 × encours, en %.
    // Au-delà, avertissement (section 4).
    'tolerance_par90_mt' => 1,

    // Nom affiché de l'institution.
    'nom_institution' => 'Institution',

];
